<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'llm_query_id',
        'role',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the conversation this message belongs to.
     *
     * @return BelongsTo The conversation relationship
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the LLM query associated with this message (if any).
     *
     * Typically populated for assistant messages that are responses from LLM queries.
     * User messages may not have an associated query.
     *
     * @return BelongsTo The llm query relationship
     */
    public function llmQuery(): BelongsTo
    {
        return $this->belongsTo(LLMQuery::class);
    }
}
