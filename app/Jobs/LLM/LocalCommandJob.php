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
        'echo',
        'openclaw',
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
     *
     * Constructor validates and sanitizes all command execution parameters including
     * the command itself, working directory, and shell. Uses strict whitelisting to
     * prevent command injection and unauthorized file access. Throws exceptions if
     * any parameter fails validation.
     *
     * @param  string  $prompt  The user prompt/input for the command
     * @param  string|null  $model  Optional model specification
     * @param  int|null  $llmQueryId  Optional LLMQuery ID for tracking
     * @param  array  $options  Options including command, working_directory, shell
     *
     * @throws \InvalidArgumentException If command, directory, or shell fails validation
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
     *
     * Validates that the command (e.g., 'claude', 'ollama', 'llm', 'aider') is in the
     * whitelist and doesn't contain shell metacharacters or dangerous patterns.
     * Protects against command injection attacks.
     *
     * @param  string  $command  The command to validate
     * @return string The validated command
     *
     * @throws \InvalidArgumentException If command is empty, not whitelisted, or contains dangerous characters
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
        if (! in_array($baseCommand, self::ALLOWED_COMMANDS, true)) {
            throw new \InvalidArgumentException(
                "Command '{$baseCommand}' is not allowed. Allowed commands: ".
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
     *
     * Ensures the working directory exists, is a valid directory, and is not within
     * sensitive system directories. Prevents directory traversal attacks and
     * unauthorized access to system files.
     *
     * @param  string  $directory  The directory path to validate
     * @return string The canonical path to the validated directory
     *
     * @throws \InvalidArgumentException If directory doesn't exist or is a system directory
     */
    protected function validateWorkingDirectory(string $directory): string
    {
        $realPath = realpath($directory);

        if ($realPath === false || ! is_dir($realPath)) {
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
     *
     * Validates that the shell is in the whitelist of allowed shells.
     * Falls back to the user's default shell if none specified. Uses realpath
     * to prevent symbolic link attacks.
     *
     * @param  string|null  $shell  The shell path to validate, or null for default
     * @return string The validated shell path
     *
     * @throws \InvalidArgumentException If shell doesn't exist or is not in whitelist
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

        if (! in_array($realShell, self::ALLOWED_SHELLS, true)) {
            throw new \InvalidArgumentException(
                "Shell '{$realShell}' is not allowed. Allowed shells: ".
                implode(', ', self::ALLOWED_SHELLS)
            );
        }

        return $realShell;
    }

    /**
     * Execute the local command with user's shell environment.
     *
     * Executes the validated command in the user's shell with proper environment setup.
     * Uses an interactive login shell to source all user configuration files.
     * Inherits critical environment variables including ANTHROPIC_API_KEY, PATH, and asdf.
     *
     * @return string The command's standard output
     *
     * @throws \Exception If command execution fails or home directory cannot be determined
     */
    protected function execute(): string
    {
        try {
            $command = $this->buildCommand();

            // Get user's home directory
            $home = getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];

            // Validate home directory
            if (empty($home) || ! is_dir($home)) {
                throw new \App\Exceptions\LLM\InvalidRequestException(
                    'Unable to determine valid home directory',
                    $this->getProvider(),
                    $this->model,
                    ['home' => $home]
                );
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
                return $this->handleCommandFailure($result);
            }

            $output = $result->output();

            // Validate output is not empty
            if (empty(trim($output))) {
                throw new \App\Exceptions\LLM\InvalidRequestException(
                    'Command executed successfully but returned empty output',
                    $this->getProvider(),
                    $this->model,
                    [
                        'command' => $this->command,
                        'exit_code' => $result->exitCode(),
                        'stderr' => $result->errorOutput(),
                    ]
                );
            }

            return $output;
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            throw new \App\Exceptions\LLM\TimeoutException(
                'Command execution timed out after '.$this->timeout.' seconds',
                $this->getProvider(),
                $this->model,
                [
                    'timeout' => $this->timeout,
                    'command' => $this->command,
                    'original_exception' => get_class($e),
                ],
                0,
                $e
            );
        } catch (\App\Exceptions\LLM\LLMException $e) {
            // Re-throw our custom exceptions
            throw $e;
        } catch (\Exception $e) {
            throw new \App\Exceptions\LLM\ApiException(
                'Unexpected error executing local command: '.$e->getMessage(),
                $this->getProvider(),
                $this->model,
                [
                    'command' => $this->command,
                    'original_exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Handle command execution failure.
     *
     * Maps command exit codes to appropriate LLMException subtypes.
     * Handles common failure scenarios like missing commands, permission denied,
     * interruptions, and timeouts. Always throws an exception.
     *
     * @param  mixed  $result  The process result object
     * @return never Always throws an exception
     *
     * @throws InvalidRequestException For invalid commands or permission issues
     * @throws TimeoutException For signal-based terminations
     * @throws ApiException For general failures
     */
    protected function handleCommandFailure($result): never
    {
        $exitCode = $result->exitCode();
        $stdout = $result->output();
        $stderr = $result->errorOutput();

        // Determine error type based on exit code and output
        $context = [
            'command' => $this->command,
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'working_directory' => $this->workingDirectory,
        ];

        // Common exit codes and their meanings
        // 1: General error
        // 2: Misuse of shell command
        // 126: Command cannot execute
        // 127: Command not found
        // 130: Script terminated by Ctrl+C
        // 137: Process killed (SIGKILL)
        // 143: Process terminated (SIGTERM)

        if ($exitCode === 127) {
            throw new \App\Exceptions\LLM\InvalidRequestException(
                "Command not found: {$this->command}. Please ensure the command is installed and in your PATH.",
                $this->getProvider(),
                $this->model,
                $context
            );
        } elseif ($exitCode === 126) {
            throw new \App\Exceptions\LLM\InvalidRequestException(
                "Command cannot execute: {$this->command}. Permission denied or not executable.",
                $this->getProvider(),
                $this->model,
                $context
            );
        } elseif ($exitCode === 130) {
            throw new \App\Exceptions\LLM\ApiException(
                'Command was interrupted (Ctrl+C)',
                $this->getProvider(),
                $this->model,
                $context
            );
        } elseif ($exitCode === 137) {
            throw new \App\Exceptions\LLM\TimeoutException(
                'Command was killed (out of memory or forcefully terminated)',
                $this->getProvider(),
                $this->model,
                array_merge($context, ['signal' => 'SIGKILL'])
            );
        } elseif ($exitCode === 143) {
            throw new \App\Exceptions\LLM\TimeoutException(
                'Command was terminated gracefully',
                $this->getProvider(),
                $this->model,
                array_merge($context, ['signal' => 'SIGTERM'])
            );
        } else {
            // Generic command failure
            $errorMessage = "Command failed with exit code {$exitCode}";

            if (! empty($stderr)) {
                $errorMessage .= "\nError: {$stderr}";
            }

            if (! empty($stdout)) {
                $errorMessage .= "\nOutput: {$stdout}";
            }

            throw new \App\Exceptions\LLM\ApiException(
                $errorMessage,
                $this->getProvider(),
                $this->model,
                $context
            );
        }
    }

    /**
     * Build the command to execute.
     *
     * Constructs the final command string by sanitizing the prompt and substituting template
     * placeholders. Supports {prompt} and {model} placeholders in the command template.
     * If no {prompt} placeholder exists, the prompt is appended to the command.
     * All arguments are properly escaped using escapeshellarg() to prevent injection attacks.
     *
     * @return string The fully constructed and escaped command ready for execution
     *
     * @throws \InvalidArgumentException If model name contains shell metacharacters
     */
    protected function buildCommand(): string
    {
        // Sanitize prompt - remove any shell metacharacters
        $sanitizedPrompt = $this->sanitizePrompt($this->prompt);
        $escapedPrompt = escapeshellarg($sanitizedPrompt);

        // Build command based on template
        $command = str_replace('{prompt}', $escapedPrompt, $this->command);

        // If command doesn't have {prompt} placeholder, append it
        if (! str_contains($this->command, '{prompt}')) {
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
     *
     * Removes null bytes and enforces length limits to prevent abuse and command injection attacks.
     * While escapeshellarg() provides the primary defense against injection, sanitization here
     * provides defense-in-depth by removing null bytes and preventing extremely large payloads.
     *
     * @param  string  $prompt  The raw user prompt to sanitize
     * @return string The sanitized prompt safe for shell execution
     *
     * @throws \InvalidArgumentException If prompt exceeds maximum length of 100,000 characters
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
     *
     * Builds a safe environment for the child process by inheriting the user's shell environment
     * while maintaining strict security controls. Only whitelisted environment variables are
     * passed to prevent malicious injection via variables like LD_PRELOAD. Includes support for:
     * - Standard shell variables (PATH, HOME, SHELL, etc.)
     * - Anthropic/Claude Code configuration variables
     * - Language and locale settings
     * - asdf version manager variables
     *
     * Custom environment variables cannot be injected through the options parameter. To add
     * new environment variables, they must be explicitly added to the whitelist in this method.
     * Values exceeding 10,000 characters are silently skipped as potential abuse vectors.
     *
     * @return array Associative array of environment variables safe for the child process
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
     *
     * Returns the provider name used for logging, error handling, and provider-specific behavior.
     * Allows for custom provider naming through options, defaulting to 'local-command' if not specified.
     * This provider name is used to identify the source of the query in error logs and status updates.
     *
     * @return string The provider identifier (typically 'local-command' or a custom name from options)
     */
    protected function getProvider(): string
    {
        return $this->options['provider_name'] ?? 'local-command';
    }
}
