<?php

namespace App\Exceptions\LLM;

/**
 * Exception for network-related errors (connection failures, DNS issues, etc.).
 */
class NetworkException extends LLMException
{
    public function getUserMessage(): string
    {
        return "Network error connecting to {$this->provider}: {$this->getMessage()}. Please check your network connection and try again.";
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
