<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenClaw Path
    |--------------------------------------------------------------------------
    |
    | The path to the openclaw CLI binary. Can be just 'openclaw' if it's
    | in your PATH, or a full path like '/usr/local/bin/openclaw'.
    |
    */
    'openclaw_path' => env('OPENCLAW_PATH', 'openclaw'),

    /*
    |--------------------------------------------------------------------------
    | Command Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds for a single agent or reviewer command to run.
    | Increase this for complex tasks that take longer.
    |
    */
    'command_timeout' => env('ORCHESTRATOR_COMMAND_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Default Agent Model
    |--------------------------------------------------------------------------
    |
    | The default model for agent task execution. Use a fast, capable model.
    | Options: sonnet, opus, haiku, or full model identifiers.
    |
    */
    'default_agent_model' => env('ORCHESTRATOR_AGENT_MODEL', 'sonnet'),

    /*
    |--------------------------------------------------------------------------
    | Default Reviewer Model
    |--------------------------------------------------------------------------
    |
    | The default model for reviewing agent output. Use a more capable model
    | for thorough evaluation. opus recommended for code review.
    |
    */
    'default_reviewer_model' => env('ORCHESTRATOR_REVIEWER_MODEL', 'opus'),

    /*
    |--------------------------------------------------------------------------
    | Default Max Iterations
    |--------------------------------------------------------------------------
    |
    | Maximum number of agent-review cycles before giving up.
    | Each iteration uses tokens, so balance thoroughness vs cost.
    |
    */
    'default_max_iterations' => env('ORCHESTRATOR_MAX_ITERATIONS', 5),

    /*
    |--------------------------------------------------------------------------
    | Horizon Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which Horizon queue handles orchestrator jobs.
    |
    */
    'queue' => env('ORCHESTRATOR_QUEUE', 'llm-orchestrator'),

    /*
    |--------------------------------------------------------------------------
    | Thinking Level
    |--------------------------------------------------------------------------
    |
    | Default thinking level for reviewer. Higher = more thorough.
    | Options: none, low, medium, high
    |
    */
    'reviewer_thinking' => env('ORCHESTRATOR_REVIEWER_THINKING', 'medium'),

];
