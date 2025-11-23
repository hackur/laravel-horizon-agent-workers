<?php

namespace App\Jobs\LLM\Claude;

use Anthropic\Exceptions\AuthenticationException as AnthropicAuthException;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\RateLimitException as AnthropicRateLimitException;
use Anthropic\Exceptions\UnserializableResponse;
use Anthropic\Laravel\Facades\Anthropic;
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

class ClaudeQueryJob extends BaseLLMJob
{
    public $queue = 'llm-claude';

    /**
     * Storage for additional metadata (usage stats, etc.).
     */
    protected array $additionalMetadata = [];

    /**
     * Execute the Claude API query with comprehensive error handling.
     *
     * Calls the Anthropic Claude API with the provided prompt and parameters.
     * Handles all Claude-specific exceptions including authentication, rate limiting,
     * timeout, and service unavailability. Maps exceptions to appropriate LLMException
     * subtypes for consistent error handling in the base job class.
     *
     * @return string The Claude API response text
     *
     * @throws AuthenticationException If API key is invalid or missing
     * @throws RateLimitException If rate limit is exceeded
     * @throws TimeoutException If connection or request times out
     * @throws ServiceUnavailableException If Claude API is unavailable
     * @throws InvalidRequestException If request is malformed or invalid
     * @throws NetworkException If network connection fails
     * @throws ApiException For other API errors
     */
    protected function execute(): string
    {
        $model = $this->model ?? 'claude-3-5-sonnet-20241022';

        $maxTokens = $this->options['max_tokens'] ?? 1024;
        $temperature = $this->options['temperature'] ?? 1.0;

        try {
            $result = Anthropic::messages()->create([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->prompt,
                    ],
                ],
            ]);

            // Extract the text content from the response
            if (! isset($result->content[0]->text)) {
                throw new InvalidRequestException(
                    'Invalid response structure from Claude API',
                    'claude',
                    $model,
                    ['response' => $result]
                );
            }

            // Capture usage statistics for cost calculation
            if (isset($result->usage)) {
                $this->additionalMetadata['usage_stats'] = [
                    'input_tokens' => $result->usage->inputTokens ?? 0,
                    'output_tokens' => $result->usage->outputTokens ?? 0,
                    'total_tokens' => ($result->usage->inputTokens ?? 0) + ($result->usage->outputTokens ?? 0),
                ];
            }

            // Capture finish reason
            if (isset($result->stopReason)) {
                $this->additionalMetadata['finish_reason'] = $result->stopReason;
            }

            return $result->content[0]->text;
        } catch (AnthropicAuthException $e) {
            throw new AuthenticationException(
                $e->getMessage(),
                'claude',
                $model,
                ['original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (AnthropicRateLimitException $e) {
            // Try to extract retry-after from exception message or headers
            $retryAfter = $this->extractRetryAfter($e);

            throw new RateLimitException(
                $e->getMessage(),
                'claude',
                $model,
                [
                    'retry_after' => $retryAfter,
                    'original_exception' => get_class($e),
                ],
                $e->getCode(),
                $e
            );
        } catch (UnserializableResponse $e) {
            // This typically indicates a network or server error
            throw new ServiceUnavailableException(
                'Unable to parse response from Claude API: '.$e->getMessage(),
                'claude',
                $model,
                ['original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (ErrorException $e) {
            // Anthropic SDK's generic error exception
            $statusCode = $this->extractStatusCode($e);

            // Determine error type based on status code
            if ($statusCode === 401 || $statusCode === 403) {
                throw new AuthenticationException(
                    $e->getMessage(),
                    'claude',
                    $model,
                    ['status_code' => $statusCode, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            } elseif ($statusCode === 429) {
                $retryAfter = $this->extractRetryAfter($e);
                throw new RateLimitException(
                    $e->getMessage(),
                    'claude',
                    $model,
                    ['retry_after' => $retryAfter, 'status_code' => $statusCode, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            } elseif ($statusCode === 400 || $statusCode === 404 || $statusCode === 422) {
                throw new InvalidRequestException(
                    $e->getMessage(),
                    'claude',
                    $model,
                    ['status_code' => $statusCode, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            } elseif ($statusCode === 503 || $statusCode === 502) {
                throw new ServiceUnavailableException(
                    $e->getMessage(),
                    'claude',
                    $model,
                    ['status_code' => $statusCode, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            } else {
                throw new ApiException(
                    $e->getMessage(),
                    'claude',
                    $model,
                    ['status_code' => $statusCode, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            }
        } catch (ConnectionException $e) {
            throw new NetworkException(
                'Failed to connect to Claude API: '.$e->getMessage(),
                'claude',
                $model,
                ['original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (RequestException $e) {
            // HTTP client exceptions
            $statusCode = $e->response->status();

            throw new ApiException(
                'HTTP request failed: '.$e->getMessage(),
                'claude',
                $model,
                ['status_code' => $statusCode, 'original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (\Illuminate\Http\Client\ConnectionTimeoutException $e) {
            throw new TimeoutException(
                'Connection to Claude API timed out',
                'claude',
                $model,
                ['timeout' => $this->timeout, 'original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (\Exception $e) {
            // Catch any other unexpected exceptions
            throw new ApiException(
                'Unexpected error calling Claude API: '.$e->getMessage(),
                'claude',
                $model,
                ['original_exception' => get_class($e), 'trace' => $e->getTraceAsString()],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Extract status code from exception.
     *
     * Attempts to extract an HTTP status code from the exception message.
     * Useful for determining the type of error from generic exception types.
     *
     * @param  \Throwable  $e  The exception to extract status code from
     * @return int|null The HTTP status code if found, null otherwise
     */
    protected function extractStatusCode(\Throwable $e): ?int
    {
        // Try to extract from exception message (e.g., "400 Bad Request")
        if (preg_match('/\b(\d{3})\b/', $e->getMessage(), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract retry-after value from exception.
     *
     * Attempts to extract the retry-after delay value from the exception message.
     * Returns a sensible default (60 seconds) if extraction fails.
     *
     * @param  \Throwable  $e  The exception to extract retry-after from
     * @return int|null The retry-after delay in seconds, or default 60
     */
    protected function extractRetryAfter(\Throwable $e): ?int
    {
        // Try to extract retry-after from exception message
        if (preg_match('/retry[- ]after[:\s]+(\d+)/i', $e->getMessage(), $matches)) {
            return (int) $matches[1];
        }

        // Default retry-after for rate limits
        return 60;
    }

    /**
     * Get additional metadata to store with the query.
     *
     * @return array Additional metadata including usage stats and finish reason
     */
    protected function getAdditionalMetadata(): array
    {
        return $this->additionalMetadata;
    }

    /**
     * Get the provider name for this job.
     *
     * @return string The provider identifier 'claude'
     */
    protected function getProvider(): string
    {
        return 'claude';
    }
}
