<?php

namespace App\Services;

use App\Models\AgentRun;
use App\Models\AgentReview;
use App\Models\AgentOutput;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Agent Orchestrator Service
 *
 * Manages iterative agent workflows with LLM-powered review cycles.
 *
 * Architecture:
 * ┌─────────────────────────────────────────────────────────────┐
 * │                    Orchestrator                              │
 * │  ┌─────────┐     ┌──────────┐     ┌─────────────────────┐  │
 * │  │  Agent  │────▶│  Output  │────▶│     Reviewer        │  │
 * │  │ (work)  │     │          │     │ (evaluate/approve)  │  │
 * │  └─────────┘     └──────────┘     └─────────────────────┘  │
 * │       ▲                                      │              │
 * │       │              ┌───────────────────────┘              │
 * │       │              ▼                                      │
 * │       │     ┌──────────────────┐                           │
 * │       └─────│ Feedback/Changes │                           │
 * │             └──────────────────┘                           │
 * └─────────────────────────────────────────────────────────────┘
 */
class AgentOrchestrator
{
    protected string $openclawPath;
    protected int $commandTimeout;

    public function __construct()
    {
        $this->openclawPath = config('orchestrator.openclaw_path', 'openclaw');
        $this->commandTimeout = config('orchestrator.command_timeout', 600);
    }

    /**
     * Run the orchestration workflow.
     */
    public function run(
        AgentRun $agentRun,
        string $task,
        string $workingDirectory,
        string $agentModel = 'sonnet',
        string $reviewerModel = 'opus',
        int $maxIterations = 5,
        ?string $sessionKey = null,
    ): array {
        $iteration = 0;
        $approved = false;
        $output = '';
        $feedback = '';

        Log::info('🎭 Orchestrator starting', [
            'agent_run_id' => $agentRun->id,
            'task' => substr($task, 0, 100),
            'agent_model' => $agentModel,
            'reviewer_model' => $reviewerModel,
            'max_iterations' => $maxIterations,
        ]);

        while ($iteration < $maxIterations && !$approved) {
            $iteration++;

            Log::info("🔄 Iteration {$iteration}/{$maxIterations}", [
                'agent_run_id' => $agentRun->id,
            ]);

            // Step 1: Agent executes task (with feedback if not first iteration)
            $agentPrompt = $this->buildAgentPrompt($task, $feedback, $iteration);
            $output = $this->runAgent($agentPrompt, $workingDirectory, $agentModel, $sessionKey);

            // Store agent output
            AgentOutput::create([
                'agent_run_id' => $agentRun->id,
                'iteration' => $iteration,
                'type' => 'agent',
                'content' => $output,
                'model' => $agentModel,
                'tokens_used' => $this->estimateTokens($output),
            ]);

            // Step 2: Reviewer evaluates output
            $reviewResult = $this->runReview($task, $output, $workingDirectory, $reviewerModel);

            // Store review
            AgentReview::create([
                'agent_run_id' => $agentRun->id,
                'iteration' => $iteration,
                'approved' => $reviewResult['approved'],
                'feedback' => $reviewResult['feedback'],
                'score' => $reviewResult['score'] ?? null,
                'model' => $reviewerModel,
            ]);

            $approved = $reviewResult['approved'];
            $feedback = $reviewResult['feedback'];

            Log::info("📋 Review complete", [
                'agent_run_id' => $agentRun->id,
                'iteration' => $iteration,
                'approved' => $approved,
                'score' => $reviewResult['score'] ?? 'N/A',
            ]);

            if ($approved) {
                Log::info("✅ Task approved after {$iteration} iteration(s)", [
                    'agent_run_id' => $agentRun->id,
                ]);
            }
        }

        if (!$approved) {
            Log::warning("⚠️ Max iterations reached without approval", [
                'agent_run_id' => $agentRun->id,
                'iterations' => $iteration,
            ]);
        }

        return [
            'output' => $output,
            'approved' => $approved,
            'iterations' => $iteration,
        ];
    }

    /**
     * Build the agent prompt with optional feedback from previous iteration.
     */
    protected function buildAgentPrompt(string $task, string $feedback, int $iteration): string
    {
        if ($iteration === 1 || empty($feedback)) {
            return $task;
        }

        return <<<PROMPT
# Task
{$task}

# Feedback from Previous Iteration
The reviewer provided the following feedback on your previous attempt. Please address these points:

{$feedback}

# Instructions
Please revise your work based on the feedback above. Focus on addressing each point raised by the reviewer.
PROMPT;
    }

    /**
     * Run the agent with OpenClaw CLI.
     */
    protected function runAgent(
        string $prompt,
        string $workingDirectory,
        string $model,
        ?string $sessionKey
    ): string {
        $command = $this->buildOpenClawCommand($prompt, $model, $sessionKey, false);

        Log::debug('Running agent command', [
            'working_directory' => $workingDirectory,
            'model' => $model,
            'command' => $command,
        ]);

        $result = Process::timeout($this->commandTimeout)
            ->path($workingDirectory)
            ->env($this->getEnvironment())
            ->run($command);

        if ($result->failed()) {
            $error = $result->errorOutput() ?: $result->output();
            throw new \RuntimeException(
                "Agent execution failed (exit code {$result->exitCode()}): " . substr($error, 0, 500)
            );
        }

        $rawOutput = $result->output();

        if (empty(trim($rawOutput))) {
            throw new \RuntimeException('Agent returned empty output');
        }

        // Parse JSON response from --json flag
        return $this->parseOpenClawResponse($rawOutput);
    }

    /**
     * Run the reviewer with OpenClaw CLI.
     */
    protected function runReview(
        string $originalTask,
        string $agentOutput,
        string $workingDirectory,
        string $reviewerModel
    ): array {
        $reviewPrompt = $this->buildReviewPrompt($originalTask, $agentOutput);
        $command = $this->buildOpenClawCommand($reviewPrompt, $reviewerModel, null, true);

        Log::debug('Running reviewer command', [
            'working_directory' => $workingDirectory,
            'model' => $reviewerModel,
            'command' => $command,
        ]);

        $result = Process::timeout($this->commandTimeout)
            ->path($workingDirectory)
            ->env($this->getEnvironment())
            ->run($command);

        if ($result->failed()) {
            $error = $result->errorOutput() ?: $result->output();
            throw new \RuntimeException(
                "Review execution failed (exit code {$result->exitCode()}): " . substr($error, 0, 500)
            );
        }

        $rawOutput = $result->output();
        $reviewText = $this->parseOpenClawResponse($rawOutput);
        
        return $this->parseReviewOutput($reviewText);
    }

    /**
     * Parse OpenClaw JSON response to extract text content.
     */
    protected function parseOpenClawResponse(string $rawOutput): string
    {
        // Try to parse as JSON first (when using --json flag)
        $data = json_decode($rawOutput, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['payloads'][0]['text'])) {
            return $data['payloads'][0]['text'];
        }

        // If not JSON, return raw output (might be plain text mode)
        return $rawOutput;
    }

    /**
     * Build the review prompt for the reviewer model.
     */
    protected function buildReviewPrompt(string $task, string $output): string
    {
        return <<<PROMPT
# Code Review Task

You are a senior engineer reviewing work from another AI agent. Your job is to evaluate the quality and completeness of their work.

## Original Task
{$task}

## Agent's Output
{$output}

## Review Instructions

Please evaluate the work and respond in the following JSON format:

```json
{
    "approved": true,
    "score": 8,
    "feedback": "Good work. Minor suggestions: ..."
}
```

Or if changes are needed:

```json
{
    "approved": false,
    "score": 5,
    "feedback": "Needs improvement: 1) ... 2) ... 3) ..."
}
```

Evaluation criteria:
1. **Correctness**: Does the code work? Are there bugs?
2. **Completeness**: Does it fully address the task?
3. **Code Quality**: Is it clean, readable, well-structured?
4. **Best Practices**: Does it follow conventions and patterns?
5. **Edge Cases**: Are edge cases handled?

Be constructive but thorough. If you approve, the work ships. If you reject, be specific about what needs to change.
PROMPT;
    }

    /**
     * Parse the reviewer's JSON output.
     */
    protected function parseReviewOutput(string $output): array
    {
        // Try to extract JSON from the output
        if (preg_match('/```json\s*(.*?)\s*```/s', $output, $matches)) {
            $json = $matches[1];
        } elseif (preg_match('/\{[^{}]*"approved"\s*:\s*(true|false)[^{}]*\}/s', $output, $matches)) {
            $json = $matches[0];
        } else {
            // Fallback: look for approval keywords
            $lowerOutput = strtolower($output);
            $hasApproval = str_contains($lowerOutput, 'approved') || 
                           str_contains($lowerOutput, 'lgtm') ||
                           str_contains($lowerOutput, 'looks good');
            $hasRejection = str_contains($lowerOutput, 'needs improvement') ||
                            str_contains($lowerOutput, 'changes needed') ||
                            str_contains($lowerOutput, 'rejected');

            Log::warning('Could not parse review JSON, using keyword detection', [
                'has_approval' => $hasApproval,
                'has_rejection' => $hasRejection,
            ]);

            return [
                'approved' => $hasApproval && !$hasRejection,
                'feedback' => $output,
                'score' => null,
            ];
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Invalid JSON in review output', [
                'json' => substr($json, 0, 200),
                'error' => json_last_error_msg(),
            ]);
            return [
                'approved' => false,
                'feedback' => 'Review completed but JSON parsing failed. Output: ' . substr($output, 0, 500),
                'score' => null,
            ];
        }

        return [
            'approved' => (bool) ($data['approved'] ?? false),
            'feedback' => $data['feedback'] ?? '',
            'score' => isset($data['score']) ? (int) $data['score'] : null,
        ];
    }

    /**
     * Build the OpenClaw CLI command.
     * 
     * Uses `openclaw agent --local` for local execution without channel routing.
     * The --local flag runs the embedded agent with model provider API keys from shell.
     */
    protected function buildOpenClawCommand(
        string $prompt, 
        string $model, 
        ?string $sessionKey,
        bool $isReviewer = false
    ): string {
        $parts = [$this->openclawPath, 'agent', '--local', '--json'];

        // Generate a unique session ID if not specified
        $session = $sessionKey ?? 'orchestrator-' . uniqid();
        $parts[] = '--session-id';
        $parts[] = escapeshellarg($session);

        // Use thinking for the reviewer (more thorough)
        if ($isReviewer) {
            $thinkingLevel = config('orchestrator.reviewer_thinking', 'medium');
            $parts[] = '--thinking';
            $parts[] = escapeshellarg($thinkingLevel);
        }

        // Add the message
        $parts[] = '-m';
        $parts[] = escapeshellarg($prompt);

        return implode(' ', $parts);
    }

    /**
     * Get environment variables for command execution.
     */
    protected function getEnvironment(): array
    {
        $env = [];

        // Inherit key environment variables
        $inherit = ['PATH', 'HOME', 'USER', 'SHELL', 'LANG', 'LC_ALL', 'TERM'];
        foreach ($inherit as $var) {
            if ($value = getenv($var)) {
                $env[$var] = $value;
            }
        }

        return $env;
    }

    /**
     * Estimate token count from text.
     */
    protected function estimateTokens(string $text): int
    {
        // Rough estimate: ~4 characters per token
        return (int) ceil(strlen($text) / 4);
    }
}
