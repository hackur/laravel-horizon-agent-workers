<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        // Register job event listeners
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Queue\Events\JobProcessed::class,
            [\App\Listeners\UpdateLLMQueryStatus::class, 'handleJobProcessed']
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Queue\Events\JobFailed::class,
            [\App\Listeners\UpdateLLMQueryStatus::class, 'handleJobFailed']
        );
    }
}
