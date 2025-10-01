<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LLMQuery extends Model
{
    protected $table = 'l_l_m_queries';

    protected $fillable = [
        'provider',
        'model',
        'prompt',
        'response',
        'status',
        'error',
        'duration_ms',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
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
}
