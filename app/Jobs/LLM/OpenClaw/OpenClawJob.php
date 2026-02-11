<?php

namespace App\Jobs\LLM\OpenClaw;

use App\Jobs\LLM\BaseLLMJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * OpenClaw CLI Job
 *
 * Executes OpenClaw CLI commands for AI agent tasks.
 * Uses `openclaw agent --local` for local execution.
 *
 * Usage:
 *   OpenClawJob::dispatch('Explain SOLID principles', 'sonnet', null, [
 *       'working_directory' => '/path/to/project',
 *       'thinking' => 'medium',
 *       'session' => 'my-session-123',
 *   ]);
 */
class OpenClawJob extends BaseLLMJob
{
    public $tries = 1;

    public $timeout = 1800; // 30 minutes for agent tasks

    protected string $workingDirectory;
    protected ?string $sessionKey;
    protected ?string $thinkingLevel;

    /**
     * Create a new OpenClaw job instance.
     */
    public function __construct(
        string $prompt,
        ?string $model = null,
        ?int $llmQueryId = null,
        array $options = []
    ) {
        parent::__construct($prompt, $model, $llmQueryId, $options);

        $this->workingDirectory = $options['working_directory'] ?? base_path();
        $this->sessionKey = $options['session'] ?? null;
        $this->thinkingLevel = $options['thinking'] ?? null;

        // Set queue
        $this->onQueue('llm-openclaw');
    }

    /**
     * Execute the OpenClaw command.
     */
    protected function execute(): string
    {
        $command = $this->buildCommand();

        Log::info('🦞 OpenClaw job executing', [
            'working_directory' => $this->workingDirectory,
            'session' => $this->sessionKey,
            'thinking' => $this->thinkingLevel,
        ]);

        $result = Process::timeout($this->timeout)
            ->path($this->workingDirectory)
            ->env($this->getEnvironment())
            ->run($command);

        if ($result->failed()) {
            $error = $result->errorOutput() ?: $result->output();
            throw new \App\Exceptions\LLM\ApiException(
                "OpenClaw command failed (exit code {$result->exitCode()}): " . substr($error, 0, 500),
                'openclaw',
                $this->model,
                ['exit_code' => $result->exitCode()]
            );
        }

        $rawOutput = $result->output();

        if (empty(trim($rawOutput))) {
            throw new \App\Exceptions\LLM\ApiException(
                'OpenClaw returned empty output',
                'openclaw',
                $this->model
            );
        }

        // Parse JSON response
        return $this->parseResponse($rawOutput);
    }

    /**
     * Build the OpenClaw command.
     */
    protected function buildCommand(): string
    {
        $openclawPath = config('orchestrator.openclaw_path', 'openclaw');
        $parts = [$openclawPath, 'agent', '--local', '--json'];

        // Session ID (required by openclaw agent)
        $session = $this->sessionKey ?? 'job-' . uniqid();
        $parts[] = '--session-id';
        $parts[] = escapeshellarg($session);

        // Thinking level
        if ($this->thinkingLevel) {
            $parts[] = '--thinking';
            $parts[] = escapeshellarg($this->thinkingLevel);
        }

        // Message
        $parts[] = '-m';
        $parts[] = escapeshellarg($this->prompt);

        return implode(' ', $parts);
    }

    /**
     * Parse OpenClaw JSON response.
     */
    protected function parseResponse(string $rawOutput): string
    {
        $data = json_decode($rawOutput, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($data['payloads'][0]['text'])) {
            return $data['payloads'][0]['text'];
        }

        // Fallback to raw output
        return $rawOutput;
    }

    /**
     * Get environment variables for command execution.
     */
    protected function getEnvironment(): array
    {
        $env = [];

        $inherit = ['PATH', 'HOME', 'USER', 'SHELL', 'LANG', 'LC_ALL', 'TERM', 'ANTHROPIC_API_KEY'];
        foreach ($inherit as $var) {
            if ($value = getenv($var)) {
                $env[$var] = $value;
            }
        }

        return $env;
    }

    /**
     * Get the provider name.
     */
    protected function getProvider(): string
    {
        return 'openclaw';
    }
}
