<?php

namespace App\Jobs\LLM\LMStudio;

use App\Jobs\LLM\BaseLLMJob;
use Illuminate\Support\Facades\Http;

class LMStudioQueryJob extends BaseLLMJob
{
    public $queue = 'llm-local';
    public $timeout = 600;

    /**
     * Execute the LM Studio query using OpenAI-compatible API.
     */
    protected function execute(): string
    {
        $baseUrl = $this->options['base_url'] ?? 'http://localhost:1234/v1';
        $model = $this->model ?? 'local-model';
        $maxTokens = $this->options['max_tokens'] ?? 1024;
        $temperature = $this->options['temperature'] ?? 0.7;

        $response = Http::timeout($this->timeout)
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

        if ($response->failed()) {
            throw new \Exception("LM Studio API request failed: " . $response->body());
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Get the provider name for this job.
     */
    protected function getProvider(): string
    {
        return 'lmstudio';
    }
}
