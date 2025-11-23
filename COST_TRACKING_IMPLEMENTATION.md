# Cost Tracking Implementation

This document outlines the comprehensive cost tracking system implemented for paid API providers in the Laravel Horizon LLM Agent Workers application.

## Overview

The cost tracking system automatically calculates and stores costs for LLM API queries based on token usage and model-specific pricing. It supports multiple providers and pricing tiers, with built-in budget alerts and comprehensive analytics.

## Features Implemented

### 1. Database Schema Changes

**Migration**: `2025_11_23_202342_add_cost_tracking_to_llm_queries_table.php`

Added the following fields to the `l_l_m_queries` table:
- `cost_usd` (decimal): Total cost in USD
- `input_cost_usd` (decimal): Cost for input tokens
- `output_cost_usd` (decimal): Cost for output tokens
- `pricing_tier` (string): Model pricing tier (e.g., 'opus', 'sonnet', 'haiku')
- `over_budget` (boolean): Flag for queries exceeding budget limits

**Indexes**:
- `cost_usd`: For efficient cost queries
- `provider, cost_usd`: For provider-specific cost analysis

### 2. Cost Calculator Service

**File**: `app/Services/CostCalculator.php`

A comprehensive service for calculating LLM API costs with the following features:

#### Current Pricing Support

**Claude API (Anthropic)**:
- **Claude 3.5 Sonnet**: $3/MTok input, $15/MTok output
- **Claude 3.5 Haiku**: $0.80/MTok input, $4/MTok output
- **Claude 3 Opus**: $15/MTok input, $75/MTok output
- **Claude 3 Sonnet (older)**: $3/MTok input, $15/MTok output
- **Claude 3 Haiku (older)**: $0.25/MTok input, $1.25/MTok output

#### Key Methods

```php
// Calculate cost based on usage statistics
calculateCost(string $provider, ?string $model, array $usageStats): array

// Get pricing information for a model
getPricing(string $provider, ?string $model = null): ?array

// Estimate cost before making a query
estimateCost(string $provider, ?string $model, int $estimatedInputTokens, int $estimatedOutputTokens): array

// Format cost for display
formatCost(float $cost, bool $showCurrency = true): string

// Check if cost exceeds budget
exceedsBudget(float $cost, float $budget): bool

// Get all supported providers
getSupportedProviders(): array
```

### 3. Model Updates

**File**: `app/Models/LLMQuery.php`

Added cost-related functionality:

#### New Fillable Fields
- `cost_usd`, `input_cost_usd`, `output_cost_usd`
- `pricing_tier`, `over_budget`

#### New Casts
- Cost fields cast to `decimal:6` for precision
- `over_budget` cast to `boolean`

#### New Scopes
```php
withCost()           // Filter queries with cost data
overBudget()         // Filter queries exceeding budget
costGreaterThan($amount) // Filter by minimum cost
```

#### New Methods
```php
getFormattedCostAttribute(): string  // Get formatted cost string
hasCost(): bool                       // Check if cost data exists
isPaidQuery(): bool                   // Check if provider is paid API
```

### 4. Job Updates

#### BaseLLMJob (`app/Jobs/LLM/BaseLLMJob.php`)

Enhanced to automatically calculate costs when usage stats are available:

```php
// Automatic cost calculation after query completion
if (isset($updateData['usage_stats'])) {
    $costData = $costCalculator->calculateCost(
        $this->getProvider(),
        $this->model,
        $updateData['usage_stats']
    );

    // Store cost data
    $updateData['cost_usd'] = $costData['total_cost_usd'];
    $updateData['input_cost_usd'] = $costData['input_cost_usd'];
    $updateData['output_cost_usd'] = $costData['output_cost_usd'];
    $updateData['pricing_tier'] = $costData['pricing_tier'];

    // Budget check with logging
    if ($budgetLimit && $costData['total_cost_usd'] > $budgetLimit) {
        $updateData['over_budget'] = true;
        Log::warning('LLM query exceeded budget limit', [...]);
    }
}
```

#### ClaudeQueryJob (`app/Jobs/LLM/Claude/ClaudeQueryJob.php`)

Updated to capture usage statistics from Claude API responses:

```php
// Capture usage statistics for cost calculation
if (isset($result->usage)) {
    $this->additionalMetadata['usage_stats'] = [
        'input_tokens' => $result->usage->inputTokens ?? 0,
        'output_tokens' => $result->usage->outputTokens ?? 0,
        'total_tokens' => (...),
    ];
}

// Capture finish reason
if (isset($result->stopReason)) {
    $this->additionalMetadata['finish_reason'] = $result->stopReason;
}
```

### 5. Conversation Service Updates

**File**: `app/Services/ConversationService.php`

Enhanced `getStatistics()` method to include cost aggregation:

```php
// Cost statistics query
$costStats = DB::table('l_l_m_queries')
    ->select([
        DB::raw('SUM(...) as total_cost_usd'),
        DB::raw('SUM(...) as total_input_cost_usd'),
        DB::raw('SUM(...) as total_output_cost_usd'),
        DB::raw('AVG(...) as avg_cost_usd'),
        DB::raw('MAX(...) as max_cost_usd'),
        DB::raw('COUNT(...) as over_budget_count'),
    ])
    ->where('conversation_id', $conversation->id)
    ->first();
```

**New Statistics Returned**:
- `total_cost_usd`: Total cost across all queries
- `total_input_cost_usd`: Total input token costs
- `total_output_cost_usd`: Total output token costs
- `avg_cost_usd`: Average cost per query
- `max_cost_usd`: Most expensive single query
- `over_budget_count`: Number of queries exceeding budget

### 6. Cost Tracking Dashboard

**Controller**: `app/Http/Controllers/CostController.php`
**View**: `resources/views/costs/index.blade.php`
**Route**: `/costs`

A comprehensive dashboard featuring:

#### Overview Statistics
- Total cost across date range
- Total number of queries
- Average cost per query
- Maximum single query cost
- Over-budget query count

#### Cost Breakdown
- **By Provider**: Cost analysis grouped by LLM provider
- **By Model**: Top 10 models by total cost with pricing tier badges
- **Daily Trend**: Interactive Chart.js line graph showing daily costs
- **Most Expensive Queries**: Table of the 10 most expensive queries with links to conversations

#### Budget Alerts
- Red alert when budget is exceeded
- Yellow warning when 80% of budget is used
- Visual budget progress indicator

#### Date Range Filtering
- Customizable date range selection
- Defaults to last 30 days
- Persistent filter across page navigation

### 7. Conversation View Enhancements

**File**: `resources/views/conversations/show.blade.php`

#### Statistics Panel
Added conversation-level statistics panel showing:
- Total queries
- Total tokens (input/output breakdown)
- Total cost with average
- Average response time
- Budget warnings if applicable

#### Message Metadata
Enhanced message cards to display:
- Cost per query with dollar icon
- Pricing tier badge (color-coded by tier)
- Token usage
- Duration
- Finish reason

**Color Coding**:
- **Opus**: Purple (highest cost)
- **Sonnet**: Blue (medium cost)
- **Haiku**: Teal (lowest cost)

### 8. Navigation

Added "Costs" link to main navigation menu:
- Desktop navigation
- Responsive mobile navigation
- Active state highlighting

### 9. Configuration

**File**: `config/llm.php`

New configuration file for LLM settings:

```php
'budget_limit_usd' => env('LLM_BUDGET_LIMIT_USD', null),
'monthly_budget_limit_usd' => env('LLM_MONTHLY_BUDGET_LIMIT_USD', null),
'cost_tracking_enabled' => env('LLM_COST_TRACKING_ENABLED', true),
'default_provider' => env('LLM_DEFAULT_PROVIDER', 'claude'),
```

### 10. Routes

Added cost tracking routes:

```php
Route::get('/costs', [CostController::class, 'index'])->name('costs.index');
Route::get('/costs/stats', [CostController::class, 'stats'])->name('costs.stats');
```

## Environment Variables

Add these to your `.env` file:

```env
# Budget Limits (optional)
LLM_BUDGET_LIMIT_USD=1.00           # Per-query budget limit
LLM_MONTHLY_BUDGET_LIMIT_USD=100.00 # Monthly total budget limit

# Cost Tracking
LLM_COST_TRACKING_ENABLED=true

# LLM Provider Settings
LLM_DEFAULT_PROVIDER=claude
```

## Usage Examples

### Viewing Cost Dashboard

1. Navigate to `/costs` in the application
2. Select a date range to filter costs
3. View breakdown by provider, model, and daily trends
4. Check budget alerts and warnings

### Viewing Conversation Costs

1. Open any conversation
2. Statistics panel shows total cost for the conversation
3. Each message displays its individual cost
4. Pricing tier badges indicate model used

### Cost Calculation Flow

1. User submits query to Claude API
2. `ClaudeQueryJob` captures usage stats from response
3. `BaseLLMJob` calls `CostCalculator` service
4. Costs are calculated based on token usage and model pricing
5. Cost data is stored in database with query
6. Budget limits are checked and violations logged
7. UI displays cost information in real-time

## Database Queries

### Get Total Costs by Provider

```sql
SELECT
    provider,
    COUNT(*) as query_count,
    SUM(cost_usd) as total_cost,
    AVG(cost_usd) as avg_cost
FROM l_l_m_queries
WHERE user_id = ? AND status = 'completed'
GROUP BY provider
ORDER BY total_cost DESC;
```

### Get Over-Budget Queries

```sql
SELECT *
FROM l_l_m_queries
WHERE user_id = ?
  AND over_budget = 1
ORDER BY created_at DESC;
```

### Get Monthly Cost Totals

```sql
SELECT
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(cost_usd) as monthly_cost
FROM l_l_m_queries
WHERE user_id = ? AND status = 'completed'
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month DESC;
```

## Future Enhancements

Potential improvements for the cost tracking system:

1. **Additional Providers**
   - Add pricing for OpenAI GPT models
   - Add pricing for Google Gemini
   - Add pricing for other providers

2. **Budget Management**
   - User-specific budget limits
   - Team budget allocation
   - Automated budget alerts via email
   - Budget forecasting based on usage trends

3. **Advanced Analytics**
   - Cost per conversation type
   - ROI analysis for different models
   - Cost optimization recommendations
   - Usage pattern analysis

4. **Export Features**
   - Export cost reports as CSV/Excel
   - Scheduled cost reports
   - Integration with accounting systems

5. **Real-time Cost Preview**
   - Estimate cost before sending query
   - Display estimated cost in UI
   - Warn users of expensive queries

6. **Cost Optimization**
   - Automatic model selection based on budget
   - Caching of expensive queries
   - Token usage optimization suggestions

## Testing

To test the cost tracking system:

1. **Create a test query**
   ```bash
   php artisan tinker

   $query = App\Models\LLMQuery::create([
       'user_id' => 1,
       'provider' => 'claude',
       'model' => 'claude-3-5-sonnet-20241022',
       'prompt' => 'Test prompt',
       'status' => 'completed',
       'usage_stats' => [
           'input_tokens' => 100,
           'output_tokens' => 500,
           'total_tokens' => 600
       ]
   ]);

   // Manually trigger cost calculation
   $calculator = new App\Services\CostCalculator();
   $costData = $calculator->calculateCost('claude', $query->model, $query->usage_stats);
   $query->update($costData);
   ```

2. **Verify cost dashboard**
   - Visit `/costs`
   - Verify statistics are displayed
   - Check that charts render correctly

3. **Test budget limits**
   - Set `LLM_BUDGET_LIMIT_USD=0.001` in `.env`
   - Create a query
   - Verify over_budget flag is set
   - Check warning appears in logs

## Support

For issues or questions about the cost tracking system:

1. Check the logs: `storage/logs/laravel.log`
2. Verify migration ran: `php artisan migrate:status`
3. Check cost calculator pricing: Review `app/Services/CostCalculator.php`
4. Ensure environment variables are set correctly

## License

This cost tracking implementation is part of the Laravel Horizon LLM Agent Workers application.
