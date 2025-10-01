<?php

namespace App\Jobs\LLM;

use Illuminate\Support\Facades\Process;

class ClaudeCodeQueryJob extends BaseLLMJob
{
    public $queue = 'llm-local';
    public $timeout = 900; // Claude Code can take longer

    protected ?string $workingDirectory;

    /**
     * Create a new job instance.
     */
    public function __construct(string $prompt, ?string $model = null, ?int $llmQueryId = null, array $options = [])
    {
        parent::__construct($prompt, $model, $llmQueryId, $options);
        $this->workingDirectory = $options['working_directory'] ?? null;
    }

    /**
     * Execute the Claude Code CLI query.
     */
    protected function execute(): string
    {
        $command = $this->buildCommand();

        // Run through login shell to access user's PATH and claude command
        $shellCommand = sprintf(
            'cd %s && %s',
            escapeshellarg($this->workingDirectory ?? base_path()),
            $command
        );

        $result = Process::timeout($this->timeout)
            ->run(['zsh', '-l', '-c', $shellCommand]);

        if ($result->failed()) {
            $error = trim($result->errorOutput() ?: $result->output());
            throw new \Exception("Claude Code CLI failed: " . ($error ?: 'Command not found. Ensure claude CLI is installed and accessible in your PATH.'));
        }

        return $result->output();
    }

    /**
     * Build the Claude Code command.
     */
    protected function buildCommand(): string
    {
        $escapedPrompt = escapeshellarg($this->prompt);

        $command = "claude {$escapedPrompt}";

        if ($this->model) {
            $command .= " --model " . escapeshellarg($this->model);
        }

        if (isset($this->options['non_interactive']) && $this->options['non_interactive']) {
            $command .= " --non-interactive";
        }

        return $command;
    }

    /**
     * Get the provider name for this job.
     */
    protected function getProvider(): string
    {
        return 'claude-code';
    }
}
