<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;

class UpdateLLMQueryStatus
{
    /**
     * Handle job processed events.
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        Log::info('Job processed', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job' => $event->job->getName(),
        ]);
    }

    /**
     * Handle job failed events.
     */
    public function handleJobFailed(JobFailed $event): void
    {
        Log::error('Job failed', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job' => $event->job->getName(),
            'exception' => $event->exception->getMessage(),
        ]);
    }
}
