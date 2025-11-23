<?php

namespace App\Exceptions\LLM;

/**
 * Exception for rate limiting errors.
 */
class RateLimitException extends LLMException
{
    public function getUserMessage(): string
    {
        $retryAfter = $this->context['retry_after'] ?? null;
        $message = "Rate limit exceeded for {$this->provider}.";

        if ($retryAfter) {
            $message .= " Please try again after {$retryAfter} seconds.";
        } else {
            $message .= ' Please try again later.';
        }

        return $message;
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
