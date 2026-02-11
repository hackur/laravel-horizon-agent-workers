<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent Review Model
 *
 * Represents a review of an agent's output by the reviewer model.
 *
 * @property int $id
 * @property int $agent_run_id
 * @property int $iteration
 * @property bool $approved
 * @property string|null $feedback
 * @property int|null $score 1-10 quality score
 * @property string $model The reviewer model used
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AgentReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_run_id',
        'iteration',
        'approved',
        'feedback',
        'score',
        'model',
    ];

    protected $casts = [
        'iteration' => 'integer',
        'approved' => 'boolean',
        'score' => 'integer',
    ];

    /**
     * Get the agent run this review belongs to.
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * Get the corresponding agent output for this review's iteration.
     */
    public function agentOutput(): ?AgentOutput
    {
        return AgentOutput::where('agent_run_id', $this->agent_run_id)
            ->where('iteration', $this->iteration)
            ->where('type', 'agent')
            ->first();
    }

    /**
     * Get a human-readable status.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->approved ? '✅ Approved' : '🔄 Changes Requested';
    }

    /**
     * Get the score as a visual representation.
     */
    public function getScoreDisplayAttribute(): string
    {
        if ($this->score === null) {
            return 'N/A';
        }

        $filled = str_repeat('★', $this->score);
        $empty = str_repeat('☆', 10 - $this->score);

        return "{$filled}{$empty} ({$this->score}/10)";
    }
}
