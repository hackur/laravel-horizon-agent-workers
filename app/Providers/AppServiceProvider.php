<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Track whether environment validation has been run in this process.
     */
    protected static bool $validationRun = false;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Validate environment variables on startup (only once per process)
        $this->validateEnvironment();

        // Register job event listeners
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Queue\Events\JobProcessed::class,
            [\App\Listeners\UpdateLLMQueryStatus::class, 'handleJobProcessed']
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Queue\Events\JobFailed::class,
            [\App\Listeners\UpdateLLMQueryStatus::class, 'handleJobFailed']
        );

        // Configure API rate limiting
        $this->configureRateLimiting();
    }

    /**
     * Validate environment configuration on application startup.
     *
     * Performs comprehensive validation of environment variables including:
     * - Core Laravel configuration (APP_KEY, APP_ENV, APP_DEBUG)
     * - LLM provider configurations (API keys, URLs)
     * - Reverb/Broadcasting settings
     * - Queue/Redis configuration
     *
     * In production, critical errors will cause startup to fail.
     * In development, errors and warnings are logged only.
     *
     * Validation runs only ONCE per process to prevent spam in queue workers.
     */
    protected function validateEnvironment(): void
    {
        // Skip if validation has already run in this process
        if (static::$validationRun) {
            return;
        }

        // In testing environment, skip validation to avoid issues with test setup
        if (app()->environment('testing')) {
            return;
        }

        // Skip validation for queue workers and background processes to prevent log spam
        if ($this->isBackgroundProcess()) {
            return;
        }

        // Mark validation as run for this process
        static::$validationRun = true;

        $validator = new \App\Services\EnvironmentValidator;

        // Validate environment (fail fast in production)
        $validator->validate(failFast: app()->environment('production'));

        // In development, output validation summary to console/log
        if (app()->environment('local', 'development')) {
            $summary = $validator->getSummary();

            if ($summary['error_count'] > 0 || $summary['warning_count'] > 0) {
                \Illuminate\Support\Facades\Log::channel('single')->info(
                    "Environment validation completed: {$summary['error_count']} errors, {$summary['warning_count']} warnings"
                );
            }
        }
    }

    /**
     * Determine if the current process is a background worker or monitoring process.
     *
     * Background processes include:
     * - Queue workers (queue:work, queue:listen, horizon)
     * - Log watchers (pail)
     * - Schedule runners
     *
     * @return bool True if this is a background process
     */
    protected function isBackgroundProcess(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        // Check if any of these artisan commands are running
        $backgroundCommands = [
            'queue:work',
            'queue:listen',
            'horizon',
            'horizon:work',
            'horizon:supervisor',
            'pail',
            'schedule:run',
            'schedule:work',
        ];

        $currentCommand = $_SERVER['argv'][1] ?? '';

        foreach ($backgroundCommands as $command) {
            if (str_contains($currentCommand, $command)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Authenticated API rate limit (120 requests per minute)
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(60)->by($request->ip());
        });

        // Guest API rate limit (60 requests per minute)
        RateLimiter::for('api:guest', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Token management strict limit (10 requests per minute)
        RateLimiter::for('api:tokens', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()->id ?? $request->ip());
        });
    }
}
