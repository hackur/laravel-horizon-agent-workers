<?php

namespace App\Jobs\LLM\OpenClaw;

use App\Models\AgentRun;
use App\Services\AgentOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator Job
 *
 * Manages multi-agent workflows with iterative review cycles.
 * 
 * Flow:
 * 1. Agent executes task → produces output
 * 2. Reviewer evaluates output → approves or requests changes
 * 3. If changes requested → Agent iterates with feedback
 * 4. Repeat until approved or max iterations reached
 *
 * Usage:
 *   OrchestratorJob::dispatch(
 *       task: 'Refactor the UserController to use DTOs',
 *       workingDirectory: '/path/to/project',
 *       options: [
 *           'agent_model' => 'sonnet',
 *           'reviewer_model' => 'opus',
 *           'max_iterations' => 5,
 *           'session' => 'refactor-123',
 *       ]
 *   );
 */
class OrchestratorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour for full orchestration cycles

    public $tries = 1; // Don't retry orchestration - it manages its own state

    protected string $task;
    protected string $workingDirectory;
    protected array $options;

    /**
     * Create a new Orchestrator job.
     *
     * @param string $task The task description for the agent
     * @param string $workingDirectory Project directory for execution
     * @param array $options Configuration options:
     *   - agent_model: Model for work (default: sonnet)
     *   - reviewer_model: Model for review (default: opus)
     *   - max_iterations: Max review cycles (default: 5)
     *   - session: Session key for context persistence
     */
    public function __construct(
        string $task,
        string $workingDirectory,
        array $options = []
    ) {
        $this->task = $task;
        $this->workingDirectory = $workingDirectory;
        $this->options = $options;
        $this->onQueue('llm-orchestrator');
    }

    /**
     * Execute the orchestration workflow.
     */
    public function handle(AgentOrchestrator $orchestrator): void
    {
        $agentModel = $this->options['agent_model'] ?? config('orchestrator.default_agent_model', 'sonnet');
        $reviewerModel = $this->options['reviewer_model'] ?? config('orchestrator.default_reviewer_model', 'opus');
        $maxIterations = $this->options['max_iterations'] ?? config('orchestrator.default_max_iterations', 5);
        $sessionKey = $this->options['session'] ?? null;

        Log::info('🎭 Orchestrator job starting', [
            'task' => substr($this->task, 0, 100),
            'working_directory' => $this->workingDirectory,
            'agent_model' => $agentModel,
            'reviewer_model' => $reviewerModel,
            'max_iterations' => $maxIterations,
        ]);

        // Create the agent run record
        $agentRun = AgentRun::create([
            'task' => $this->task,
            'working_directory' => $this->workingDirectory,
            'agent_model' => $agentModel,
            'reviewer_model' => $reviewerModel,
            'max_iterations' => $maxIterations,
            'session_key' => $sessionKey,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $orchestrator->run(
                agentRun: $agentRun,
                task: $this->task,
                workingDirectory: $this->workingDirectory,
                agentModel: $agentModel,
                reviewerModel: $reviewerModel,
                maxIterations: $maxIterations,
                sessionKey: $sessionKey,
            );

            $agentRun->update([
                'status' => $result['approved'] ? 'completed' : 'max_iterations_reached',
                'completed_at' => now(),
                'final_output' => $result['output'],
                'iterations_used' => $result['iterations'],
            ]);

            Log::info('🎭 Orchestrator job completed', [
                'agent_run_id' => $agentRun->id,
                'approved' => $result['approved'],
                'iterations' => $result['iterations'],
            ]);

        } catch (\Exception $e) {
            $agentRun->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::error('🎭 Orchestrator job failed', [
                'agent_run_id' => $agentRun->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('🎭 Orchestrator job failed permanently', [
            'task' => substr($this->task, 0, 100),
            'error' => $exception->getMessage(),
        ]);
    }
}
