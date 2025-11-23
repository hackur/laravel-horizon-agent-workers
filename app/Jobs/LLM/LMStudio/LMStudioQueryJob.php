<?php

namespace App\Jobs\LLM\LMStudio;

use App\Jobs\LLM\BaseLLMJob;
use Illuminate\Support\Facades\Http;

class LMStudioQueryJob extends BaseLLMJob
{
    public $queue = 'llm-local';
    public $timeout = 900; // 15 minutes for reasoning models

    protected ?array $additionalMetadata = null;

    /**
     * Execute the LM Studio query using OpenAI-compatible API.
     */
    protected function execute(): string
    {
        $baseUrl = $this->options['base_url'] ?? 'http://127.0.0.1:1234/v1';
        $model = $this->model ?? 'local-model';
        $maxTokens = $this->options['max_tokens'] ?? (1024*10);
        $temperature = $this->options['temperature'] ?? 0.7;

        // Use a longer timeout for reasoning models (e.g., Magistral)
        $httpTimeout = $this->options['http_timeout'] ?? $this->timeout;

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

        if ($response->failed()) {
            throw new \Exception("LM Studio API request failed: " . $response->body());
        }

        $data = $response->json();

        // Store additional metadata for reasoning models
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $this->additionalMetadata = [
            'reasoning_content' => $message['reasoning_content'] ?? null,
            'finish_reason' => $choice['finish_reason'] ?? null,
            'usage_stats' => $data['usage'] ?? null,
        ];

        return $message['content'] ?? '';
    }

    /**
     * Get additional metadata collected during execution.
     */
    public function getAdditionalMetadata(): ?array
    {
        return $this->additionalMetadata;
    }

    /**
     * Get the provider name for this job.
     */
    protected function getProvider(): string
    {
        return 'lmstudio';
    }
}
