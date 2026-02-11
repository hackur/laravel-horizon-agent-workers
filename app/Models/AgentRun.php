<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agent Run Model
 *
 * Represents a single orchestrated agent workflow execution.
 *
 * @property int $id
 * @property string $task
 * @property string $working_directory
 * @property string $agent_model
 * @property string $reviewer_model
 * @property int $max_iterations
 * @property int|null $iterations_used
 * @property string|null $session_key
 * @property string $status running|completed|failed|max_iterations_reached
 * @property string|null $final_output
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AgentRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'task',
        'working_directory',
        'agent_model',
        'reviewer_model',
        'max_iterations',
        'iterations_used',
        'session_key',
        'status',
        'final_output',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'max_iterations' => 'integer',
        'iterations_used' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the reviews for this agent run.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(AgentReview::class)->orderBy('iteration');
    }

    /**
     * Get the outputs for this agent run.
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(AgentOutput::class)->orderBy('iteration');
    }

    /**
     * Get the latest review.
     */
    public function latestReview(): ?AgentReview
    {
        return $this->reviews()->latest('iteration')->first();
    }

    /**
     * Check if the run is still in progress.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if the run completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the run failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the duration of the run in seconds.
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($end);
    }

    /**
     * Get a summary of the run for display.
     */
    public function getSummaryAttribute(): string
    {
        $status = match ($this->status) {
            'running' => '🔄 Running',
            'completed' => '✅ Completed',
            'failed' => '❌ Failed',
            'max_iterations_reached' => '⚠️ Max iterations',
            default => $this->status,
        };

        $iterations = $this->iterations_used
            ? "{$this->iterations_used}/{$this->max_iterations} iterations"
            : 'Not started';

        return "{$status} - {$iterations}";
    }
}
