<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'title',
        'provider',
        'model',
        'metadata',
        'last_message_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the user who owns this conversation.
     *
     * @return BelongsTo The user relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team this conversation belongs to.
     *
     * @return BelongsTo The team relationship
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all messages in this conversation.
     *
     * Returns all ConversationMessage records associated with this conversation
     * in the order they were created.
     *
     * @return HasMany The messages relationship
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * Get all LLM queries associated with this conversation.
     *
     * Returns all LLMQuery records that were executed within this conversation context.
     *
     * @return HasMany The queries relationship
     */
    public function queries(): HasMany
    {
        return $this->hasMany(LLMQuery::class);
    }

    // =========================================================================
    // QUERY SCOPES - For cleaner and more reusable queries
    // =========================================================================

    /**
     * Scope a query to only include conversations for a specific user.
     *
     * @param  Builder  $query  The query builder instance
     * @param  int  $userId  The user ID to filter by
     * @return Builder The modified query builder
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include conversations for a specific team.
     *
     * @param  Builder  $query  The query builder instance
     * @param  int  $teamId  The team ID to filter by
     * @return Builder The modified query builder
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to only include conversations for a specific provider.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $provider  The LLM provider to filter by
     * @return Builder The modified query builder
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope a query to order conversations by most recent activity.
     *
     * @param  Builder  $query  The query builder instance
     * @return Builder The modified query builder
     */
    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderBy('last_message_at', 'desc');
    }

    /**
     * Scope a query to order conversations by creation date.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $direction  Sort direction ('asc' or 'desc')
     * @return Builder The modified query builder
     */
    public function scopeOrderByCreated(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('created_at', $direction);
    }

    /**
     * Scope a query to search conversations by title (legacy).
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $search  The search term
     * @return Builder The modified query builder
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('title', 'like', "%{$search}%");
    }

    /**
     * Scope a query to perform full-text search across title and messages.
     *
     * This uses SQLite's FTS5 for efficient full-text search. Searches both
     * conversation titles and message content, returning conversations that
     * contain the search term in either location.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $search  The search term (supports FTS5 query syntax)
     * @return Builder The modified query builder
     */
    public function scopeFullTextSearch(Builder $query, string $search): Builder
    {
        if (empty(trim($search))) {
            return $query;
        }

        // Escape special FTS5 characters and prepare search term
        $searchTerm = $this->prepareFtsSearchTerm($search);

        // Search in both conversation titles and message content
        return $query->where(function ($q) use ($searchTerm) {
            // Search in conversation titles
            $q->whereIn('id', function ($subQuery) use ($searchTerm) {
                $subQuery->select('id')
                    ->from('conversations_fts')
                    ->whereRaw('conversations_fts MATCH ?', [$searchTerm]);
            })
            // OR search in conversation messages
                ->orWhereIn('id', function ($subQuery) use ($searchTerm) {
                    $subQuery->select('conversation_id')
                        ->from('conversation_messages_fts')
                        ->whereRaw('conversation_messages_fts MATCH ?', [$searchTerm]);
                });
        });
    }

    /**
     * Scope a query to filter by date range.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string|null  $startDate  Start date (Y-m-d format)
     * @param  string|null  $endDate  End date (Y-m-d format)
     * @return Builder The modified query builder
     */
    public function scopeDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->where('created_at', '>=', $startDate.' 00:00:00');
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate.' 23:59:59');
        }

        return $query;
    }

    /**
     * Scope a query to filter by query status.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $status  The status to filter by (pending, processing, completed, failed)
     * @return Builder The modified query builder
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('queries', function ($q) use ($status) {
            $q->where('status', $status);
        });
    }

    /**
     * Prepare search term for FTS5 query.
     *
     * Escapes special FTS5 characters and wraps phrases in quotes for exact matching.
     *
     * @param  string  $search  The raw search term
     * @return string The prepared search term
     */
    protected function prepareFtsSearchTerm(string $search): string
    {
        // Remove any existing quotes and trim
        $search = trim(str_replace('"', '', $search));

        // If the search contains multiple words, treat as phrase search
        if (str_contains($search, ' ')) {
            return '"'.$search.'"';
        }

        // Single word search - escape special characters
        return $search;
    }

    /**
     * Scope a query to include only conversations with messages.
     *
     * @param  Builder  $query  The query builder instance
     * @return Builder The modified query builder
     */
    public function scopeWithMessages(Builder $query): Builder
    {
        return $query->has('messages');
    }

    /**
     * Scope a query to include message counts and latest message.
     * This is the common pattern used in conversation lists.
     *
     * @param  Builder  $query  The query builder instance
     * @return Builder The modified query builder
     */
    public function scopeWithListData(Builder $query): Builder
    {
        return $query
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->withCount('messages');
    }

    /**
     * Scope a query to include all conversation detail data.
     * This is the common pattern used in conversation show pages.
     *
     * @param  Builder  $query  The query builder instance
     * @return Builder The modified query builder
     */
    public function scopeWithDetailData(Builder $query): Builder
    {
        return $query
            ->with([
                'messages' => fn ($q) => $q->with('llmQuery')->oldest(),
                'queries' => fn ($q) => $q->latest(),
                'user',
            ]);
    }

    // =========================================================================
    // ACCESSOR & HELPER METHODS
    // =========================================================================

    /**
     * Get the latest message for this conversation.
     *
     * @return ConversationMessage|null The latest message or null
     */
    public function latestMessage(): ?ConversationMessage
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Get the first message for this conversation.
     *
     * @return ConversationMessage|null The first message or null
     */
    public function firstMessage(): ?ConversationMessage
    {
        return $this->messages()->oldest()->first();
    }

    /**
     * Check if the conversation has any messages.
     *
     * @return bool True if messages exist
     */
    public function hasMessages(): bool
    {
        return $this->messages()->exists();
    }

    /**
     * Check if the conversation has any pending queries.
     *
     * @return bool True if pending queries exist
     */
    public function hasPendingQueries(): bool
    {
        return $this->queries()->where('status', 'pending')->exists();
    }

    /**
     * Check if the conversation has any processing queries.
     *
     * @return bool True if processing queries exist
     */
    public function hasProcessingQueries(): bool
    {
        return $this->queries()->where('status', 'processing')->exists();
    }

    /**
     * Check if the conversation is active (has pending or processing queries).
     *
     * @return bool True if conversation is active
     */
    public function isActive(): bool
    {
        return $this->hasPendingQueries() || $this->hasProcessingQueries();
    }

    /**
     * Get human-readable provider name.
     *
     * @return string The formatted provider name
     */
    public function getProviderNameAttribute(): string
    {
        return match ($this->provider) {
            'claude' => 'Claude API',
            'ollama' => 'Ollama',
            'lmstudio' => 'LM Studio',
            'local-command' => 'Local Command',
            default => ucfirst($this->provider ?? 'Unknown'),
        };
    }
}
