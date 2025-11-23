<?php

namespace App\Exceptions\LLM;

/**
 * Exception for timeout errors.
 */
class TimeoutException extends LLMException
{
    public function getUserMessage(): string
    {
        $timeout = $this->context['timeout'] ?? 'unknown';

        return "Request to {$this->provider} timed out after {$timeout} seconds. The model may be processing a complex query or the service may be overloaded. Please try again.";
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
