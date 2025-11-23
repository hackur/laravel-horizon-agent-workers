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

    // Whitelist of allowed commands
    protected const ALLOWED_COMMANDS = [
        'claude',
        'ollama',
        'llm',
        'aider',
    ];

    // Whitelist of allowed shell paths
    protected const ALLOWED_SHELLS = [
        '/bin/bash',
        '/bin/zsh',
        '/bin/sh',
        '/usr/bin/bash',
        '/usr/bin/zsh',
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(string $prompt, ?string $model = null, ?int $llmQueryId = null, array $options = [])
    {
        parent::__construct($prompt, $model, $llmQueryId, $options);

        // Validate and sanitize command
        $this->command = $this->validateCommand($options['command'] ?? 'claude');

        // Validate working directory
        $this->workingDirectory = $this->validateWorkingDirectory(
            $options['working_directory'] ?? base_path()
        );

        // Validate shell
        $this->shell = $this->validateShell($options['shell'] ?? null);
    }

    /**
     * Validate command is in whitelist.
     */
    protected function validateCommand(string $command): string
    {
        // Extract base command (before any placeholders or arguments)
        $baseCommand = trim(explode(' ', $command)[0]);
        $baseCommand = str_replace(['{prompt}', '{model}'], '', $baseCommand);
        $baseCommand = trim($baseCommand);

        if (empty($baseCommand)) {
            throw new \InvalidArgumentException('Command cannot be empty');
        }

        // Check if command is in whitelist
        if (!in_array($baseCommand, self::ALLOWED_COMMANDS, true)) {
            throw new \InvalidArgumentException(
                "Command '{$baseCommand}' is not allowed. Allowed commands: " .
                implode(', ', self::ALLOWED_COMMANDS)
            );
        }

        // Validate the command doesn't contain shell metacharacters
        if (preg_match('/[;&|`$()<>]/', $command)) {
            throw new \InvalidArgumentException(
                'Command contains invalid characters. Shell metacharacters are not allowed.'
            );
        }

        return $command;
    }

    /**
     * Validate working directory exists and is within allowed paths.
     */
    protected function validateWorkingDirectory(string $directory): string
    {
        $realPath = realpath($directory);

        if ($realPath === false || !is_dir($realPath)) {
            throw new \InvalidArgumentException("Working directory does not exist: {$directory}");
        }

        // Ensure it's not a system directory
        $systemDirs = ['/bin', '/sbin', '/usr/bin', '/usr/sbin', '/etc', '/var', '/boot', '/sys', '/proc'];
        foreach ($systemDirs as $sysDir) {
            if (str_starts_with($realPath, $sysDir)) {
                throw new \InvalidArgumentException(
                    "Working directory cannot be a system directory: {$realPath}"
                );
            }
        }

        return $realPath;
    }

    /**
     * Validate shell is in whitelist.
     */
    protected function validateShell(?string $shell): string
    {
        if ($shell === null) {
            $shell = getenv('SHELL') ?: '/bin/zsh';
        }

        $realShell = realpath($shell);
        if ($realShell === false) {
            throw new \InvalidArgumentException("Shell does not exist: {$shell}");
        }

        if (!in_array($realShell, self::ALLOWED_SHELLS, true)) {
            throw new \InvalidArgumentException(
                "Shell '{$realShell}' is not allowed. Allowed shells: " .
                implode(', ', self::ALLOWED_SHELLS)
            );
        }

        return $realShell;
    }

    /**
     * Execute the local command with user's shell environment.
     */
    protected function execute(): string
    {
        $command = $this->buildCommand();

        // Get user's home directory
        $home = getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];

        // Validate home directory
        if (empty($home) || !is_dir($home)) {
            throw new \RuntimeException('Unable to determine valid home directory');
        }

        // Build command that ensures we're in the user's environment
        // Use interactive login shell (-l -i) to source both .zprofile and .zshrc
        // This ensures the full user environment including .zshrc configuration
        $wrappedCommand = sprintf(
            'cd %s && HOME=%s USER=%s %s -l -i -c %s',
            escapeshellarg($this->workingDirectory),
            escapeshellarg($home),
            escapeshellarg(get_current_user()),
            $this->shell,
            escapeshellarg($command)
        );

        $result = Process::timeout($this->timeout)
            ->env($this->getEnvironment())
            ->run($wrappedCommand);

        if ($result->failed()) {
            $errorOutput = $result->errorOutput();
            $output = $result->output();

            throw new \Exception(
                "Command failed with exit code {$result->exitCode()}\n".
                "STDOUT: {$output}\n".
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
        // Sanitize prompt - remove any shell metacharacters
        $sanitizedPrompt = $this->sanitizePrompt($this->prompt);
        $escapedPrompt = escapeshellarg($sanitizedPrompt);

        // Build command based on template
        $command = str_replace('{prompt}', $escapedPrompt, $this->command);

        // If command doesn't have {prompt} placeholder, append it
        if (!str_contains($this->command, '{prompt}')) {
            $command = "{$this->command} {$escapedPrompt}";
        }

        // Add model if specified and command supports it
        if ($this->model && str_contains($command, '{model}')) {
            // Validate model doesn't contain shell metacharacters
            if (preg_match('/[;&|`$()<>]/', $this->model)) {
                throw new \InvalidArgumentException('Model name contains invalid characters');
            }
            $command = str_replace('{model}', escapeshellarg($this->model), $command);
        }

        return $command;
    }

    /**
     * Sanitize prompt to prevent command injection.
     */
    protected function sanitizePrompt(string $prompt): string
    {
        // Remove null bytes
        $prompt = str_replace("\0", '', $prompt);

        // Limit length to prevent abuse
        if (strlen($prompt) > 100000) {
            throw new \InvalidArgumentException('Prompt exceeds maximum length of 100,000 characters');
        }

        return $prompt;
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
                // Validate environment variable values
                if (strlen($value) > 10000) {
                    continue; // Skip suspiciously long values
                }
                $env[$var] = $value;
            }
        }

        // DO NOT allow custom environment variables from options
        // This prevents users from injecting malicious env vars like LD_PRELOAD
        // If you need custom env vars, add them to the whitelist above

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
