<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LLMQuery;

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
     * Get conversation statistics.
     */
    public function getStatistics(Conversation $conversation): array
    {
        $queries = $conversation->queries;

        return [
            'total_messages' => $conversation->messages()->count(),
            'total_queries' => $queries->count(),
            'completed_queries' => $queries->where('status', 'completed')->count(),
            'failed_queries' => $queries->where('status', 'failed')->count(),
            'total_duration_ms' => $queries->where('status', 'completed')->sum('duration_ms'),
            'total_tokens' => $queries->where('status', 'completed')->sum(function ($query) {
                return $query->usage_stats['total_tokens'] ?? 0;
            }),
        ];
    }
}
