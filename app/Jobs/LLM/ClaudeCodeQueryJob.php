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

        $result = Process::timeout($this->timeout)
            ->path($this->workingDirectory ?? base_path())
            ->run($command);

        if ($result->failed()) {
            throw new \Exception("Claude Code CLI failed: " . $result->errorOutput());
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
