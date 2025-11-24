<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LLM Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for LLM providers, cost tracking, and budget limits.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Budget Limit
    |--------------------------------------------------------------------------
    |
    | Set a budget limit for individual LLM queries. If a query exceeds this
    | limit, it will be flagged with over_budget=true and logged as a warning.
    | Set to null to disable budget limits.
    |
    | Value is in USD.
    |
    */

    'budget_limit_usd' => env('LLM_BUDGET_LIMIT_USD', null),

    /*
    |--------------------------------------------------------------------------
    | Monthly Budget Limit
    |--------------------------------------------------------------------------
    |
    | Set a monthly budget limit for all LLM queries. This can be used to
    | track total spending and send alerts when approaching the limit.
    | Set to null to disable monthly budget tracking.
    |
    | Value is in USD.
    |
    */

    'monthly_budget_limit_usd' => env('LLM_MONTHLY_BUDGET_LIMIT_USD', null),

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Enable or disable cost tracking for LLM queries. When enabled, the
    | application will calculate and store costs for each query based on
    | token usage and model pricing.
    |
    */

    'cost_tracking_enabled' => env('LLM_COST_TRACKING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Suppress Budget Warning
    |--------------------------------------------------------------------------
    |
    | Suppress the warning about missing budget limits during environment
    | validation. Set to true if you intentionally don't want to set budget
    | limits or find the warning unnecessary.
    |
    */

    'suppress_budget_warning' => env('LLM_SUPPRESS_BUDGET_WARNING', false),

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The default LLM provider to use when none is specified.
    |
    */

    'default_provider' => env('LLM_DEFAULT_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for each LLM provider.
    |
    */

    'providers' => [
        'claude' => [
            'enabled' => env('CLAUDE_ENABLED', true),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => env('CLAUDE_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
            'max_tokens' => env('CLAUDE_MAX_TOKENS', 1024),
            'temperature' => env('CLAUDE_TEMPERATURE', 1.0),
        ],

        'ollama' => [
            'enabled' => env('OLLAMA_ENABLED', false),
            'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'default_model' => env('OLLAMA_DEFAULT_MODEL', 'llama2'),
        ],

        'lmstudio' => [
            'enabled' => env('LMSTUDIO_ENABLED', false),
            'base_url' => env('LMSTUDIO_BASE_URL', 'http://127.0.0.1:1234'),
            'default_model' => env('LMSTUDIO_DEFAULT_MODEL', null),
        ],

        'local-command' => [
            'enabled' => env('LOCAL_COMMAND_ENABLED', false),
            'command' => env('LOCAL_COMMAND', null),
        ],
    ],

];
