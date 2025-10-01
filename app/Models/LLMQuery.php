<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LLMQuery extends Model
{
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
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'usage_stats' => 'array',
        'completed_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
