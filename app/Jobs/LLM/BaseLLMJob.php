<?php

namespace App\Jobs\LLM;

use App\Events\MessageReceived;
use App\Events\QueryStatusUpdated;
use App\Models\ConversationMessage;
use App\Models\LLMQuery;
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

    protected string $prompt;
    protected ?string $model;
    protected ?int $llmQueryId;
    protected array $options;

    /**
     * Create a new job instance.
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
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $llmQuery = null;

        try {
            if ($this->llmQueryId) {
                $llmQuery = LLMQuery::find($this->llmQueryId);
                if ($llmQuery) {
                    $llmQuery->update(['status' => 'processing']);

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
                ];

                // Check if job has additional metadata to store (e.g., reasoning_content)
                if (method_exists($this, 'getAdditionalMetadata')) {
                    $additionalMetadata = $this->getAdditionalMetadata();
                    if ($additionalMetadata) {
                        $updateData = array_merge($updateData, array_filter($additionalMetadata));
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
            ]);
        } catch (\Exception $e) {
            if ($llmQuery) {
                $llmQuery->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);

                // Broadcast status update to failed
                broadcast(new QueryStatusUpdated($llmQuery->fresh()));
            }

            Log::error('LLM job failed', [
                'provider' => $this->getProvider(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute the LLM query - must be implemented by child classes.
     */
    abstract protected function execute(): string;

    /**
     * Get the provider name for this job.
     */
    abstract protected function getProvider(): string;

    /**
     * Get the tags for the job (for Horizon filtering).
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
