<?php

namespace App\Exceptions\LLM;

/**
 * Exception for generic API errors.
 */
class ApiException extends LLMException
{
    public function getUserMessage(): string
    {
        $statusCode = $this->context['status_code'] ?? null;
        $message = "API error from {$this->provider}";

        if ($statusCode) {
            $message .= " (HTTP {$statusCode})";
        }

        $message .= ": {$this->getMessage()}";

        return $message;
    }

    public function isRetryable(): bool
    {
        // Retry on 5xx errors, not on 4xx (except rate limits which have their own exception)
        $statusCode = $this->context['status_code'] ?? 0;

        return $statusCode >= 500 && $statusCode < 600;
    }
}
