<?php

namespace App\Services;

use App\Jobs\LLM\Claude\ClaudeQueryJob;
use App\Jobs\LLM\LMStudio\LMStudioQueryJob;
use App\Jobs\LLM\LocalCommandJob;
use App\Jobs\LLM\Ollama\OllamaQueryJob;
use App\Models\LLMQuery;
use InvalidArgumentException;

class LLMQueryDispatcher
{
    /**
     * Dispatch a query to the specified LLM provider.
     */
    public function dispatch(string $provider, string $prompt, ?string $model = null, array $options = []): LLMQuery
    {
        $llmQuery = LLMQuery::create([
            'user_id' => $options['user_id'] ?? null,
            'conversation_id' => $options['conversation_id'] ?? null,
            'provider' => $provider,
            'model' => $model,
            'prompt' => $prompt,
            'status' => 'pending',
            'metadata' => $options,
        ]);

        $job = $this->createJob($provider, $prompt, $model, $llmQuery->id, $options);

        dispatch($job);

        return $llmQuery;
    }

    /**
     * Dispatch a query without persisting to database.
     */
    public function dispatchOnly(string $provider, string $prompt, ?string $model = null, array $options = []): void
    {
        $job = $this->createJob($provider, $prompt, $model, null, $options);
        dispatch($job);
    }

    /**
     * Create the appropriate job class based on provider.
     */
    protected function createJob(string $provider, string $prompt, ?string $model, ?int $llmQueryId, array $options)
    {
        return match ($provider) {
            'claude' => new ClaudeQueryJob($prompt, $model, $llmQueryId, $options),
            'ollama' => new OllamaQueryJob($prompt, $model, $llmQueryId, $options),
            'lmstudio' => new LMStudioQueryJob($prompt, $model, $llmQueryId, $options),
            'local-command' => new LocalCommandJob($prompt, $model, $llmQueryId, $options),
            default => throw new InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }

    /**
     * Get list of available providers.
     */
    public function getProviders(): array
    {
        return [
            'claude' => [
                'name' => 'Claude API',
                'description' => 'Anthropic Claude API (requires API key)',
                'queue' => 'llm-claude',
                'models' => ['claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229'],
            ],
            'ollama' => [
                'name' => 'Ollama',
                'description' => 'Local Ollama instance',
                'queue' => 'llm-ollama',
                'models' => ['llama3.2', 'llama3.1', 'mistral', 'codellama'],
            ],
            'lmstudio' => [
                'name' => 'LM Studio',
                'description' => 'Local LM Studio server',
                'queue' => 'llm-local',
                'models' => ['local-model'],
            ],
            'local-command' => [
                'name' => 'Local Command (Claude Code, etc.)',
                'description' => 'Execute local commands with your shell environment - Use "claude" for Claude Code CLI (requires authentication)',
                'queue' => 'llm-local',
                'models' => [],
            ],
        ];
    }
}
