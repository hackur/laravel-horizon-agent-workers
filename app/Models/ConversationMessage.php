<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    use HasFactory;

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

    /**
     * Get highlighted content excerpt for search results.
     *
     * Returns a snippet of the message content with search terms highlighted.
     * If no match is found in the content, returns the first 200 characters.
     *
     * @param  string|null  $searchTerm  The search term to highlight
     * @param  int  $contextLength  Number of characters to show around match
     * @return string The highlighted excerpt
     */
    public function getSearchExcerpt(?string $searchTerm = null, int $contextLength = 200): string
    {
        if (! $searchTerm || empty(trim($searchTerm))) {
            return $this->getTruncatedContent($contextLength);
        }

        // Clean the search term
        $searchTerm = trim(str_replace('"', '', $searchTerm));

        // Find the position of the search term (case-insensitive)
        $position = stripos($this->content, $searchTerm);

        if ($position === false) {
            return $this->getTruncatedContent($contextLength);
        }

        // Calculate excerpt boundaries
        $start = max(0, $position - ($contextLength / 2));
        $excerpt = substr($this->content, $start, $contextLength);

        // Add ellipsis if truncated
        if ($start > 0) {
            $excerpt = '...'.$excerpt;
        }
        if (strlen($this->content) > $start + $contextLength) {
            $excerpt .= '...';
        }

        return $excerpt;
    }

    /**
     * Get truncated content.
     *
     * @param  int  $length  Maximum length
     * @return string Truncated content
     */
    protected function getTruncatedContent(int $length = 200): string
    {
        if (strlen($this->content) <= $length) {
            return $this->content;
        }

        return substr($this->content, 0, $length).'...';
    }

    /**
     * Highlight search term in text.
     *
     * @param  string  $text  The text to highlight in
     * @param  string  $searchTerm  The term to highlight
     * @return string The text with highlighted search terms
     */
    public function highlightSearchTerm(string $text, string $searchTerm): string
    {
        if (empty(trim($searchTerm))) {
            return $text;
        }

        // Clean the search term
        $searchTerm = trim(str_replace('"', '', $searchTerm));

        // Use preg_replace for case-insensitive replacement
        return preg_replace(
            '/('.preg_quote($searchTerm, '/').')/i',
            '<mark class="bg-yellow-200 px-1 rounded">$1</mark>',
            $text
        );
    }
}
