<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnvironmentValidator
{
    /**
     * Validation errors collected during environment validation.
     */
    protected array $errors = [];

    /**
     * Validation warnings collected during environment validation.
     */
    protected array $warnings = [];

    /**
     * Timeout for URL accessibility checks in seconds.
     */
    protected int $timeout = 5;

    /**
     * Validate all required environment variables.
     *
     * Performs comprehensive validation of all environment variables required for the application.
     * Checks for existence, proper formatting, and accessibility of external services.
     * Returns true if all critical validations pass, false otherwise.
     *
     * @param  bool  $failFast Whether to throw exception on critical errors in production
     * @return bool True if validation passes, false if there are critical errors
     *
     * @throws \RuntimeException If critical validation fails and failFast is true
     */
    public function validate(bool $failFast = true): bool
    {
        $this->errors = [];
        $this->warnings = [];

        // Validate core Laravel environment variables
        $this->validateCoreEnvironment();

        // Validate LLM provider configurations
        $this->validateLLMProviders();

        // Validate Reverb/Broadcasting configuration
        $this->validateReverbConfiguration();

        // Validate Queue/Redis configuration
        $this->validateQueueConfiguration();

        // Log results
        $this->logValidationResults();

        // Fail fast in production if there are critical errors
        if ($failFast && app()->environment('production') && !empty($this->errors)) {
            $errorMessage = "Critical environment validation failed:\n" . implode("\n", $this->errors);
            throw new \RuntimeException($errorMessage);
        }

        return empty($this->errors);
    }

    /**
     * Validate core Laravel environment variables.
     *
     * Checks APP_KEY, APP_ENV, APP_DEBUG, APP_URL and other critical Laravel configuration.
     * APP_KEY must be present and properly formatted (base64: prefix).
     * APP_ENV must be a valid environment name.
     *
     * @return void
     */
    protected function validateCoreEnvironment(): void
    {
        // APP_KEY - Critical
        if (empty(config('app.key'))) {
            $this->addError('APP_KEY is not set. Run: php artisan key:generate');
        } elseif (!str_starts_with(config('app.key'), 'base64:')) {
            $this->addWarning('APP_KEY should start with "base64:" - regenerate with: php artisan key:generate');
        }

        // APP_ENV - Critical
        $appEnv = config('app.env');
        if (empty($appEnv)) {
            $this->addError('APP_ENV is not set. Set to: local, production, staging, etc.');
        } elseif (!in_array($appEnv, ['local', 'development', 'staging', 'production', 'testing'])) {
            $this->addWarning("APP_ENV is set to '{$appEnv}' which is not a standard Laravel environment");
        }

        // APP_DEBUG - Warning
        if (config('app.env') === 'production' && config('app.debug') === true) {
            $this->addWarning('APP_DEBUG is enabled in production - this is a security risk');
        }

        // APP_URL - Warning
        if (empty(config('app.url'))) {
            $this->addWarning('APP_URL is not set - may cause issues with URL generation');
        }

        // APP_NAME - Optional
        if (empty(config('app.name'))) {
            $this->addWarning('APP_NAME is not set - using default "Laravel"');
        }
    }

    /**
     * Validate LLM provider configurations.
     *
     * Checks configuration for Claude (Anthropic), Ollama, LM Studio, and local command providers.
     * Validates API keys, base URLs, and accessibility where applicable.
     *
     * @return void
     */
    protected function validateLLMProviders(): void
    {
        // Get enabled providers
        $providers = config('llm.providers', []);

        // Claude / Anthropic
        if (($providers['claude']['enabled'] ?? false)) {
            $this->validateClaudeProvider();
        }

        // Ollama
        if (($providers['ollama']['enabled'] ?? false)) {
            $this->validateOllamaProvider();
        }

        // LM Studio
        if (($providers['lmstudio']['enabled'] ?? false)) {
            $this->validateLMStudioProvider();
        }

        // Local Command
        if (($providers['local-command']['enabled'] ?? false)) {
            $this->validateLocalCommandProvider();
        }

        // Check that at least one provider is enabled
        $anyEnabled = collect($providers)->contains('enabled', true);
        if (!$anyEnabled) {
            $this->addWarning('No LLM providers are enabled - application may not function properly');
        }
    }

    /**
     * Validate Claude/Anthropic API configuration.
     *
     * Checks for ANTHROPIC_API_KEY existence and proper formatting.
     * Validates that the API key follows the expected sk-ant-* format.
     *
     * @return void
     */
    protected function validateClaudeProvider(): void
    {
        $apiKey = config('llm.providers.claude.api_key');

        if (empty($apiKey)) {
            $this->addError('ANTHROPIC_API_KEY is not set but Claude provider is enabled');
            return;
        }

        // Validate API key format (should start with sk-ant-)
        if (!str_starts_with($apiKey, 'sk-ant-')) {
            $this->addWarning('ANTHROPIC_API_KEY does not start with "sk-ant-" - may be invalid');
        }

        // Validate minimum length
        if (strlen($apiKey) < 20) {
            $this->addWarning('ANTHROPIC_API_KEY appears too short - may be invalid');
        }

        // Check model configuration
        $model = config('llm.providers.claude.default_model');
        if (empty($model)) {
            $this->addWarning('CLAUDE_DEFAULT_MODEL is not set - will use default');
        }
    }

    /**
     * Validate Ollama provider configuration.
     *
     * Checks for OLLAMA_BASE_URL existence, proper formatting, and accessibility.
     * Attempts to connect to the Ollama service to verify it's running.
     *
     * @return void
     */
    protected function validateOllamaProvider(): void
    {
        $baseUrl = config('llm.providers.ollama.base_url');

        if (empty($baseUrl)) {
            $this->addError('OLLAMA_BASE_URL is not set but Ollama provider is enabled');
            return;
        }

        // Validate URL format
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->addError("OLLAMA_BASE_URL '{$baseUrl}' is not a valid URL");
            return;
        }

        // Check URL accessibility (non-blocking)
        $this->checkUrlAccessibility('Ollama', $baseUrl . '/api/tags');

        // Check model configuration
        $model = config('llm.providers.ollama.default_model');
        if (empty($model)) {
            $this->addWarning('OLLAMA_DEFAULT_MODEL is not set - will use default');
        }
    }

    /**
     * Validate LM Studio provider configuration.
     *
     * Checks for LMSTUDIO_BASE_URL existence, proper formatting, and accessibility.
     * Attempts to connect to the LM Studio service to verify it's running.
     *
     * @return void
     */
    protected function validateLMStudioProvider(): void
    {
        $baseUrl = config('llm.providers.lmstudio.base_url');

        if (empty($baseUrl)) {
            $this->addError('LMSTUDIO_BASE_URL is not set but LM Studio provider is enabled');
            return;
        }

        // Validate URL format
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->addError("LMSTUDIO_BASE_URL '{$baseUrl}' is not a valid URL");
            return;
        }

        // Check URL accessibility (non-blocking)
        $this->checkUrlAccessibility('LM Studio', $baseUrl . '/models');
    }

    /**
     * Validate local command provider configuration.
     *
     * Checks for LOCAL_COMMAND existence and validates that it's a safe command.
     * Warns about potentially dangerous commands or missing configuration.
     *
     * @return void
     */
    protected function validateLocalCommandProvider(): void
    {
        $command = config('llm.providers.local-command.command');

        if (empty($command)) {
            $this->addWarning('LOCAL_COMMAND is not set but local-command provider is enabled');
            return;
        }

        // Check for dangerous commands
        $dangerousPatterns = ['rm ', 'dd ', 'mkfs', ':(){:|:&};:', 'sudo ', 'chmod 777'];
        foreach ($dangerousPatterns as $pattern) {
            if (str_contains($command, $pattern)) {
                $this->addError("LOCAL_COMMAND contains potentially dangerous pattern: '{$pattern}'");
            }
        }
    }

    /**
     * Validate Reverb/Broadcasting configuration.
     *
     * Checks for REVERB_APP_KEY, REVERB_APP_ID, REVERB_APP_SECRET and other
     * required Reverb configuration when broadcasting is enabled.
     *
     * @return void
     */
    protected function validateReverbConfiguration(): void
    {
        $broadcastDriver = config('broadcasting.default');

        // Only validate if Reverb is being used
        if ($broadcastDriver !== 'reverb') {
            return;
        }

        // REVERB_APP_KEY
        if (empty(config('reverb.apps.apps.0.key'))) {
            $this->addError('REVERB_APP_KEY is not set but Reverb is enabled');
        }

        // REVERB_APP_SECRET
        if (empty(config('reverb.apps.apps.0.secret'))) {
            $this->addError('REVERB_APP_SECRET is not set but Reverb is enabled');
        }

        // REVERB_APP_ID
        if (empty(config('reverb.apps.apps.0.app_id'))) {
            $this->addError('REVERB_APP_ID is not set but Reverb is enabled');
        }

        // REVERB_HOST
        if (empty(config('reverb.apps.apps.0.options.host'))) {
            $this->addWarning('REVERB_HOST is not set - using default');
        }

        // REVERB_PORT
        if (empty(config('reverb.apps.apps.0.options.port'))) {
            $this->addWarning('REVERB_PORT is not set - using default (443)');
        }

        // REVERB_SCHEME
        $scheme = config('reverb.apps.apps.0.options.scheme', 'https');
        if ($scheme !== 'https' && config('app.env') === 'production') {
            $this->addWarning('REVERB_SCHEME is not set to "https" in production - security risk');
        }
    }

    /**
     * Validate Queue/Redis configuration.
     *
     * Checks queue connection settings and Redis configuration when applicable.
     * Validates that required queue tables exist for database driver.
     *
     * @return void
     */
    protected function validateQueueConfiguration(): void
    {
        $queueConnection = config('queue.default');

        // For database queue, check connection
        if ($queueConnection === 'database') {
            try {
                // Check if database is accessible
                \DB::connection()->getPdo();
            } catch (\Exception $e) {
                $this->addError('Queue connection is "database" but database is not accessible: ' . $e->getMessage());
            }
        }

        // For Redis queue, check Redis configuration
        if (in_array($queueConnection, ['redis', 'horizon'])) {
            $redisHost = config('database.redis.default.host');
            $redisPort = config('database.redis.default.port');

            if (empty($redisHost)) {
                $this->addError('REDIS_HOST is not set but Redis queue is enabled');
            }

            if (empty($redisPort)) {
                $this->addWarning('REDIS_PORT is not set - using default (6379)');
            }

            // Check Redis password in production
            if (config('app.env') === 'production' && empty(config('database.redis.default.password'))) {
                $this->addWarning('REDIS_PASSWORD is not set in production - security risk');
            }
        }

        // Check Horizon configuration if using horizon connection
        if ($queueConnection === 'horizon' && empty(config('horizon.path'))) {
            $this->addWarning('Horizon configuration may be incomplete - run: php artisan horizon:install');
        }
    }

    /**
     * Check if a URL is accessible.
     *
     * Attempts a HEAD request to the specified URL with a timeout.
     * Adds warnings if the URL is not accessible but doesn't fail validation.
     * This is a non-blocking check suitable for startup validation.
     *
     * @param  string  $serviceName Human-readable service name for error messages
     * @param  string  $url The URL to check
     * @return void
     */
    protected function checkUrlAccessibility(string $serviceName, string $url): void
    {
        try {
            $response = Http::timeout($this->timeout)->head($url);

            if ($response->failed() && $response->status() !== 404) {
                $this->addWarning("{$serviceName} URL '{$url}' returned status {$response->status()}");
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->addWarning("{$serviceName} at '{$url}' is not accessible - service may not be running");
        } catch (\Exception $e) {
            $this->addWarning("{$serviceName} accessibility check failed: {$e->getMessage()}");
        }
    }

    /**
     * Add a critical error to the validation results.
     *
     * Critical errors will cause application startup to fail in production
     * when failFast is enabled. Use for missing required configuration.
     *
     * @param  string  $message The error message
     * @return void
     */
    protected function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Add a warning to the validation results.
     *
     * Warnings are logged but don't prevent application startup.
     * Use for missing optional configuration or potential issues.
     *
     * @param  string  $message The warning message
     * @return void
     */
    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Log validation results.
     *
     * Logs all errors and warnings to the application log.
     * Errors are logged at 'error' level, warnings at 'warning' level.
     *
     * @return void
     */
    protected function logValidationResults(): void
    {
        if (!empty($this->errors)) {
            Log::error('Environment validation failed', [
                'errors' => $this->errors,
            ]);

            foreach ($this->errors as $error) {
                Log::error("[ENV] {$error}");
            }
        }

        if (!empty($this->warnings)) {
            Log::warning('Environment validation warnings', [
                'warnings' => $this->warnings,
            ]);

            foreach ($this->warnings as $warning) {
                Log::warning("[ENV] {$warning}");
            }
        }

        if (empty($this->errors) && empty($this->warnings)) {
            Log::info('Environment validation passed');
        }
    }

    /**
     * Get all validation errors.
     *
     * Returns array of critical error messages collected during validation.
     * Empty array indicates no critical errors.
     *
     * @return array Array of error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all validation warnings.
     *
     * Returns array of warning messages collected during validation.
     * Empty array indicates no warnings.
     *
     * @return array Array of warning messages
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if validation has errors.
     *
     * Returns true if any critical errors were found during validation.
     *
     * @return bool True if there are errors, false otherwise
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if validation has warnings.
     *
     * Returns true if any warnings were found during validation.
     *
     * @return bool True if there are warnings, false otherwise
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get validation summary.
     *
     * Returns a comprehensive summary of validation results including
     * counts of errors and warnings, and the full list of messages.
     *
     * @return array Validation summary with errors, warnings, and status
     */
    public function getSummary(): array
    {
        return [
            'passed' => empty($this->errors),
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Set timeout for URL accessibility checks.
     *
     * Configures how long to wait when checking if provider URLs are accessible.
     * Returns self for method chaining.
     *
     * @param  int  $timeout The timeout in seconds
     * @return $this For method chaining
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Get timeout for URL accessibility checks.
     *
     * Returns the configured timeout in seconds.
     *
     * @return int The timeout in seconds
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
