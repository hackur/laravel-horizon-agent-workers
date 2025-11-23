<?php

namespace App\Jobs\LLM\Ollama;

use App\Exceptions\LLM\ApiException;
use App\Exceptions\LLM\InvalidRequestException;
use App\Exceptions\LLM\NetworkException;
use App\Exceptions\LLM\ServiceUnavailableException;
use App\Exceptions\LLM\TimeoutException;
use App\Jobs\LLM\BaseLLMJob;
use CloudStudio\Ollama\Facades\Ollama;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class OllamaQueryJob extends BaseLLMJob
{
    public $queue = 'llm-ollama';

    public $timeout = 600; // Ollama can be slower locally

    /**
     * Execute the Ollama query with comprehensive error handling.
     *
     * Calls Ollama with the provided prompt using the specified model.
     * Supports both streaming and non-streaming modes. Handles all Ollama-specific
     * errors including connection issues, missing models, and timeouts.
     *
     * @return string The Ollama response text
     *
     * @throws NetworkException If connection to Ollama fails
     * @throws InvalidRequestException If model is not found or response format is invalid
     * @throws ServiceUnavailableException If Ollama service is unavailable
     * @throws TimeoutException If request times out
     * @throws ApiException For other API errors
     */
    protected function execute(): string
    {
        $model = $this->model ?? 'llama3.2';

        $stream = $this->options['stream'] ?? false;

        try {
            if ($stream) {
                return $this->executeStreaming($model);
            }

            return $this->executeNonStreaming($model);
        } catch (ConnectionException $e) {
            throw new NetworkException(
                'Failed to connect to Ollama service: '.$e->getMessage().'. Please ensure Ollama is running.',
                'ollama',
                $model,
                ['original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (RequestException $e) {
            $statusCode = $e->response ? $e->response->status() : null;

            if ($statusCode === 404) {
                throw new InvalidRequestException(
                    "Model '{$model}' not found. Please pull the model first using 'ollama pull {$model}'.",
                    'ollama',
                    $model,
                    ['status_code' => $statusCode, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            } elseif ($statusCode === 503) {
                throw new ServiceUnavailableException(
                    'Ollama service is unavailable. It may be starting up or overloaded.',
                    'ollama',
                    $model,
                    ['status_code' => $statusCode, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            } else {
                throw new ApiException(
                    'HTTP request to Ollama failed: '.$e->getMessage(),
                    'ollama',
                    $model,
                    ['status_code' => $statusCode, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            }
        } catch (\Illuminate\Http\Client\ConnectionTimeoutException $e) {
            throw new TimeoutException(
                'Connection to Ollama timed out. The model may be loading or the query is too complex.',
                'ollama',
                $model,
                ['timeout' => $this->timeout, 'original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        } catch (\Exception $e) {
            // Handle any SDK-specific exceptions
            $message = $e->getMessage();

            // Check for common Ollama error patterns
            if (str_contains($message, 'model') && str_contains($message, 'not found')) {
                throw new InvalidRequestException(
                    "Model '{$model}' not found: {$message}",
                    'ollama',
                    $model,
                    ['original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            } elseif (str_contains($message, 'timeout')) {
                throw new TimeoutException(
                    'Ollama query timed out: '.$message,
                    'ollama',
                    $model,
                    ['timeout' => $this->timeout, 'original_exception' => get_class($e)],
                    $e->getCode(),
                    $e
                );
            } else {
                throw new ApiException(
                    'Unexpected error calling Ollama: '.$message,
                    'ollama',
                    $model,
                    ['original_exception' => get_class($e), 'trace' => $e->getTraceAsString()],
                    $e->getCode(),
                    $e
                );
            }
        }
    }

    /**
     * Execute non-streaming query.
     *
     * Sends a single request to Ollama and waits for the complete response.
     * Handles response parsing from different Ollama SDK response formats.
     *
     * @param  string  $model  The model to query
     * @return string The complete response from Ollama
     *
     * @throws InvalidRequestException If response format is invalid
     * @throws ApiException If query execution fails
     */
    protected function executeNonStreaming(string $model): string
    {
        try {
            $response = Ollama::agent($model)
                ->prompt($this->prompt)
                ->ask();

            // Handle different response formats
            if (is_string($response)) {
                return $response;
            }

            if (is_array($response)) {
                // Try to extract response from common Ollama response formats
                if (isset($response['response'])) {
                    return $response['response'];
                } elseif (isset($response['message']['content'])) {
                    return $response['message']['content'];
                } elseif (isset($response['content'])) {
                    return $response['content'];
                }
            }

            // If we can't parse the response, throw an error
            throw new InvalidRequestException(
                'Unable to parse Ollama response. Unexpected response format.',
                'ollama',
                $model,
                ['response' => $response]
            );
        } catch (\Throwable $e) {
            // Re-throw if it's already one of our custom exceptions
            if ($e instanceof \App\Exceptions\LLM\LLMException) {
                throw $e;
            }

            // Otherwise wrap it
            throw new ApiException(
                'Error executing Ollama query: '.$e->getMessage(),
                'ollama',
                $model,
                ['original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Execute with streaming support.
     *
     * Streams the response from Ollama in chunks, accumulating the full response.
     * Validates that data is received and the final response is not empty.
     *
     * @param  string  $model  The model to query
     * @return string The accumulated streamed response from Ollama
     *
     * @throws ServiceUnavailableException If no data is received from stream
     * @throws InvalidRequestException If final response is empty
     * @throws ApiException If streaming execution fails
     */
    protected function executeStreaming(string $model): string
    {
        $fullResponse = '';
        $hasReceivedData = false;

        try {
            Ollama::agent($model)
                ->prompt($this->prompt)
                ->stream(function ($response) use (&$fullResponse, &$hasReceivedData) {
                    $hasReceivedData = true;

                    if (is_string($response)) {
                        $fullResponse .= $response;
                    } elseif (is_array($response)) {
                        if (isset($response['response'])) {
                            $fullResponse .= $response['response'];
                        } elseif (isset($response['message']['content'])) {
                            $fullResponse .= $response['message']['content'];
                        } elseif (isset($response['content'])) {
                            $fullResponse .= $response['content'];
                        }
                    }
                });

            if (! $hasReceivedData) {
                throw new ServiceUnavailableException(
                    'No data received from Ollama streaming response',
                    'ollama',
                    $model,
                    ['stream' => true]
                );
            }

            if (empty($fullResponse)) {
                throw new InvalidRequestException(
                    'Empty response from Ollama. The model may have failed to generate content.',
                    'ollama',
                    $model,
                    ['stream' => true]
                );
            }

            return $fullResponse;
        } catch (\Throwable $e) {
            // Re-throw if it's already one of our custom exceptions
            if ($e instanceof \App\Exceptions\LLM\LLMException) {
                throw $e;
            }

            // Otherwise wrap it
            throw new ApiException(
                'Error executing streaming Ollama query: '.$e->getMessage(),
                'ollama',
                $model,
                ['stream' => true, 'original_exception' => get_class($e)],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get the provider name for this job.
     *
     * @return string The provider identifier 'ollama'
     */
    protected function getProvider(): string
    {
        return 'ollama';
    }
}
