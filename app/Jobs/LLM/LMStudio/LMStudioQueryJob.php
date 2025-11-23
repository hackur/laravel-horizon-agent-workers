<?php

namespace App\Jobs\LLM\LMStudio;

use App\Exceptions\LLM\ApiException;
use App\Exceptions\LLM\AuthenticationException;
use App\Exceptions\LLM\InvalidRequestException;
use App\Exceptions\LLM\NetworkException;
use App\Exceptions\LLM\RateLimitException;
use App\Exceptions\LLM\ServiceUnavailableException;
use App\Exceptions\LLM\TimeoutException;
use App\Jobs\LLM\BaseLLMJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class LMStudioQueryJob extends BaseLLMJob
{
    public $queue = 'llm-local';

    public $timeout = 900; // 15 minutes for reasoning models

    protected ?array $additionalMetadata = null;

    /**
     * Execute the LM Studio query using OpenAI-compatible API.
     *
     * Calls the LM Studio server via its OpenAI-compatible chat/completions endpoint.
     * Supports reasoning models with extended timeouts (up to 15 minutes) and captures
     * additional metadata like reasoning_content. Comprehensive error handling for all
     * HTTP status codes and network conditions.
     *
     * @return string The model response text
     *
     * @throws NetworkException If connection to LM Studio fails
     * @throws TimeoutException If request times out
     * @throws InvalidRequestException If request is malformed or response structure is invalid
     * @throws AuthenticationException If authentication fails
     * @throws RateLimitException If rate limit is exceeded
     * @throws ServiceUnavailableException If server is unavailable
     * @throws ApiException For other API errors
     */
    protected function execute(): string
    {
        $baseUrl = $this->options['base_url'] ?? 'http://127.0.0.1:1234/v1';
        $model = $this->model ?? 'local-model';
        $maxTokens = $this->options['max_tokens'] ?? (1024 * 10);
        $temperature = $this->options['temperature'] ?? 0.7;

        // Use a longer timeout for reasoning models (e.g., Magistral)
        $httpTimeout = $this->options['http_timeout'] ?? $this->timeout;

        try {
            $response = Http::timeout($httpTimeout)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $this->prompt,
                        ],
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);

            // Handle HTTP errors
            if ($response->failed()) {
                return $this->handleHttpError($response, $model);
            }

            return $this->parseSuccessResponse($response, $model);
        } catch (ConnectionException $e) {
            throw new NetworkException(
                'Failed to connect to LM Studio: '.$e->getMessage().'. Please ensure LM Studio is running and the server is started.',
                'lmstudio',
                $model,
                ['base_url' => $baseUrl, 'original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (\Illuminate\Http\Client\ConnectionTimeoutException $e) {
            throw new TimeoutException(
                'Connection to LM Studio timed out. The model may be loading, or the query is too complex for the model.',
                'lmstudio',
                $model,
                ['timeout' => $httpTimeout, 'base_url' => $baseUrl, 'original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (RequestException $e) {
            throw new ApiException(
                'HTTP request to LM Studio failed: '.$e->getMessage(),
                'lmstudio',
                $model,
                ['base_url' => $baseUrl, 'original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (\Exception $e) {
            // Re-throw if it's already one of our custom exceptions
            if ($e instanceof \App\Exceptions\LLM\LLMException) {
                throw $e;
            }

            // Otherwise wrap it
            throw new ApiException(
                'Unexpected error calling LM Studio: '.$e->getMessage(),
                'lmstudio',
                $model,
                ['base_url' => $baseUrl, 'original_exception' => get_class($e), 'trace' => $e->getTraceAsString()],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Handle HTTP error responses.
     *
     * Parses HTTP error responses and throws appropriate LLMException subtypes
     * based on the HTTP status code. Extracts error messages from response body.
     *
     * @param  mixed  $response  The HTTP response object
     * @param  string  $model  The model that was being queried
     * @return never Always throws an exception
     *
     * @throws InvalidRequestException For 400, 404 errors
     * @throws AuthenticationException For 401, 403 errors
     * @throws RateLimitException For 429 errors
     * @throws ServiceUnavailableException For 500, 502, 503, 504 errors
     * @throws ApiException For all other errors
     */
    protected function handleHttpError($response, string $model): never
    {
        $statusCode = $response->status();
        $body = $response->body();
        $baseUrl = $this->options['base_url'] ?? 'http://127.0.0.1:1234/v1';

        // Try to parse error message from response
        try {
            $json = $response->json();
            $errorMessage = $json['error']['message'] ?? $json['message'] ?? $body;
        } catch (\Exception $e) {
            $errorMessage = $body;
        }

        $context = [
            'status_code' => $statusCode,
            'base_url' => $baseUrl,
            'error_body' => $body,
        ];

        // Handle specific status codes
        switch ($statusCode) {
            case 400:
                throw new InvalidRequestException(
                    "Invalid request to LM Studio: {$errorMessage}",
                    'lmstudio',
                    $model,
                    $context
                );

            case 401:
            case 403:
                throw new AuthenticationException(
                    "Authentication failed for LM Studio: {$errorMessage}",
                    'lmstudio',
                    $model,
                    $context
                );

            case 404:
                throw new InvalidRequestException(
                    "Model '{$model}' not found in LM Studio. Please load the model first.",
                    'lmstudio',
                    $model,
                    $context
                );

            case 429:
                throw new RateLimitException(
                    "Rate limit exceeded for LM Studio: {$errorMessage}",
                    'lmstudio',
                    $model,
                    $context
                );

            case 500:
            case 502:
            case 503:
            case 504:
                throw new ServiceUnavailableException(
                    "LM Studio server error: {$errorMessage}",
                    'lmstudio',
                    $model,
                    $context
                );

            default:
                throw new ApiException(
                    "LM Studio API error (HTTP {$statusCode}): {$errorMessage}",
                    'lmstudio',
                    $model,
                    $context
                );
        }
    }

    /**
     * Parse successful response and extract content.
     *
     * Validates and parses a successful LM Studio API response. Extracts content
     * from the OpenAI-compatible response format and captures additional metadata
     * including reasoning_content for reasoning models and usage statistics.
     *
     * @param  mixed  $response  The successful HTTP response object
     * @param  string  $model  The model that was queried
     * @return string The extracted response content
     *
     * @throws InvalidRequestException If response structure is invalid or empty
     */
    protected function parseSuccessResponse($response, string $model): string
    {
        try {
            $data = $response->json();
        } catch (\Exception $e) {
            throw new InvalidRequestException(
                'Unable to parse JSON response from LM Studio: '.$e->getMessage(),
                'lmstudio',
                $model,
                ['response_body' => $response->body()]
            );
        }

        // Validate response structure
        if (! isset($data['choices']) || ! is_array($data['choices']) || empty($data['choices'])) {
            throw new InvalidRequestException(
                'Invalid response structure from LM Studio: missing or empty choices array',
                'lmstudio',
                $model,
                ['response' => $data]
            );
        }

        $choice = $data['choices'][0] ?? null;
        if (! $choice) {
            throw new InvalidRequestException(
                'Invalid response structure from LM Studio: no choices available',
                'lmstudio',
                $model,
                ['response' => $data]
            );
        }

        $message = $choice['message'] ?? null;
        if (! $message) {
            throw new InvalidRequestException(
                'Invalid response structure from LM Studio: no message in choice',
                'lmstudio',
                $model,
                ['response' => $data]
            );
        }

        // Store additional metadata for reasoning models
        $this->additionalMetadata = [
            'reasoning_content' => $message['reasoning_content'] ?? null,
            'finish_reason' => $choice['finish_reason'] ?? null,
            'usage_stats' => $data['usage'] ?? null,
        ];

        // Extract content
        $content = $message['content'] ?? null;
        if ($content === null || $content === '') {
            // Check if it was intentionally empty or an error
            $finishReason = $choice['finish_reason'] ?? 'unknown';
            if ($finishReason === 'length') {
                throw new InvalidRequestException(
                    'LM Studio response was cut off due to max_tokens limit. Try increasing max_tokens.',
                    'lmstudio',
                    $model,
                    ['finish_reason' => $finishReason, 'usage' => $data['usage'] ?? null]
                );
            } elseif ($finishReason === 'content_filter') {
                throw new InvalidRequestException(
                    'LM Studio response was filtered due to content policy.',
                    'lmstudio',
                    $model,
                    ['finish_reason' => $finishReason]
                );
            } else {
                throw new InvalidRequestException(
                    'Empty response content from LM Studio.',
                    'lmstudio',
                    $model,
                    ['finish_reason' => $finishReason, 'response' => $data]
                );
            }
        }

        return $content;
    }

    /**
     * Get additional metadata collected during execution.
     *
     * Returns metadata captured from the LM Studio response including reasoning_content,
     * finish_reason, and usage statistics. Used by the base job class to update the
     * LLMQuery with additional fields beyond the standard response.
     *
     * @return array|null The metadata array or null if none was captured
     */
    public function getAdditionalMetadata(): ?array
    {
        return $this->additionalMetadata;
    }

    /**
     * Get the provider name for this job.
     *
     * @return string The provider identifier 'lmstudio'
     */
    protected function getProvider(): string
    {
        return 'lmstudio';
    }
}
