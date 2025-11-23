<?php

namespace App\Exceptions\LLM;

/**
 * Exception for invalid request errors (bad parameters, model not found, etc.).
 */
class InvalidRequestException extends LLMException
{
    public function getUserMessage(): string
    {
        return "Invalid request to {$this->provider}: {$this->getMessage()}. Please check your parameters and try again.";
    }

    public function isRetryable(): bool
    {
        return false; // Don't retry invalid requests
    }
}
