<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent Output Model
 *
 * Stores the output from each iteration of an agent run.
 *
 * @property int $id
 * @property int $agent_run_id
 * @property int $iteration
 * @property string $type agent|reviewer
 * @property string $content The full output content
 * @property string $model The model used
 * @property int|null $tokens_used Estimated token count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AgentOutput extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_run_id',
        'iteration',
        'type',
        'content',
        'model',
        'tokens_used',
    ];

    protected $casts = [
        'iteration' => 'integer',
        'tokens_used' => 'integer',
    ];

    /**
     * Get the agent run this output belongs to.
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * Get the corresponding review for this output (if type is 'agent').
     */
    public function review(): ?AgentReview
    {
        return AgentReview::where('agent_run_id', $this->agent_run_id)
            ->where('iteration', $this->iteration)
            ->first();
    }

    /**
     * Get a truncated preview of the content.
     */
    public function getPreviewAttribute(): string
    {
        $content = strip_tags($this->content);
        if (strlen($content) <= 200) {
            return $content;
        }
        return substr($content, 0, 200) . '...';
    }

    /**
     * Estimate token count if not set.
     */
    public function getEstimatedTokensAttribute(): int
    {
        if ($this->tokens_used) {
            return $this->tokens_used;
        }

        // Rough estimate: ~4 chars per token
        return (int) ceil(strlen($this->content) / 4);
    }
}
