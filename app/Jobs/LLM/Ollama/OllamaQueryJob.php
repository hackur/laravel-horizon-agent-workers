<?php

namespace App\Jobs\LLM\Ollama;

use App\Jobs\LLM\BaseLLMJob;
use CloudStudio\Ollama\Facades\Ollama;

class OllamaQueryJob extends BaseLLMJob
{
    public $queue = 'llm-ollama';
    public $timeout = 600; // Ollama can be slower locally

    /**
     * Execute the Ollama query.
     */
    protected function execute(): string
    {
        $model = $this->model ?? 'llama3.2';

        $stream = $this->options['stream'] ?? false;

        if ($stream) {
            return $this->executeStreaming($model);
        }

        $response = Ollama::agent($model)
            ->prompt($this->prompt)
            ->ask();

        return $response['response'] ?? $response['message']['content'] ?? '';
    }

    /**
     * Execute with streaming support.
     */
    protected function executeStreaming(string $model): string
    {
        $fullResponse = '';

        Ollama::agent($model)
            ->prompt($this->prompt)
            ->stream(function ($response) use (&$fullResponse) {
                if (isset($response['response'])) {
                    $fullResponse .= $response['response'];
                } elseif (isset($response['message']['content'])) {
                    $fullResponse .= $response['message']['content'];
                }
            });

        return $fullResponse;
    }

    /**
     * Get the provider name for this job.
     */
    protected function getProvider(): string
    {
        return 'ollama';
    }
}
