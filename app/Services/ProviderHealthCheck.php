<?php

namespace App\Services;

use Anthropic\Laravel\Facades\Anthropic;
use CloudStudio\Ollama\Facades\Ollama;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class ProviderHealthCheck
{
    /**
     * Cache TTL for health checks in seconds.
     */
    protected int $cacheTtl = 60;

    /**
     * Timeout for health check requests in seconds.
     */
    protected int $timeout = 5;

    /**
     * Check health of all providers.
     *
     * Performs health checks on all configured LLM providers (Claude, Ollama, LM Studio, and
     * Local Command execution). Returns an associative array with health status for each provider.
     * Each provider's health is checked without caching. For cached results, use checkAllCached().
     *
     * @return array Associative array with provider names as keys and health check results as values
     */
    public function checkAll(): array
    {
        return [
            'claude' => $this->checkClaude(),
            'ollama' => $this->checkOllama(),
            'lmstudio' => $this->checkLMStudio(),
            'local-command' => $this->checkLocalCommand(),
        ];
    }

    /**
     * Check health of a specific provider.
     *
     * Performs a health check on a single LLM provider without caching. Supports claude, ollama,
     * lmstudio, and local-command providers. Returns an array with status, message, timestamp,
     * and details about the provider's health.
     *
     * @param  string  $provider  The provider identifier (claude, ollama, lmstudio, local-command)
     * @return array Health check result with keys: status, message, timestamp, details
     */
    public function check(string $provider): array
    {
        return match ($provider) {
            'claude' => $this->checkClaude(),
            'ollama' => $this->checkOllama(),
            'lmstudio' => $this->checkLMStudio(),
            'local-command' => $this->checkLocalCommand(),
            default => $this->healthCheckError('Unknown provider', [
                'provider' => $provider,
                'available_providers' => ['claude', 'ollama', 'lmstudio', 'local-command'],
            ]),
        };
    }

    /**
     * Check health with caching.
     *
     * Performs a cached health check on a specific provider. Results are cached for the duration
     * specified by cacheTtl (default 60 seconds). Reduces overhead when health checks are performed
     * frequently. Use clearCache() to invalidate cached results.
     *
     * @param  string  $provider  The provider identifier to check
     * @return array Cached health check result with keys: status, message, timestamp, details
     */
    public function checkCached(string $provider): array
    {
        $cacheKey = "provider_health:{$provider}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($provider) {
            return $this->check($provider);
        });
    }

    /**
     * Check health of all providers with caching.
     *
     * Performs cached health checks on all configured providers. Each provider's health is cached
     * independently according to the configured cacheTtl. Use this when checking multiple providers
     * to minimize performance impact on the application.
     *
     * @return array Associative array with provider names as keys and cached health check results as values
     */
    public function checkAllCached(): array
    {
        return [
            'claude' => $this->checkCached('claude'),
            'ollama' => $this->checkCached('ollama'),
            'lmstudio' => $this->checkCached('lmstudio'),
            'local-command' => $this->checkCached('local-command'),
        ];
    }

    /**
     * Check if a provider is healthy.
     *
     * Performs a cached health check and returns a boolean indicating if the provider is in a
     * healthy state. Uses cached results to minimize performance overhead. Only returns true if
     * the provider's status is exactly 'healthy' (degraded/warning status returns false).
     *
     * @param  string  $provider  The provider identifier to check
     * @return bool True if the provider is healthy, false otherwise
     */
    public function isHealthy(string $provider): bool
    {
        $health = $this->checkCached($provider);

        return $health['status'] === 'healthy';
    }

    /**
     * Clear health check cache for a provider.
     *
     * Invalidates cached health check results. If a provider is specified, only that provider's
     * cache is cleared. If no provider is specified, all provider health check caches are cleared.
     * Useful when you want fresh health status information immediately.
     *
     * @param  string|null  $provider  Optional provider identifier to clear, or null to clear all providers
     */
    public function clearCache(?string $provider = null): void
    {
        if ($provider) {
            Cache::forget("provider_health:{$provider}");
        } else {
            $providers = ['claude', 'ollama', 'lmstudio', 'local-command'];
            foreach ($providers as $p) {
                Cache::forget("provider_health:{$p}");
            }
        }
    }

    /**
     * Check Claude API health.
     *
     * Verifies that the Claude API is accessible and properly configured with valid credentials.
     * Checks for ANTHROPIC_API_KEY configuration and attempts a minimal API request to verify
     * connectivity. Handles authentication errors, rate limiting, and network failures gracefully.
     *
     * @return array Health check result with status, message, timestamp, and details
     */
    protected function checkClaude(): array
    {
        try {
            $apiKey = config('anthropic.api_key');

            if (empty($apiKey)) {
                return $this->healthCheckWarning('API key not configured', [
                    'message' => 'Set ANTHROPIC_API_KEY in .env file',
                ]);
            }

            // Try a minimal API request to check connectivity
            $result = Anthropic::messages()->create([
                'model' => 'claude-3-5-haiku-20241022',
                'max_tokens' => 10,
                'messages' => [
                    ['role' => 'user', 'content' => 'test'],
                ],
            ]);

            return $this->healthCheckSuccess('Claude API is accessible', [
                'models_available' => true,
                'api_version' => '2023-06-01',
            ]);
        } catch (\Anthropic\Exceptions\UnprocessableEntityException $e) {
            // This is actually a successful connection - just invalid request format
            return $this->healthCheckSuccess('Claude API is accessible', [
                'models_available' => true,
                'note' => 'API responded (validation error is expected)',
            ]);
        } catch (\Anthropic\Exceptions\ErrorException $e) {
            if (str_contains($e->getMessage(), 'authentication') || str_contains($e->getMessage(), 'api_key')) {
                return $this->healthCheckError('Invalid API key', [
                    'message' => 'Check your ANTHROPIC_API_KEY',
                ]);
            }

            if (str_contains($e->getMessage(), 'rate_limit')) {
                return $this->healthCheckWarning('Rate limited', [
                    'message' => 'Too many requests, but service is available',
                ]);
            }

            return $this->healthCheckError('API error: '.$e->getMessage());
        } catch (\Exception $e) {
            return $this->healthCheckError('Connection failed', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);
        }
    }

    /**
     * Check Ollama health.
     *
     * Verifies that the Ollama service is running and responsive at the configured base URL.
     * Checks for available models and provides helpful guidance if models are missing or the
     * service is unreachable. Returns detailed information about the Ollama instance.
     *
     * @return array Health check result with status, message, timestamp, and details including model count
     */
    protected function checkOllama(): array
    {
        try {
            $baseUrl = config('ollama.base_url', 'http://127.0.0.1:11434');

            // Check if Ollama is running
            $response = Http::timeout($this->timeout)
                ->get("{$baseUrl}/api/tags");

            if ($response->failed()) {
                return $this->healthCheckError('Ollama not responding', [
                    'url' => $baseUrl,
                    'status_code' => $response->status(),
                ]);
            }

            $data = $response->json();
            $models = $data['models'] ?? [];

            if (empty($models)) {
                return $this->healthCheckWarning('Ollama running but no models found', [
                    'url' => $baseUrl,
                    'message' => 'Run "ollama pull llama3.2" to install a model',
                ]);
            }

            return $this->healthCheckSuccess('Ollama is running', [
                'url' => $baseUrl,
                'models_count' => count($models),
                'models' => array_map(fn ($m) => $m['name'] ?? 'unknown', $models),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return $this->healthCheckError('Cannot connect to Ollama', [
                'url' => config('ollama.base_url', 'http://127.0.0.1:11434'),
                'message' => 'Is Ollama running? Start it with "ollama serve"',
            ]);
        } catch (\Exception $e) {
            return $this->healthCheckError('Ollama check failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check LM Studio health.
     *
     * Verifies that the LM Studio local server is running and accessible at the configured base URL.
     * Checks for loaded models and provides guidance if none are available or the server is unreachable.
     * Helpful for debugging LM Studio connection issues.
     *
     * @return array Health check result with status, message, timestamp, and details including model count
     */
    protected function checkLMStudio(): array
    {
        try {
            $baseUrl = config('lmstudio.base_url', 'http://127.0.0.1:1234/v1');

            // Check if LM Studio server is running
            $response = Http::timeout($this->timeout)
                ->get("{$baseUrl}/models");

            if ($response->failed()) {
                return $this->healthCheckError('LM Studio not responding', [
                    'url' => $baseUrl,
                    'status_code' => $response->status(),
                ]);
            }

            $data = $response->json();
            $models = $data['data'] ?? [];

            if (empty($models)) {
                return $this->healthCheckWarning('LM Studio running but no model loaded', [
                    'url' => $baseUrl,
                    'message' => 'Load a model in LM Studio',
                ]);
            }

            return $this->healthCheckSuccess('LM Studio is running', [
                'url' => $baseUrl,
                'models_count' => count($models),
                'models' => array_map(fn ($m) => $m['id'] ?? 'unknown', $models),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return $this->healthCheckError('Cannot connect to LM Studio', [
                'url' => config('lmstudio.base_url', 'http://127.0.0.1:1234/v1'),
                'message' => 'Is LM Studio running with local server enabled?',
            ]);
        } catch (\Exception $e) {
            return $this->healthCheckError('LM Studio check failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check local command execution health.
     *
     * Verifies that local shell command execution is available and functional. Checks for a valid shell,
     * tests basic command execution, and detects available CLI tools (claude, ollama, llm, aider).
     * Provides guidance on missing tools and shell configuration issues.
     *
     * @return array Health check result with status, message, timestamp, and details including available tools
     */
    protected function checkLocalCommand(): array
    {
        try {
            // Check if shell is available
            $shell = getenv('SHELL') ?: '/bin/zsh';
            $shellReal = realpath($shell);

            if (! $shellReal || ! file_exists($shellReal)) {
                return $this->healthCheckError('Shell not found', [
                    'shell' => $shell,
                    'message' => 'Cannot execute local commands',
                ]);
            }

            // Test basic command execution
            $result = Process::timeout($this->timeout)
                ->run('echo "test"');

            if ($result->failed()) {
                return $this->healthCheckError('Cannot execute commands', [
                    'exit_code' => $result->exitCode(),
                ]);
            }

            // Check for common CLI tools
            $tools = $this->checkAvailableTools();

            if (empty($tools)) {
                return $this->healthCheckWarning('Shell works but no CLI tools found', [
                    'shell' => $shellReal,
                    'message' => 'Install claude, ollama, or other CLI tools',
                    'available_tools' => $tools,
                ]);
            }

            return $this->healthCheckSuccess('Local commands available', [
                'shell' => $shellReal,
                'available_tools' => $tools,
            ]);
        } catch (\Exception $e) {
            return $this->healthCheckError('Command execution check failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check which CLI tools are available.
     *
     * Detects which LLM CLI tools are installed and available in the system PATH.
     * Checks for claude, ollama, llm, and aider commands. Uses a 2-second timeout for each check
     * to prevent hanging on unavailable tools.
     *
     * @return array List of available tool names (e.g., ['claude', 'ollama', 'llm'])
     */
    protected function checkAvailableTools(): array
    {
        $tools = ['claude', 'ollama', 'llm', 'aider'];
        $available = [];

        foreach ($tools as $tool) {
            try {
                $result = Process::timeout(2)->run("which {$tool}");
                if ($result->successful() && ! empty(trim($result->output()))) {
                    $available[] = $tool;
                }
            } catch (\Exception $e) {
                // Tool not available
            }
        }

        return $available;
    }

    /**
     * Create a healthy status response.
     *
     * Creates a standardized health check response indicating a successful status.
     * Returns an array with status='healthy', message, current timestamp, and optional details.
     *
     * @param  string  $message  A user-friendly message describing the healthy status
     * @param  array  $details  Optional associative array with additional details about the provider
     * @return array Standardized health check response
     */
    protected function healthCheckSuccess(string $message, array $details = []): array
    {
        return [
            'status' => 'healthy',
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'details' => $details,
        ];
    }

    /**
     * Create a warning status response.
     *
     * Creates a standardized health check response indicating a degraded or warning status.
     * The provider may still function but has reduced capacity or missing configuration.
     * Returns status='degraded' with message, timestamp, and optional details.
     *
     * @param  string  $message  A user-friendly message describing the warning status
     * @param  array  $details  Optional associative array with additional details about the issue
     * @return array Standardized health check response with degraded status
     */
    protected function healthCheckWarning(string $message, array $details = []): array
    {
        return [
            'status' => 'degraded',
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'details' => $details,
        ];
    }

    /**
     * Create an error status response.
     *
     * Creates a standardized health check response indicating an unhealthy/error status.
     * The provider is not accessible or is not properly configured. Returns status='unhealthy'
     * with message, timestamp, and optional diagnostic details.
     *
     * @param  string  $message  A user-friendly message describing the error status
     * @param  array  $details  Optional associative array with error details and diagnostic information
     * @return array Standardized health check response with unhealthy status
     */
    protected function healthCheckError(string $message, array $details = []): array
    {
        return [
            'status' => 'unhealthy',
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'details' => $details,
        ];
    }

    /**
     * Get cache TTL in seconds.
     *
     * Returns the cache time-to-live setting used for storing health check results.
     * Default is 60 seconds.
     *
     * @return int The cache TTL in seconds
     */
    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    /**
     * Set cache TTL in seconds.
     *
     * Configures how long health check results are cached. Allows customizing the cache duration
     * to balance between performance and freshness of health status. Returns self for method chaining.
     *
     * @param  int  $ttl  The cache TTL in seconds
     * @return $this For method chaining
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Get timeout in seconds.
     *
     * Returns the timeout setting used for provider health check requests.
     * Default is 5 seconds.
     *
     * @return int The timeout in seconds
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set timeout in seconds.
     *
     * Configures how long to wait for responses during health checks. Useful for slow network
     * conditions or heavily loaded systems. Returns self for method chaining.
     *
     * @param  int  $timeout  The timeout in seconds
     * @return $this For method chaining
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }
}
