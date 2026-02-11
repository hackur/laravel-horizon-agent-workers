# OpenClaw Integration

This document describes the OpenClaw integration for the Laravel Horizon Agent Workers project.

## Overview

OpenClaw is integrated as both a direct CLI executor and as the backbone for the **Agent Orchestrator** — a multi-agent workflow system with iterative review cycles.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Laravel Horizon                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────┐ │
│  │  llm-openclaw    │  │  llm-orchestrator │  │  llm-claude/ollama  │ │
│  │     Queue        │  │       Queue       │  │       Queues        │ │
│  └────────┬─────────┘  └────────┬──────────┘  └──────────────────────┘ │
│           │                      │                                      │
│           ▼                      ▼                                      │
│  ┌──────────────────┐  ┌──────────────────────────────────────────┐   │
│  │   OpenClawJob    │  │          OrchestratorJob                 │   │
│  │  (single task)   │  │                                          │   │
│  └──────────────────┘  │  ┌──────────────────────────────────┐   │   │
│                        │  │       AgentOrchestrator          │   │   │
│                        │  │                                  │   │   │
│                        │  │  ┌─────────┐    ┌─────────────┐ │   │   │
│                        │  │  │  Agent  │───▶│  Reviewer   │ │   │   │
│                        │  │  │ (work)  │◀───│ (evaluate)  │ │   │   │
│                        │  │  └─────────┘    └─────────────┘ │   │   │
│                        │  └──────────────────────────────────┘   │   │
│                        └──────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
                          ┌─────────────────┐
                          │   OpenClaw CLI  │
                          │  openclaw invoke │
                          └─────────────────┘
```

## Components

### 1. OpenClawJob

Direct execution of OpenClaw CLI commands via `openclaw agent --local`.

```php
use App\Jobs\LLM\OpenClaw\OpenClawJob;

// Simple task
OpenClawJob::dispatch(
    prompt: 'Explain the SOLID principles',
    model: null,  // Uses config default
);

// With options
OpenClawJob::dispatch(
    prompt: 'Refactor this function to be more efficient',
    model: null,
    llmQueryId: null,
    options: [
        'working_directory' => '/path/to/project',
        'thinking' => 'medium',
        'session' => 'refactor-session-123',
    ]
);
```

**Note:** Model selection is handled by OpenClaw's configuration. The `--local` flag runs the embedded agent using API keys from your environment.

### 2. OrchestratorJob

Multi-agent workflow with iterative review cycles.

```php
use App\Jobs\LLM\OpenClaw\OrchestratorJob;

OrchestratorJob::dispatch(
    task: 'Add validation to the UserController store method',
    workingDirectory: '/path/to/laravel-project',
    options: [
        'agent_model' => 'sonnet',      // Fast model for work
        'reviewer_model' => 'opus',      // Thorough model for review
        'max_iterations' => 5,
        'session' => 'validation-task',
    ]
);
```

### 3. AgentOrchestrator Service

The core orchestration logic. Can be used directly for synchronous workflows:

```php
use App\Services\AgentOrchestrator;
use App\Models\AgentRun;

$orchestrator = app(AgentOrchestrator::class);

$agentRun = AgentRun::create([
    'task' => 'Fix the N+1 query in User::with("posts")',
    'working_directory' => '/path/to/project',
    'agent_model' => 'sonnet',
    'reviewer_model' => 'opus',
    'max_iterations' => 3,
    'status' => 'running',
    'started_at' => now(),
]);

$result = $orchestrator->run(
    agentRun: $agentRun,
    task: $agentRun->task,
    workingDirectory: $agentRun->working_directory,
    agentModel: $agentRun->agent_model,
    reviewerModel: $agentRun->reviewer_model,
    maxIterations: $agentRun->max_iterations,
);

// $result = ['output' => '...', 'approved' => true, 'iterations' => 2]
```

## Database Models

### AgentRun

Tracks the overall orchestration workflow.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| task | text | The task description |
| working_directory | string | Project directory |
| agent_model | string | Model for task execution |
| reviewer_model | string | Model for review |
| max_iterations | int | Maximum review cycles |
| iterations_used | int | Actual iterations used |
| status | enum | running, completed, failed, max_iterations_reached |
| final_output | longtext | Final approved output |
| error_message | text | Error if failed |
| started_at | timestamp | When run started |
| completed_at | timestamp | When run finished |

### AgentReview

Stores each review iteration.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| agent_run_id | bigint | Foreign key to AgentRun |
| iteration | int | Iteration number (1, 2, 3...) |
| approved | boolean | Whether output was approved |
| feedback | text | Reviewer's feedback |
| score | tinyint | Quality score 1-10 |
| model | string | Reviewer model used |

### AgentOutput

Stores agent output from each iteration.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| agent_run_id | bigint | Foreign key to AgentRun |
| iteration | int | Iteration number |
| type | enum | agent or reviewer |
| content | longtext | Full output |
| model | string | Model used |
| tokens_used | int | Estimated token count |

## Configuration

### Environment Variables

```env
# OpenClaw CLI path
OPENCLAW_PATH=openclaw

# Orchestrator defaults
ORCHESTRATOR_COMMAND_TIMEOUT=600
ORCHESTRATOR_AGENT_MODEL=sonnet
ORCHESTRATOR_REVIEWER_MODEL=opus
ORCHESTRATOR_MAX_ITERATIONS=5
ORCHESTRATOR_REVIEWER_THINKING=medium
```

### Horizon Queues

Two new queues are configured:

- `llm-openclaw` — For direct OpenClaw tasks (30 min timeout)
- `llm-orchestrator` — For orchestration workflows (1 hour timeout)

## Workflow Example

Here's what happens when you dispatch an OrchestratorJob:

1. **Iteration 1**
   - Agent receives task: "Add validation to UserController"
   - Agent executes via OpenClaw CLI
   - Output stored in `agent_outputs`
   - Reviewer evaluates output
   - Reviewer returns: `{approved: false, score: 6, feedback: "Missing email validation"}`

2. **Iteration 2**
   - Agent receives task + feedback
   - Agent revises work addressing feedback
   - Output stored
   - Reviewer evaluates
   - Reviewer returns: `{approved: true, score: 9, feedback: "LGTM!"}`

3. **Completion**
   - Final output stored in `agent_runs.final_output`
   - Status set to `completed`
   - Job finishes

## Monitoring

### Via Horizon Dashboard

Visit `/horizon` to monitor:
- Active orchestrator jobs
- Queue depths for `llm-openclaw` and `llm-orchestrator`
- Failed jobs and retry attempts

### Via Database

```php
// Recent runs
AgentRun::latest()->take(10)->get();

// Failed runs
AgentRun::where('status', 'failed')->get();

// Average iterations to approval
AgentRun::where('status', 'completed')
    ->avg('iterations_used');
```

## Running Migrations

```bash
php artisan migrate
```

This creates:
- `agent_runs` — Main workflow tracking
- `agent_reviews` — Review history
- `agent_outputs` — Output history

## Best Practices

1. **Model Selection**
   - Use `sonnet` for agent tasks (fast, capable)
   - Use `opus` for reviews (thorough, catches edge cases)

2. **Iteration Limits**
   - Start with `max_iterations: 3` for simple tasks
   - Use `max_iterations: 5-7` for complex refactoring
   - Higher values = more thorough but more expensive

3. **Sessions**
   - Use sessions for multi-step workflows
   - Session maintains context across iterations
   - Name sessions descriptively: `refactor-user-auth-2026-02`

4. **Working Directory**
   - Always specify the project root
   - Ensure the directory is accessible by the queue worker
   - Avoid system directories

## Troubleshooting

### "openclaw: command not found"

Ensure OpenClaw is installed and in PATH:
```bash
which openclaw
# or set OPENCLAW_PATH=/full/path/to/openclaw
```

### Timeouts

Increase timeouts in `config/orchestrator.php`:
```php
'command_timeout' => 900,  // 15 minutes
```

### Max Iterations Reached

If tasks consistently hit max iterations:
- Check if the task is too broad
- Consider breaking into smaller sub-tasks
- Increase max_iterations for complex tasks
