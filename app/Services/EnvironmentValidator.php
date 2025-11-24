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
     * @param  bool  $failFast  Whether to throw exception on critical errors in production
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

        // Validate database configuration
        $this->validateDatabaseConfiguration();

        // Validate cache configuration
        $this->validateCacheConfiguration();

        // Validate session configuration
        $this->validateSessionConfiguration();

        // Validate LLM provider configurations
        $this->validateLLMProviders();

        // Validate Reverb/Broadcasting configuration
        $this->validateReverbConfiguration();

        // Validate Queue/Redis configuration
        $this->validateQueueConfiguration();

        // Validate mail configuration
        $this->validateMailConfiguration();

        // Log results
        $this->logValidationResults();

        // Fail fast in production if there are critical errors
        if ($failFast && app()->environment('production') && ! empty($this->errors)) {
            $errorMessage = "Critical environment validation failed:\n".implode("\n", $this->errors);
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
     */
    protected function validateCoreEnvironment(): void
    {
        // APP_KEY - Critical
        if (empty(config('app.key'))) {
            $this->addError('APP_KEY is not set. Run: php artisan key:generate');
        } elseif (! str_starts_with(config('app.key'), 'base64:')) {
            $this->addWarning('APP_KEY should start with "base64:" - regenerate with: php artisan key:generate');
        }

        // APP_ENV - Critical
        $appEnv = config('app.env');
        if (empty($appEnv)) {
            $this->addError('APP_ENV is not set. Set to: local, production, staging, etc.');
        } elseif (! in_array($appEnv, ['local', 'development', 'staging', 'production', 'testing'])) {
            $this->addWarning("APP_ENV is set to '{$appEnv}' which is not a standard Laravel environment");
        }

        // APP_DEBUG - Warning
        if (config('app.env') === 'production' && config('app.debug') === true) {
            $this->addWarning('APP_DEBUG is enabled in production - this is a security risk');
        }

        // APP_URL - Warning
        if (empty(config('app.url'))) {
            $this->addWarning('APP_URL is not set - may cause issues with URL generation');
        } else {
            $this->validateUrl('APP_URL', config('app.url'), false);
        }

        // APP_NAME - Optional
        if (empty(config('app.name'))) {
            $this->addWarning('APP_NAME is not set - using default "Laravel"');
        }

        // Validate locale settings
        $locale = config('app.locale');
        if (empty($locale)) {
            $this->addWarning('APP_LOCALE is not set - using default');
        }
    }

    /**
     * Validate database configuration.
     *
     * Checks database connection, host, port, and credentials based on selected driver.
     * Verifies that the database is accessible.
     */
    protected function validateDatabaseConfiguration(): void
    {
        $connection = config('database.default');

        if (empty($connection)) {
            $this->addError('DB_CONNECTION is not set');

            return;
        }

        $config = config("database.connections.{$connection}");

        if (! $config) {
            $this->addError("Database connection '{$connection}' is not configured");

            return;
        }

        // SQLite validation
        if ($connection === 'sqlite') {
            $database = $config['database'] ?? null;

            if (empty($database)) {
                $this->addWarning('SQLite database path is not configured');
            } elseif ($database !== ':memory:' && ! file_exists($database)) {
                $this->addWarning("SQLite database file '{$database}' does not exist - it will be created on first use");
            }
        }

        // MySQL/PostgreSQL validation
        elseif (in_array($connection, ['mysql', 'pgsql'])) {
            if (empty($config['host'])) {
                $this->addError("DB_HOST is not set for '{$connection}' connection");
            }

            if (empty($config['database'])) {
                $this->addError("DB_DATABASE is not set for '{$connection}' connection");
            }

            if (empty($config['username'])) {
                $this->addWarning("DB_USERNAME is not set for '{$connection}' connection");
            }

            // Check if port is within valid range
            if (! empty($config['port'])) {
                $port = $config['port'];
                if (! is_numeric($port) || $port < 1 || $port > 65535) {
                    $this->addError("DB_PORT '{$port}' is not valid (must be 1-65535)");
                }
            }
        }

        // Test database accessibility
        try {
            \DB::connection($connection)->getPdo();
        } catch (\Exception $e) {
            $this->addWarning("Database connection test failed: {$e->getMessage()}");
        }
    }

    /**
     * Validate cache configuration.
     *
     * Checks cache store settings and validates required environment variables
     * for the selected cache driver.
     */
    protected function validateCacheConfiguration(): void
    {
        $store = config('cache.default');

        if (empty($store)) {
            $this->addWarning('CACHE_STORE is not set - using default');

            return;
        }

        // Memcached validation
        if ($store === 'memcached') {
            $host = config('cache.stores.memcached.servers.0.host');
            $port = config('cache.stores.memcached.servers.0.port');

            if (empty($host)) {
                $this->addWarning('MEMCACHED_HOST is not set but Memcached cache is enabled');
            }

            if (empty($port)) {
                $this->addWarning('MEMCACHED_PORT is not set - using default (11211)');
            }
        }

        // Redis validation
        elseif ($store === 'redis') {
            $host = config('cache.stores.redis.connection.host');
            $port = config('cache.stores.redis.connection.port');

            if (empty($host)) {
                $this->addWarning('REDIS_HOST is not set but Redis cache is enabled');
            }

            if (empty($port)) {
                $this->addWarning('REDIS_PORT is not set - using default (6379)');
            }
        }
    }

    /**
     * Validate session configuration.
     *
     * Checks session driver settings and validates required environment variables.
     */
    protected function validateSessionConfiguration(): void
    {
        $driver = config('session.driver');

        if (empty($driver)) {
            $this->addWarning('SESSION_DRIVER is not set - using default "file"');

            return;
        }

        // Database session validation
        if ($driver === 'database') {
            try {
                $table = config('session.table', 'sessions');
                $schema = \DB::getSchemaBuilder();

                if (! $schema->hasTable($table)) {
                    $this->addWarning("Session table '{$table}' does not exist - run migrations");
                }
            } catch (\Exception $e) {
                $this->addWarning("Cannot verify session table: {$e->getMessage()}");
            }
        }

        // Validate session timeout
        $lifetime = config('session.lifetime');
        if (! empty($lifetime) && (! is_numeric($lifetime) || $lifetime < 1)) {
            $this->addWarning('SESSION_LIFETIME should be a positive integer (minutes)');
        }

        // Validate session domain in production
        if (config('app.env') === 'production') {
            $domain = config('session.domain');
            if (! empty($domain) && ! filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
                $this->addWarning("SESSION_DOMAIN '{$domain}' is not a valid domain");
            }
        }
    }

    /**
     * Validate mail configuration.
     *
     * Checks mail driver settings and validates required environment variables
     * for the selected mail driver.
     */
    protected function validateMailConfiguration(): void
    {
        $mailer = config('mail.default');

        if (empty($mailer)) {
            $this->addWarning('MAIL_MAILER is not set - using default "log"');

            return;
        }

        $from = config('mail.from.address');
        if (empty($from)) {
            $this->addWarning('MAIL_FROM_ADDRESS is not set');
        } elseif (! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $this->addError("MAIL_FROM_ADDRESS '{$from}' is not a valid email address");
        }

        // SMTP validation
        if ($mailer === 'smtp') {
            if (empty(config('mail.mailers.smtp.host'))) {
                $this->addError('MAIL_HOST is not set but SMTP mailer is enabled');
            }

            if (empty(config('mail.mailers.smtp.port'))) {
                $this->addWarning('MAIL_PORT is not set - using default (587)');
            }

            // In production, SMTP should use encryption
            if (config('app.env') === 'production') {
                $encryption = config('mail.mailers.smtp.encryption');
                if (empty($encryption) || ! in_array($encryption, ['tls', 'ssl'])) {
                    $this->addWarning('MAIL_ENCRYPTION is not properly set in production - consider using tls or ssl');
                }
            }
        }
    }

    /**
     * Validate LLM provider configurations.
     *
     * Checks configuration for Claude (Anthropic), Ollama, LM Studio, and local command providers.
     * Validates API keys, base URLs, and accessibility where applicable.
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
        if (! $anyEnabled) {
            $this->addWarning('No LLM providers are enabled - application may not function properly');
        }

        // Validate budget limits if cost tracking is enabled
        if (config('llm.cost_tracking_enabled', true)) {
            $this->validateBudgetLimits();
        }
    }

    /**
     * Validate Claude/Anthropic API configuration.
     *
     * Checks for ANTHROPIC_API_KEY existence and proper formatting.
     * Validates that the API key follows the expected sk-ant-* format.
     */
    protected function validateClaudeProvider(): void
    {
        $apiKey = config('llm.providers.claude.api_key');

        if (empty($apiKey)) {
            $this->addError('ANTHROPIC_API_KEY is not set but Claude provider is enabled');

            return;
        }

        // Validate API key format (should start with sk-ant-)
        if (! str_starts_with($apiKey, 'sk-ant-')) {
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

        // Check max tokens
        $maxTokens = config('llm.providers.claude.max_tokens');
        if (! empty($maxTokens) && (! is_numeric($maxTokens) || $maxTokens < 1)) {
            $this->addWarning('CLAUDE_MAX_TOKENS should be a positive integer');
        }

        // Check temperature
        $temperature = config('llm.providers.claude.temperature');
        if (! empty($temperature)) {
            if (! is_numeric($temperature) || $temperature < 0 || $temperature > 2) {
                $this->addWarning('CLAUDE_TEMPERATURE should be between 0 and 2');
            }
        }
    }

    /**
     * Validate Ollama provider configuration.
     *
     * Checks for OLLAMA_BASE_URL existence, proper formatting, and accessibility.
     * Attempts to connect to the Ollama service to verify it's running.
     */
    protected function validateOllamaProvider(): void
    {
        $baseUrl = config('llm.providers.ollama.base_url');

        if (empty($baseUrl)) {
            $this->addError('OLLAMA_BASE_URL is not set but Ollama provider is enabled');

            return;
        }

        // Validate URL format
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->addError("OLLAMA_BASE_URL '{$baseUrl}' is not a valid URL");

            return;
        }

        // Check URL accessibility (non-blocking)
        $this->checkUrlAccessibility('Ollama', $baseUrl.'/api/tags');

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
     */
    protected function validateLMStudioProvider(): void
    {
        $baseUrl = config('llm.providers.lmstudio.base_url');

        if (empty($baseUrl)) {
            $this->addError('LMSTUDIO_BASE_URL is not set but LM Studio provider is enabled');

            return;
        }

        // Validate URL format
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->addError("LMSTUDIO_BASE_URL '{$baseUrl}' is not a valid URL");

            return;
        }

        // Check URL accessibility (non-blocking)
        $this->checkUrlAccessibility('LM Studio', $baseUrl.'/models');

        // Check model configuration
        $model = config('llm.providers.lmstudio.default_model');
        if (empty($model)) {
            $this->addWarning('LMSTUDIO_DEFAULT_MODEL is not set - will use default or first available model');
        }
    }

    /**
     * Validate local command provider configuration.
     *
     * Checks for LOCAL_COMMAND existence and validates that it's a safe command.
     * Warns about potentially dangerous commands or missing configuration.
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

        // Warn if {prompt} placeholder is missing
        if (! str_contains($command, '{prompt}')) {
            $this->addWarning('LOCAL_COMMAND should include {prompt} placeholder for the input prompt');
        }
    }

    /**
     * Validate budget limits configuration.
     *
     * Checks LLM_BUDGET_LIMIT_USD and LLM_MONTHLY_BUDGET_LIMIT_USD if cost tracking is enabled.
     * Ensures values are valid positive numbers or null.
     */
    protected function validateBudgetLimits(): void
    {
        $budgetLimit = config('llm.budget_limit_usd');
        if ($budgetLimit !== null && (! is_numeric($budgetLimit) || $budgetLimit <= 0)) {
            $this->addWarning('LLM_BUDGET_LIMIT_USD should be a positive number or null');
        }

        $monthlyBudgetLimit = config('llm.monthly_budget_limit_usd');
        if ($monthlyBudgetLimit !== null && (! is_numeric($monthlyBudgetLimit) || $monthlyBudgetLimit <= 0)) {
            $this->addWarning('LLM_MONTHLY_BUDGET_LIMIT_USD should be a positive number or null');
        }

        // Warn if no limits are set (unless warning is suppressed)
        $suppressWarning = config('llm.suppress_budget_warning', false);
        if (! $suppressWarning && empty($budgetLimit) && empty($monthlyBudgetLimit)) {
            $this->addWarning('No budget limits set for LLM queries - consider setting limits to monitor costs');
        }
    }

    /**
     * Validate Reverb/Broadcasting configuration.
     *
     * Checks for REVERB_APP_KEY, REVERB_APP_ID, REVERB_APP_SECRET and other
     * required Reverb configuration when broadcasting is enabled.
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
        } else {
            $host = config('reverb.apps.apps.0.options.host');
            // Validate host format
            if (! $this->isValidHost($host)) {
                $this->addWarning("REVERB_HOST '{$host}' may not be a valid hostname or IP address");
            }
        }

        // REVERB_PORT
        if (empty(config('reverb.apps.apps.0.options.port'))) {
            $this->addWarning('REVERB_PORT is not set - using default (443)');
        } else {
            $port = config('reverb.apps.apps.0.options.port');
            if (! is_numeric($port) || $port < 1 || $port > 65535) {
                $this->addError("REVERB_PORT '{$port}' is not valid (must be 1-65535)");
            }
        }

        // REVERB_SCHEME
        $scheme = config('reverb.apps.apps.0.options.scheme', 'https');
        if (! in_array($scheme, ['http', 'https'])) {
            $this->addWarning("REVERB_SCHEME '{$scheme}' should be 'http' or 'https'");
        } elseif ($scheme !== 'https' && config('app.env') === 'production') {
            $this->addWarning('REVERB_SCHEME is not set to "https" in production - security risk');
        }
    }

    /**
     * Validate Queue/Redis configuration.
     *
     * Checks queue connection settings and Redis configuration when applicable.
     * Validates that required queue tables exist for database driver.
     */
    protected function validateQueueConfiguration(): void
    {
        $queueConnection = config('queue.default');

        if (empty($queueConnection)) {
            $this->addWarning('QUEUE_CONNECTION is not set - using default');

            return;
        }

        // For database queue, check connection
        if ($queueConnection === 'database') {
            try {
                // Check if database is accessible
                \DB::connection()->getPdo();

                // Check if jobs table exists
                $schema = \DB::getSchemaBuilder();
                if (! $schema->hasTable('jobs')) {
                    $this->addWarning("Queue table 'jobs' does not exist - run migrations");
                }
            } catch (\Exception $e) {
                $this->addError('Queue connection is "database" but database is not accessible: '.$e->getMessage());
            }
        }

        // For Redis queue, check Redis configuration
        elseif (in_array($queueConnection, ['redis', 'horizon'])) {
            $this->validateRedisConfiguration('queue');
        }

        // Check Horizon configuration if using horizon connection
        if ($queueConnection === 'horizon' && empty(config('horizon.path'))) {
            $this->addWarning('Horizon configuration may be incomplete - run: php artisan horizon:install');
        }
    }

    /**
     * Validate Redis configuration.
     *
     * Checks Redis host, port, and password settings for the given service.
     *
     * @param  string  $service  The service name (queue, cache, etc.)
     */
    protected function validateRedisConfiguration(string $service): void
    {
        $host = config('database.redis.default.host');
        $port = config('database.redis.default.port');

        if (empty($host)) {
            $this->addError('REDIS_HOST is not set but Redis is enabled');

            return;
        }

        if (empty($port)) {
            $this->addWarning('REDIS_PORT is not set - using default (6379)');
        } else {
            if (! is_numeric($port) || $port < 1 || $port > 65535) {
                $this->addError("REDIS_PORT '{$port}' is not valid (must be 1-65535)");

                return;
            }
        }

        // Check Redis password in production
        if (config('app.env') === 'production' && empty(config('database.redis.default.password'))) {
            $this->addWarning('REDIS_PASSWORD is not set in production - security risk');
        }

        // Attempt to connect to Redis
        try {
            $redis = \Cache::store('redis');
            $redis->get('laravel_env_validation_test');
        } catch (\Exception $e) {
            $this->addWarning("Redis connection test failed: {$e->getMessage()}");
        }
    }

    /**
     * Validate a URL format and optionally check accessibility.
     *
     * @param  string  $name  The environment variable name
     * @param  string  $url  The URL to validate
     * @param  bool  $checkAccessibility  Whether to check if URL is accessible
     * @return bool True if URL is valid, false otherwise
     */
    protected function validateUrl(string $name, string $url, bool $checkAccessibility = true): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addError("{$name} '{$url}' is not a valid URL");

            return false;
        }

        if ($checkAccessibility) {
            $this->checkUrlAccessibility($name, $url);
        }

        return true;
    }

    /**
     * Check if a string is a valid hostname or IP address.
     *
     * @param  string  $host  The host to validate
     * @return bool True if valid hostname or IP, false otherwise
     */
    protected function isValidHost(string $host): bool
    {
        // Check if it's a valid IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Check if it's a valid hostname/domain
        if (filter_var($host, FILTER_VALIDATE_DOMAIN)) {
            return true;
        }

        // Allow localhost and other common hostnames
        if (in_array($host, ['localhost', 'localhost.localdomain'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if a URL is accessible.
     *
     * Attempts a HEAD request to the specified URL with a timeout.
     * Adds warnings if the URL is not accessible but doesn't fail validation.
     * This is a non-blocking check suitable for startup validation.
     *
     * @param  string  $serviceName  Human-readable service name for error messages
     * @param  string  $url  The URL to check
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
     * @param  string  $message  The error message
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
     * @param  string  $message  The warning message
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
     */
    protected function logValidationResults(): void
    {
        if (! empty($this->errors)) {
            Log::error('Environment validation failed', [
                'errors' => $this->errors,
            ]);

            foreach ($this->errors as $error) {
                Log::error("[ENV] {$error}");
            }
        }

        if (! empty($this->warnings)) {
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
        return ! empty($this->errors);
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
        return ! empty($this->warnings);
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
     * @param  int  $timeout  The timeout in seconds
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
