<?php

namespace App\Exceptions\LLM;

/**
 * Exception for authentication/authorization errors.
 */
class AuthenticationException extends LLMException
{
    public function getUserMessage(): string
    {
        return "Authentication failed for {$this->provider}. Please check your API key or credentials.";
    }

    public function isRetryable(): bool
    {
        return false; // Don't retry auth failures
    }
}
