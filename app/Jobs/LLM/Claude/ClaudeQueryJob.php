<?php

namespace App\Jobs\LLM\Claude;

use App\Jobs\LLM\BaseLLMJob;
use Anthropic\Laravel\Facades\Anthropic;

class ClaudeQueryJob extends BaseLLMJob
{
    public $queue = 'llm-claude';

    /**
     * Execute the Claude API query.
     */
    protected function execute(): string
    {
        $model = $this->model ?? 'claude-3-5-sonnet-20241022';

        $maxTokens = $this->options['max_tokens'] ?? 1024;
        $temperature = $this->options['temperature'] ?? 1.0;

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

        return $result->content[0]->text;
    }

    /**
     * Get the provider name for this job.
     */
    protected function getProvider(): string
    {
        return 'claude';
    }
}
