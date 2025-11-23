<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LLMQuery;
use Illuminate\Support\Facades\DB;

/**
 * OPTIMIZED VERSION of ConversationService
 *
 * This is an optimized version that fixes N+1 query issues in the getStatistics() method.
 * To use this version, rename the current ConversationService.php to ConversationService.OLD.php
 * and rename this file to ConversationService.php
 */
class ConversationService
{
    /**
     * Add a message to a conversation.
     */
    public function addMessage(Conversation $conversation, string $role, string $content, ?LLMQuery $llmQuery = null): ConversationMessage
    {
        $message = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'llm_query_id' => $llmQuery?->id,
            'role' => $role,
            'content' => $content,
            'metadata' => $llmQuery ? [
                'query_id' => $llmQuery->id,
                'provider' => $llmQuery->provider,
                'model' => $llmQuery->model,
                'duration_ms' => $llmQuery->duration_ms,
                'finish_reason' => $llmQuery->finish_reason,
            ] : null,
        ]);

        // Update conversation's last message timestamp
        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    /**
     * Get conversation context for LLM (message history).
     */
    public function getConversationContext(Conversation $conversation, int $messageLimit = 20): array
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->limit($messageLimit)
            ->get();

        return $messages->map(function ($message) {
            return [
                'role' => $message->role,
                'content' => $message->content,
            ];
        })->toArray();
    }

    /**
     * Auto-create or get existing conversation for a query.
     */
    public function getOrCreateConversation(int $userId, string $provider, ?string $model = null, ?string $title = null): Conversation
    {
        // If no title provided, use a timestamp-based default
        $title = $title ?? 'Conversation '.now()->format('Y-m-d H:i:s');

        return Conversation::create([
            'user_id' => $userId,
            'team_id' => auth()->user()->currentTeam?->id,
            'title' => $title,
            'provider' => $provider,
            'model' => $model,
            'last_message_at' => now(),
        ]);
    }

    /**
     * Link an LLM query response to the conversation as an assistant message.
     */
    public function addQueryResponse(LLMQuery $query): ?ConversationMessage
    {
        if (! $query->conversation_id || ! $query->response) {
            return null;
        }

        $conversation = Conversation::find($query->conversation_id);

        if (! $conversation) {
            return null;
        }

        return $this->addMessage($conversation, 'assistant', $query->response, $query);
    }

    /**
     * Get conversation statistics - OPTIMIZED VERSION.
     *
     * This version uses database aggregation instead of loading all queries into memory
     * and filtering in PHP. This prevents N+1 queries and improves performance.
     */
    public function getStatistics(Conversation $conversation): array
    {
        // Use single aggregation query instead of loading all queries
        $queryStats = DB::table('l_l_m_queries')
            ->select([
                DB::raw('COUNT(*) as total_queries'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_queries'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_queries'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_queries'),
                DB::raw('SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing_queries'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN duration_ms ELSE 0 END) as total_duration_ms'),
                DB::raw('AVG(CASE WHEN status = "completed" THEN duration_ms ELSE NULL END) as avg_duration_ms'),
            ])
            ->where('conversation_id', $conversation->id)
            ->first();

        // Get total tokens from JSON field (SQLite compatible)
        // Note: For MySQL/PostgreSQL, you might need to adjust the JSON extraction syntax
        $tokenStats = DB::table('l_l_m_queries')
            ->select([
                DB::raw('SUM(
                    CASE
                        WHEN status = "completed" AND usage_stats IS NOT NULL
                        THEN CAST(json_extract(usage_stats, "$.total_tokens") AS INTEGER)
                        ELSE 0
                    END
                ) as total_tokens'),
                DB::raw('SUM(
                    CASE
                        WHEN status = "completed" AND usage_stats IS NOT NULL
                        THEN CAST(json_extract(usage_stats, "$.input_tokens") AS INTEGER)
                        ELSE 0
                    END
                ) as input_tokens'),
                DB::raw('SUM(
                    CASE
                        WHEN status = "completed" AND usage_stats IS NOT NULL
                        THEN CAST(json_extract(usage_stats, "$.output_tokens") AS INTEGER)
                        ELSE 0
                    END
                ) as output_tokens'),
            ])
            ->where('conversation_id', $conversation->id)
            ->where('status', 'completed')
            ->first();

        return [
            'total_messages' => $conversation->messages()->count(),
            'total_queries' => $queryStats->total_queries ?? 0,
            'completed_queries' => $queryStats->completed_queries ?? 0,
            'failed_queries' => $queryStats->failed_queries ?? 0,
            'pending_queries' => $queryStats->pending_queries ?? 0,
            'processing_queries' => $queryStats->processing_queries ?? 0,
            'total_duration_ms' => $queryStats->total_duration_ms ?? 0,
            'avg_duration_ms' => round($queryStats->avg_duration_ms ?? 0, 2),
            'total_tokens' => $tokenStats->total_tokens ?? 0,
            'input_tokens' => $tokenStats->input_tokens ?? 0,
            'output_tokens' => $tokenStats->output_tokens ?? 0,
        ];
    }

    /**
     * Get conversation statistics using Eloquent ORM - Alternative approach.
     *
     * This is an alternative that uses Eloquent aggregations instead of raw queries.
     * Use this if you prefer Eloquent over raw SQL.
     */
    public function getStatisticsEloquent(Conversation $conversation): array
    {
        $queries = $conversation->queries();

        return [
            'total_messages' => $conversation->messages()->count(),
            'total_queries' => $queries->count(),
            'completed_queries' => (clone $queries)->where('status', 'completed')->count(),
            'failed_queries' => (clone $queries)->where('status', 'failed')->count(),
            'pending_queries' => (clone $queries)->where('status', 'pending')->count(),
            'processing_queries' => (clone $queries)->where('status', 'processing')->count(),
            'total_duration_ms' => (clone $queries)->where('status', 'completed')->sum('duration_ms'),
            'avg_duration_ms' => round((clone $queries)->where('status', 'completed')->avg('duration_ms') ?? 0, 2),
            // Note: Token aggregation still requires raw SQL for JSON extraction
            'total_tokens' => $this->sumTokensForConversation($conversation->id, 'total_tokens'),
            'input_tokens' => $this->sumTokensForConversation($conversation->id, 'input_tokens'),
            'output_tokens' => $this->sumTokensForConversation($conversation->id, 'output_tokens'),
        ];
    }

    /**
     * Helper method to sum token values from JSON field.
     */
    private function sumTokensForConversation(int $conversationId, string $tokenField): int
    {
        $result = DB::table('l_l_m_queries')
            ->select([
                DB::raw("SUM(
                    CASE
                        WHEN status = 'completed' AND usage_stats IS NOT NULL
                        THEN CAST(json_extract(usage_stats, '$.{$tokenField}') AS INTEGER)
                        ELSE 0
                    END
                ) as total"),
            ])
            ->where('conversation_id', $conversationId)
            ->first();

        return $result->total ?? 0;
    }
}
