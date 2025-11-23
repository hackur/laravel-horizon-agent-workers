<?php

namespace App\Services;

use App\Jobs\LLM\Claude\ClaudeQueryJob;
use App\Jobs\LLM\LMStudio\LMStudioQueryJob;
use App\Jobs\LLM\LocalCommandJob;
use App\Jobs\LLM\Ollama\OllamaQueryJob;
use App\Models\LLMQuery;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LLMQueryDispatcher
{
    /**
     * Constructor for LLMQueryDispatcher.
     *
     * @param  ProviderHealthCheck  $healthCheck  Service for checking provider health and availability
     */
    public function __construct(
        protected ProviderHealthCheck $healthCheck
    ) {}

    /**
     * Dispatch a query to the specified LLM provider.
     *
     * Creates an LLM query record and dispatches it to the appropriate queue based on the provider.
     * Performs optional provider health checks to ensure the service is available before dispatching.
     * The query is persisted to the database and can be tracked by ID.
     *
     * @param  string  $provider  The LLM provider (claude, ollama, lmstudio, local-command)
     * @param  string  $prompt  The user's prompt/query to send to the LLM
     * @param  string|null  $model  Optional model specification for the provider
     * @param  array  $options  Additional options including user_id, conversation_id, skip_health_check, etc.
     * @return LLMQuery The created query model with ID and status
     *
     * @throws InvalidArgumentException If provider is not supported
     * @throws \Exception If provider health check fails (can be bypassed with skip_health_check option)
     */
    public function dispatch(string $provider, string $prompt, ?string $model = null, array $options = []): LLMQuery
    {
        // Check provider health (with optional bypass)
        $skipHealthCheck = $options['skip_health_check'] ?? false;

        if (! $skipHealthCheck) {
            $this->checkProviderHealth($provider, $options);
        }

        $llmQuery = LLMQuery::create([
            'user_id' => $options['user_id'] ?? null,
            'conversation_id' => $options['conversation_id'] ?? null,
            'provider' => $provider,
            'model' => $model,
            'prompt' => $prompt,
            'status' => 'pending',
            'metadata' => $options,
        ]);

        $job = $this->createJob($provider, $prompt, $model, $llmQuery->id, $options);

        dispatch($job);

        return $llmQuery;
    }

    /**
     * Dispatch a query without persisting to database.
     *
     * Dispatches an LLM query directly to the queue without creating a database record.
     * Useful for fire-and-forget operations where result tracking isn't needed.
     * Still performs provider health checks unless explicitly disabled.
     *
     * @param  string  $provider  The LLM provider (claude, ollama, lmstudio, local-command)
     * @param  string  $prompt  The user's prompt/query to send to the LLM
     * @param  string|null  $model  Optional model specification for the provider
     * @param  array  $options  Additional options including skip_health_check, etc.
     *
     * @throws InvalidArgumentException If provider is not supported
     * @throws \Exception If provider health check fails (can be bypassed with skip_health_check option)
     */
    public function dispatchOnly(string $provider, string $prompt, ?string $model = null, array $options = []): void
    {
        // Check provider health (with optional bypass)
        $skipHealthCheck = $options['skip_health_check'] ?? false;

        if (! $skipHealthCheck) {
            $this->checkProviderHealth($provider, $options);
        }

        $job = $this->createJob($provider, $prompt, $model, null, $options);
        dispatch($job);
    }

    /**
     * Create the appropriate job class based on provider.
     *
     * Factory method that instantiates the correct job class for the given provider.
     * Each provider has its own job implementation handling provider-specific logic.
     *
     * @param  string  $provider  The LLM provider identifier
     * @param  string  $prompt  The user's prompt
     * @param  string|null  $model  The optional model specification
     * @param  int|null  $llmQueryId  The LLMQuery ID if tracking in database, null for dispatch-only
     * @param  array  $options  Additional options for the job
     * @return \Illuminate\Bus\Queueable The created job instance
     *
     * @throws InvalidArgumentException If provider is not supported
     */
    protected function createJob(string $provider, string $prompt, ?string $model, ?int $llmQueryId, array $options)
    {
        return match ($provider) {
            'claude' => new ClaudeQueryJob($prompt, $model, $llmQueryId, $options),
            'ollama' => new OllamaQueryJob($prompt, $model, $llmQueryId, $options),
            'lmstudio' => new LMStudioQueryJob($prompt, $model, $llmQueryId, $options),
            'local-command' => new LocalCommandJob($prompt, $model, $llmQueryId, $options),
            default => throw new InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }

    /**
     * Get list of available providers.
     *
     * Returns a structured array of all available LLM providers with their metadata.
     * Each provider includes name, description, queue information, and supported models.
     *
     * @return array Associative array of providers with configuration details
     */
    public function getProviders(): array
    {
        return [
            'claude' => [
                'name' => 'Claude API',
                'description' => 'Anthropic Claude API (requires API key)',
                'queue' => 'llm-claude',
                'models' => ['claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229'],
            ],
            'ollama' => [
                'name' => 'Ollama',
                'description' => 'Local Ollama instance',
                'queue' => 'llm-ollama',
                'models' => ['llama3.2', 'llama3.1', 'mistral', 'codellama'],
            ],
            'lmstudio' => [
                'name' => 'LM Studio',
                'description' => 'Local LM Studio server',
                'queue' => 'llm-local',
                'models' => ['local-model'],
            ],
            'local-command' => [
                'name' => 'Local Command (Claude Code, etc.)',
                'description' => 'Execute local commands with your shell environment - Use "claude" for Claude Code CLI (requires authentication)',
                'queue' => 'llm-local',
                'models' => [],
            ],
        ];
    }

    /**
     * Get list of available providers with health status.
     *
     * Returns providers array enriched with real-time health check information.
     * Health status is cached to reduce overhead on repeated calls.
     *
     * @return array Providers with health status included
     */
    public function getProvidersWithHealth(): array
    {
        $providers = $this->getProviders();
        $healthStatuses = $this->healthCheck->checkAllCached();

        foreach ($providers as $key => &$provider) {
            $provider['health'] = $healthStatuses[$key] ?? [
                'status' => 'unknown',
                'message' => 'Health check not available',
            ];
        }

        return $providers;
    }

    /**
     * Check provider health and handle graceful degradation.
     *
     * Validates that a provider is healthy before dispatching jobs to it.
     * Supports degraded providers with warnings and can suggest fallback options.
     *
     * @param  string  $provider  The provider to check
     * @param  array  $options  Dispatch options including allow_unhealthy and fallback_provider
     *
     * @throws \RuntimeException If provider is unhealthy and no fallback is configured
     */
    protected function checkProviderHealth(string $provider, array $options): void
    {
        $health = $this->healthCheck->checkCached($provider);

        // Allow degraded providers (with warning)
        if ($health['status'] === 'degraded') {
            Log::warning("Provider {$provider} is degraded but allowing dispatch", [
                'health' => $health,
                'options' => $options,
            ]);

            return;
        }

        // Block unhealthy providers (with fallback option)
        if ($health['status'] === 'unhealthy') {
            $allowUnhealthy = $options['allow_unhealthy'] ?? false;
            $fallbackProvider = $options['fallback_provider'] ?? null;

            if ($allowUnhealthy) {
                Log::warning("Provider {$provider} is unhealthy but dispatch allowed by option", [
                    'health' => $health,
                ]);

                return;
            }

            if ($fallbackProvider && $this->healthCheck->isHealthy($fallbackProvider)) {
                Log::info("Provider {$provider} is unhealthy, suggesting fallback to {$fallbackProvider}", [
                    'primary_health' => $health,
                ]);

                throw new \RuntimeException(
                    "Provider '{$provider}' is currently unavailable. ".
                    "Suggested fallback: '{$fallbackProvider}'. ".
                    "Reason: {$health['message']}"
                );
            }

            throw new \RuntimeException(
                "Provider '{$provider}' is currently unavailable: {$health['message']}. ".
                'Please check the provider health status at /api/providers/health'
            );
        }
    }

    /**
     * Find a healthy fallback provider.
     *
     * Searches through a list of providers to find the first healthy one.
     * Useful for automatic failover scenarios.
     *
     * @param  array|null  $preferredProviders  List of providers to check (null = all providers)
     * @return string|null Provider name if found, null if none are healthy
     */
    public function findHealthyProvider(?array $preferredProviders = null): ?string
    {
        $providers = $preferredProviders ?? array_keys($this->getProviders());

        foreach ($providers as $provider) {
            if ($this->healthCheck->isHealthy($provider)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Dispatch with automatic fallback to healthy provider.
     *
     * Attempts to dispatch to the primary provider, and if it fails health checks,
     * automatically tries fallback providers until a healthy one is found.
     *
     * @param  string  $provider  Primary provider to try
     * @param  string  $prompt  The user's prompt
     * @param  string|null  $model  Optional model specification
     * @param  array  $options  Dispatch options
     * @param  array|null  $fallbackProviders  List of providers to try if primary fails
     * @return LLMQuery The created query model
     *
     * @throws \RuntimeException If no healthy providers are available
     */
    public function dispatchWithFallback(
        string $provider,
        string $prompt,
        ?string $model = null,
        array $options = [],
        ?array $fallbackProviders = null
    ): LLMQuery {
        try {
            return $this->dispatch($provider, $prompt, $model, $options);
        } catch (\RuntimeException $e) {
            // Try fallback providers
            $fallbackProviders = $fallbackProviders ?? ['claude', 'ollama', 'lmstudio', 'local-command'];
            $fallbackProviders = array_diff($fallbackProviders, [$provider]); // Remove failed provider

            $healthyProvider = $this->findHealthyProvider($fallbackProviders);

            if ($healthyProvider) {
                Log::info("Falling back from {$provider} to {$healthyProvider}", [
                    'original_error' => $e->getMessage(),
                ]);

                $options['fallback_from'] = $provider;
                $options['fallback_reason'] = $e->getMessage();

                return $this->dispatch($healthyProvider, $prompt, $model, $options);
            }

            // No healthy provider found
            throw new \RuntimeException(
                "Provider '{$provider}' is unavailable and no healthy fallback providers found. ".
                'Original error: '.$e->getMessage()
            );
        }
    }
}
