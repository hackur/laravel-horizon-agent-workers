<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LLMQuery extends Model
{
    use HasFactory;
    protected $table = 'l_l_m_queries';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'provider',
        'model',
        'prompt',
        'response',
        'reasoning_content',
        'status',
        'finish_reason',
        'error',
        'duration_ms',
        'metadata',
        'usage_stats',
        'cost_usd',
        'input_cost_usd',
        'output_cost_usd',
        'pricing_tier',
        'over_budget',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'usage_stats' => 'array',
        'completed_at' => 'datetime',
        'cost_usd' => 'decimal:6',
        'input_cost_usd' => 'decimal:6',
        'output_cost_usd' => 'decimal:6',
        'over_budget' => 'boolean',
    ];

    /**
     * Scope to filter queries with pending status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @return \Illuminate\Database\Eloquent\Builder The filtered query
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter queries with processing status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @return \Illuminate\Database\Eloquent\Builder The filtered query
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to filter queries with completed status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @return \Illuminate\Database\Eloquent\Builder The filtered query
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter queries with failed status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @return \Illuminate\Database\Eloquent\Builder The filtered query
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to filter queries by provider.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @param  string  $provider  The provider name to filter by
     * @return \Illuminate\Database\Eloquent\Builder The filtered query
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Get the user who made this query.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The user relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversation this query belongs to (if any).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The conversation relationship
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Scope to filter queries with cost data.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @return \Illuminate\Database\Eloquent\Builder The filtered query
     */
    public function scopeWithCost($query)
    {
        return $query->whereNotNull('cost_usd');
    }

    /**
     * Scope to filter queries that exceed budget.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @return \Illuminate\Database\Eloquent\Builder The filtered query
     */
    public function scopeOverBudget($query)
    {
        return $query->where('over_budget', true);
    }

    /**
     * Scope to filter queries with cost greater than specified amount.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @param  float  $amount  The minimum cost in USD
     * @return \Illuminate\Database\Eloquent\Builder The filtered query
     */
    public function scopeCostGreaterThan($query, float $amount)
    {
        return $query->where('cost_usd', '>', $amount);
    }

    /**
     * Get formatted cost for display.
     *
     * @param  bool  $showCurrency  Whether to include currency symbol
     * @return string Formatted cost string
     */
    public function getFormattedCostAttribute(bool $showCurrency = true): string
    {
        if (! $this->cost_usd) {
            return $showCurrency ? '$0.00' : '0.00';
        }

        $prefix = $showCurrency ? '$' : '';

        // For very small costs, show more precision
        if ($this->cost_usd < 0.01) {
            return $prefix.number_format((float) $this->cost_usd, 4);
        }

        return $prefix.number_format((float) $this->cost_usd, 2);
    }

    /**
     * Check if this query has cost information.
     *
     * @return bool True if cost data is available
     */
    public function hasCost(): bool
    {
        return $this->cost_usd !== null;
    }

    /**
     * Check if this query is a paid API query (vs free local models).
     *
     * @return bool True if this is a paid API query
     */
    public function isPaidQuery(): bool
    {
        return in_array($this->provider, ['claude', 'openai', 'anthropic']);
    }
}
