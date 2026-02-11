<?php

namespace App\Http\Controllers;

use App\Jobs\LLM\OpenClaw\OpenClawJob;
use App\Jobs\LLM\OpenClaw\OrchestratorJob;
use App\Models\AgentRun;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentRunController extends Controller
{
    /**
     * Display a listing of agent runs.
     */
    public function index(Request $request)
    {
        $runs = AgentRun::query()
            ->withCount(['reviews', 'outputs'])
            ->orderByDesc('created_at')
            ->paginate(20);

        // For API requests
        if ($request->wantsJson()) {
            return response()->json([
                'runs' => $runs,
                'stats' => $this->getStats(),
            ]);
        }

        return view('agent-runs.index', [
            'runs' => $runs,
            'stats' => $this->getStats(),
        ]);
    }

    /**
     * Show the form for creating a new agent run.
     */
    public function create()
    {
        return view('agent-runs.create');
    }

    /**
     * Store a newly created agent run (dispatch orchestrator job).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'task' => 'required|string|min:10|max:10000',
            'working_directory' => 'required|string|max:500',
            'agent_model' => 'nullable|string|max:100',
            'reviewer_model' => 'nullable|string|max:100',
            'max_iterations' => 'nullable|integer|min:1|max:20',
            'session' => 'nullable|string|max:100',
            'mode' => 'nullable|in:orchestrator,simple',
        ]);

        $workingDir = $validated['working_directory'];
        
        // Validate working directory exists
        if (!is_dir($workingDir)) {
            return back()->withErrors(['working_directory' => 'Directory does not exist: ' . $workingDir]);
        }

        $mode = $validated['mode'] ?? 'orchestrator';

        // Create the AgentRun record first so it shows in the list
        $agentRun = AgentRun::create([
            'task' => $validated['task'],
            'working_directory' => $workingDir,
            'status' => 'running',
            'agent_model' => $validated['agent_model'] ?? config('orchestrator.default_agent_model', 'sonnet'),
            'reviewer_model' => $validated['reviewer_model'] ?? config('orchestrator.default_reviewer_model', 'opus'),
            'max_iterations' => $validated['max_iterations'] ?? config('orchestrator.default_max_iterations', 5),
            'session_key' => $validated['session'] ?? null,
        ]);

        if ($mode === 'simple') {
            // Simple OpenClaw job without orchestration
            OpenClawJob::dispatch(
                prompt: $validated['task'],
                model: null,
                llmQueryId: null,
                options: [
                    'working_directory' => $workingDir,
                    'session' => $validated['session'] ?? null,
                    'agent_run_id' => $agentRun->id,
                ]
            );

            return redirect()->route('agent-runs.show', $agentRun)
                ->with('success', 'Simple agent job dispatched successfully.');
        }

        // Orchestrator job with review loop
        OrchestratorJob::dispatch(
            task: $validated['task'],
            workingDirectory: $workingDir,
            options: [
                'agent_model' => $agentRun->agent_model,
                'reviewer_model' => $agentRun->reviewer_model,
                'max_iterations' => $agentRun->max_iterations,
                'session' => $validated['session'] ?? null,
                'agent_run_id' => $agentRun->id,
            ]
        );

        return redirect()->route('agent-runs.show', $agentRun)
            ->with('success', 'Orchestrator job dispatched successfully.');
    }

    /**
     * Display the specified agent run.
     */
    public function show(AgentRun $agentRun)
    {
        $agentRun->load(['reviews', 'outputs']);

        if (request()->wantsJson()) {
            return response()->json(['run' => $agentRun]);
        }

        return view('agent-runs.show', [
            'run' => $agentRun,
        ]);
    }

    /**
     * Cancel/delete an agent run.
     */
    public function destroy(AgentRun $agentRun)
    {
        if ($agentRun->isRunning()) {
            $agentRun->update([
                'status' => 'failed',
                'error_message' => 'Cancelled by user',
                'completed_at' => now(),
            ]);
        } else {
            $agentRun->delete();
        }

        return redirect()->route('agent-runs.index')
            ->with('success', 'Agent run handled.');
    }

    /**
     * Get stats for dashboard.
     */
    protected function getStats(): array
    {
        return [
            'total' => AgentRun::count(),
            'running' => AgentRun::where('status', 'running')->count(),
            'completed' => AgentRun::where('status', 'completed')->count(),
            'failed' => AgentRun::where('status', 'failed')->count(),
            'avg_iterations' => round(AgentRun::where('status', 'completed')->avg('iterations_used') ?? 0, 1),
        ];
    }

    /**
     * API endpoint to dispatch a quick job.
     */
    public function dispatch(Request $request)
    {
        $validated = $request->validate([
            'task' => 'required|string|min:5',
            'working_directory' => 'nullable|string',
            'session' => 'nullable|string',
            'thinking' => 'nullable|in:off,minimal,low,medium,high',
        ]);

        $workingDir = $validated['working_directory'] ?? base_path();

        OpenClawJob::dispatch(
            prompt: $validated['task'],
            model: null,
            llmQueryId: null,
            options: [
                'working_directory' => $workingDir,
                'session' => $validated['session'] ?? 'api-' . uniqid(),
                'thinking' => $validated['thinking'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Job dispatched',
        ]);
    }
}
