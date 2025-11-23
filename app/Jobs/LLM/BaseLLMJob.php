<?php

namespace App\Jobs\LLM;

use App\Events\MessageReceived;
use App\Events\QueryStatusUpdated;
use App\Exceptions\LLM\LLMException;
use App\Models\ConversationMessage;
use App\Models\LLMQuery;
use App\Services\CostCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class BaseLLMJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 300;

    public $backoff = [30, 60, 120];

    public $maxExceptions = 3;

    protected string $prompt;

    protected ?string $model;

    protected ?int $llmQueryId;

    protected array $options;

    /**
     * Create a new job instance.
     *
     * @param  string  $prompt  The user prompt to send to the LLM
     * @param  string|null  $model  Optional model specification for the provider
     * @param  int|null  $llmQueryId  Optional LLMQuery ID for tracking in database
     * @param  array  $options  Additional options (user_id, conversation_id, etc.)
     */
    public function __construct(string $prompt, ?string $model = null, ?int $llmQueryId = null, array $options = [])
    {
        $this->prompt = $prompt;
        $this->model = $model;
        $this->llmQueryId = $llmQueryId;
        $this->options = $options;
    }

    /**
     * Execute the job.
     *
     * Main job handler that orchestrates the LLM query execution. Updates the query status,
     * executes the provider-specific query, captures metrics, broadcasts events, and handles
     * errors with detailed logging. Supports additional metadata capture from child classes.
     *
     * @throws \Throwable Propagates exceptions after logging and updating query status
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $llmQuery = null;

        try {
            if ($this->llmQueryId) {
                $llmQuery = LLMQuery::find($this->llmQueryId);
                if ($llmQuery) {
                    $llmQuery->update([
                        'status' => 'processing',
                        'attempts' => $this->attempts(),
                    ]);

                    // Broadcast status update to processing
                    broadcast(new QueryStatusUpdated($llmQuery->fresh()));
                }
            }

            $response = $this->execute();

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($llmQuery) {
                $updateData = [
                    'status' => 'completed',
                    'response' => $response,
                    'duration_ms' => $duration,
                    'completed_at' => now(),
                    'attempts' => $this->attempts(),
                ];

                // Check if job has additional metadata to store (e.g., reasoning_content)
                if (method_exists($this, 'getAdditionalMetadata')) {
                    $additionalMetadata = $this->getAdditionalMetadata();
                    if ($additionalMetadata) {
                        $updateData = array_merge($updateData, array_filter($additionalMetadata));
                    }
                }

                // Calculate costs if usage stats are available
                if (isset($updateData['usage_stats']) && ! empty($updateData['usage_stats'])) {
                    $costCalculator = app(CostCalculator::class);
                    $costData = $costCalculator->calculateCost(
                        $this->getProvider(),
                        $this->model,
                        $updateData['usage_stats']
                    );

                    if ($costData['calculated']) {
                        $updateData['cost_usd'] = $costData['total_cost_usd'];
                        $updateData['input_cost_usd'] = $costData['input_cost_usd'];
                        $updateData['output_cost_usd'] = $costData['output_cost_usd'];
                        $updateData['pricing_tier'] = $costData['pricing_tier'];

                        // Check budget if configured
                        $budgetLimit = config('llm.budget_limit_usd', null);
                        if ($budgetLimit && $costData['total_cost_usd'] > $budgetLimit) {
                            $updateData['over_budget'] = true;
                            Log::warning('LLM query exceeded budget limit', [
                                'query_id' => $llmQuery->id,
                                'cost' => $costData['total_cost_usd'],
                                'budget_limit' => $budgetLimit,
                                'provider' => $this->getProvider(),
                                'model' => $this->model,
                            ]);
                        }
                    }
                }

                $llmQuery->update($updateData);

                // Broadcast status update to completed
                broadcast(new QueryStatusUpdated($llmQuery->fresh()));

                // If query has conversation, broadcast message received event
                if ($llmQuery->conversation_id) {
                    // Create or find the assistant message
                    $message = ConversationMessage::firstOrCreate(
                        [
                            'conversation_id' => $llmQuery->conversation_id,
                            'llm_query_id' => $llmQuery->id,
                            'role' => 'assistant',
                        ],
                        [
                            'content' => $response,
                        ]
                    );

                    broadcast(new MessageReceived($llmQuery->conversation_id, $message, $llmQuery->fresh()));
                }
            }

            Log::info('LLM job completed', [
                'provider' => $this->getProvider(),
                'model' => $this->model,
                'duration_ms' => $duration,
                'attempts' => $this->attempts(),
                'llm_query_id' => $this->llmQueryId,
            ]);
        } catch (\Throwable $e) {
            $this->handleFailure($e, $llmQuery, $startTime);
            throw $e;
        }
    }

    /**
     * Handle job failure with detailed error logging and user-friendly messages.
     *
     * Handles both retryable and permanent failures. Differentiates between LLMException subclasses
     * and unexpected exceptions, logs detailed context, updates the LLMQuery status, and broadcasts
     * events. Non-retryable errors are immediately removed from the queue.
     *
     * @param  \Throwable  $exception  The exception that caused the failure
     * @param  LLMQuery|null  $llmQuery  The LLMQuery model being processed (if tracking)
     * @param  float  $startTime  Microtime when the job started for duration calculation
     */
    protected function handleFailure(\Throwable $exception, ?LLMQuery $llmQuery, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000);
        $isRetryable = true;
        $userMessage = $exception->getMessage();

        // Determine if this is an LLMException with additional context
        if ($exception instanceof LLMException) {
            $isRetryable = $exception->isRetryable();
            $userMessage = $exception->getUserMessage();

            Log::error('LLM job failed with LLM exception', [
                'provider' => $this->getProvider(),
                'model' => $this->model,
                'exception_type' => get_class($exception),
                'error' => $exception->getMessage(),
                'user_message' => $userMessage,
                'context' => $exception->getContext(),
                'attempts' => $this->attempts(),
                'max_tries' => $this->tries,
                'is_retryable' => $isRetryable,
                'will_retry' => $isRetryable && $this->attempts() < $this->tries,
                'duration_ms' => $duration,
                'llm_query_id' => $this->llmQueryId,
            ]);
        } else {
            // Generic exception - assume it's retryable
            Log::error('LLM job failed with unexpected exception', [
                'provider' => $this->getProvider(),
                'model' => $this->model,
                'exception_type' => get_class($exception),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'attempts' => $this->attempts(),
                'max_tries' => $this->tries,
                'will_retry' => $this->attempts() < $this->tries,
                'duration_ms' => $duration,
                'llm_query_id' => $this->llmQueryId,
            ]);
        }

        // Update LLMQuery with failure details
        if ($llmQuery) {
            $willRetry = $isRetryable && $this->attempts() < $this->tries;

            $updateData = [
                'status' => $willRetry ? 'retrying' : 'failed',
                'error' => $userMessage,
                'error_details' => [
                    'exception_type' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'attempts' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'is_retryable' => $isRetryable,
                    'will_retry' => $willRetry,
                    'duration_ms' => $duration,
                ],
                'attempts' => $this->attempts(),
            ];

            // Only set completed_at if we're not retrying
            if (! $willRetry) {
                $updateData['completed_at'] = now();
            }

            $llmQuery->update($updateData);

            // Broadcast status update
            broadcast(new QueryStatusUpdated($llmQuery->fresh()));
        }

        // For non-retryable errors, delete the job from the queue
        if (! $isRetryable && $exception instanceof LLMException) {
            $this->delete();
        }
    }

    /**
     * Determine the number of seconds to wait before retrying the job.
     *
     * Returns the backoff schedule for exponential backoff: first retry after 30s,
     * second after 60s, third after 120s. Subsequent retries will fail permanently.
     *
     * @return array Backoff delay array in seconds
     */
    public function backoff(): array
    {
        return $this->backoff;
    }

    /**
     * Handle a job failure.
     *
     * Called when the job has exhausted all retry attempts (after 3 failed attempts).
     * Logs a critical error, marks the query as permanently failed, and broadcasts
     * final status to connected clients.
     *
     * @param  \Throwable  $exception  The exception that caused the final failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('LLM job permanently failed after all retries', [
            'provider' => $this->getProvider(),
            'model' => $this->model,
            'exception_type' => get_class($exception),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'llm_query_id' => $this->llmQueryId,
        ]);

        if ($this->llmQueryId) {
            $llmQuery = LLMQuery::find($this->llmQueryId);
            if ($llmQuery) {
                $userMessage = $exception instanceof LLMException
                    ? $exception->getUserMessage()
                    : $exception->getMessage();

                $llmQuery->update([
                    'status' => 'failed',
                    'error' => $userMessage,
                    'error_details' => [
                        'exception_type' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'attempts' => $this->attempts(),
                        'permanently_failed' => true,
                    ],
                    'completed_at' => now(),
                ]);

                // Broadcast final failure status
                broadcast(new QueryStatusUpdated($llmQuery->fresh()));
            }
        }
    }

    /**
     * Execute the LLM query - must be implemented by child classes.
     *
     * Each provider job should implement this to call their respective LLM API
     * with the stored prompt, model, and options. Should return the full response text.
     *
     * @return string The LLM response text
     *
     * @throws \Throwable Provider-specific exceptions or network errors
     */
    abstract protected function execute(): string;

    /**
     * Get the provider name for this job.
     *
     * Returns the name of the LLM provider this job handles (e.g., 'claude', 'ollama').
     * Used for logging, tagging, and provider-specific behavior.
     *
     * @return string The provider identifier
     */
    abstract protected function getProvider(): string;

    /**
     * Get the tags for the job (for Horizon filtering).
     *
     * Returns an array of tags used by Horizon for filtering and monitoring jobs.
     * Includes the provider, model, and generic 'llm' tag.
     *
     * @return array Array of tag strings
     */
    public function tags(): array
    {
        return [
            'llm',
            $this->getProvider(),
            $this->model ?? 'default',
        ];
    }
}
