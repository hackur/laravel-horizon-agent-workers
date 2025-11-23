<?php

namespace App\Exceptions\LLM;

use Exception;

/**
 * Base exception for all LLM-related errors.
 */
class LLMException extends Exception
{
    protected string $provider;

    protected ?string $model;

    protected array $context;

    public function __construct(
        string $message = '',
        string $provider = 'unknown',
        ?string $model = null,
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->provider = $provider;
        $this->model = $model;
        $this->context = $context;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * Determine if this error is retryable.
     */
    public function isRetryable(): bool
    {
        return false;
    }
}
