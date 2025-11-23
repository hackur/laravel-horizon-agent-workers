<?php

namespace App\Jobs\LLM;

use Illuminate\Support\Facades\Process;

class LocalCommandJob extends BaseLLMJob
{
    public $queue = 'llm-local';
    public $timeout = 900; // 15 minutes for long-running commands

    protected ?string $workingDirectory;
    protected ?string $command;
    protected ?string $shell;

    /**
     * Create a new job instance.
     */
    public function __construct(string $prompt, ?string $model = null, ?int $llmQueryId = null, array $options = [])
    {
        parent::__construct($prompt, $model, $llmQueryId, $options);

        // Allow custom command template or use Claude Code by default
        $this->command = $options['command'] ?? 'claude';
        $this->workingDirectory = $options['working_directory'] ?? base_path();
        $this->shell = $options['shell'] ?? null; // Use system default if null
    }

    /**
     * Execute the local command with user's shell environment.
     */
    protected function execute(): string
    {
        $command = $this->buildCommand();

        // Get user's home directory
        $home = getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];

        // Use user's login shell
        $shell = $this->shell ?? getenv('SHELL') ?: '/bin/zsh';

        // Build command that ensures we're in the user's environment
        // Use interactive login shell (-l -i) to source both .zprofile and .zshrc
        // This ensures the full user environment including .zshrc configuration
        $wrappedCommand = sprintf(
            'cd %s && HOME=%s USER=%s %s -l -i -c %s',
            escapeshellarg($this->workingDirectory),
            escapeshellarg($home),
            escapeshellarg(get_current_user()),
            $shell,
            escapeshellarg($command)
        );

        $result = Process::timeout($this->timeout)
            ->env($this->getEnvironment())
            ->run($wrappedCommand);

        if ($result->failed()) {
            $errorOutput = $result->errorOutput();
            $output = $result->output();

            throw new \Exception(
                "Command failed with exit code {$result->exitCode()}\n" .
                "STDOUT: {$output}\n" .
                "STDERR: {$errorOutput}"
            );
        }

        return $result->output();
    }

    /**
     * Build the command to execute.
     */
    protected function buildCommand(): string
    {
        $escapedPrompt = escapeshellarg($this->prompt);

        // Build command based on template
        $command = str_replace('{prompt}', $escapedPrompt, $this->command);

        // If command doesn't have {prompt} placeholder, append it
        if (!str_contains($this->command, '{prompt}')) {
            $command = "{$this->command} {$escapedPrompt}";
        }

        // Add model if specified and command supports it
        if ($this->model && str_contains($command, '{model}')) {
            $command = str_replace('{model}', escapeshellarg($this->model), $command);
        }

        return $command;
    }

    /**
     * Get environment variables for the command.
     * Inherits user's shell environment including PATH, HOME, etc.
     */
    protected function getEnvironment(): array
    {
        $env = [];

        // Preserve important environment variables
        $preserveVars = [
            'PATH',
            'HOME',
            'USER',
            'SHELL',
            'LANG',
            'LC_ALL',
            'TMPDIR',
            // Claude Code / Anthropic
            'ANTHROPIC_API_KEY',
            'ANTHROPIC_BASE_URL',
            'CLAUDE_CONFIG_PATH',
            'CLAUDE_CODE_ENTRYPOINT',
            'CLAUDE_CODE_SSE_PORT',
            'CLAUDECODE',
            'XDG_CONFIG_HOME',
            // asdf
            'ASDF_DIR',
            'ASDF_DATA_DIR',
            'ASDF_INSTALL_PATH',
            'ASDF_INSTALL_TYPE',
            'ASDF_INSTALL_VERSION',
        ];

        foreach ($preserveVars as $var) {
            $value = getenv($var);
            if ($value !== false) {
                $env[$var] = $value;
            }
        }

        // Allow custom environment variables from options
        if (isset($this->options['env']) && is_array($this->options['env'])) {
            $env = array_merge($env, $this->options['env']);
        }

        return $env;
    }

    /**
     * Get the provider name for this job.
     */
    protected function getProvider(): string
    {
        return $this->options['provider_name'] ?? 'local-command';
    }
}
