<?php

namespace App\Exceptions\LLM;

/**
 * Exception for service unavailability (503 errors, maintenance, etc.).
 */
class ServiceUnavailableException extends LLMException
{
    public function getUserMessage(): string
    {
        return "{$this->provider} is currently unavailable. The service may be down for maintenance. Please try again later.";
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
