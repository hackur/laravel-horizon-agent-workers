<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LLMQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    /**
     * Token counter service
     */
    protected TokenCounter $tokenCounter;

    /**
     * Constructor with dependency injection
     */
    public function __construct(TokenCounter $tokenCounter)
    {
        $this->tokenCounter = $tokenCounter;
    }

    /**
     * Add a message to a conversation.
     *
     * Creates a new conversation message with the specified role and content.
     * Optionally links the message to an LLM query and captures relevant metadata
     * including provider, model, duration, and finish reason. Updates the conversation's
     * last message timestamp.
     *
     * @param  Conversation  $conversation  The conversation to add the message to
     * @param  string  $role  The message role (typically 'user' or 'assistant')
     * @param  string  $content  The message content/text
     * @param  LLMQuery|null  $llmQuery  Optional LLM query associated with this message
     * @return ConversationMessage The created message model
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
     * Get conversation context for LLM (message history) with token management.
     *
     * Retrieves message history for a conversation in the format needed for LLM context.
     * Automatically manages token counts and truncates older messages if the context
     * exceeds the model's safe context limit. Logs warnings when truncation occurs.
     *
     * @param  Conversation  $conversation  The conversation to get context for
     * @param  int  $messageLimit  Maximum number of messages to return (default 100)
     * @return array Array of messages with 'role' and 'content' keys
     */
    public function getConversationContext(Conversation $conversation, int $messageLimit = 100): array
    {
        // Get model for token counting
        $model = $conversation->model ?? $conversation->provider;

        // Get all messages in chronological order (oldest first)
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->limit($messageLimit)
            ->get();

        // Convert to LLM format
        $formattedMessages = $messages->map(function ($message) {
            return [
                'role' => $message->role,
                'content' => $message->content,
            ];
        })->toArray();

        // Count tokens in the current context
        $totalTokens = $this->tokenCounter->countMessages($formattedMessages, $model);
        $safeLimit = $this->tokenCounter->getSafeContextLimit($model);

        // If we're over the limit, truncate from the beginning
        if ($totalTokens > $safeLimit) {
            $truncatedMessages = $this->truncateContext($formattedMessages, $model, $safeLimit);

            $originalCount = count($formattedMessages);
            $truncatedCount = count($truncatedMessages);
            $messagesRemoved = $originalCount - $truncatedCount;

            Log::warning('Conversation context truncated due to token limit', [
                'conversation_id' => $conversation->id,
                'model' => $model,
                'original_messages' => $originalCount,
                'truncated_messages' => $truncatedCount,
                'messages_removed' => $messagesRemoved,
                'original_tokens' => $totalTokens,
                'safe_limit' => $safeLimit,
                'final_tokens' => $this->tokenCounter->countMessages($truncatedMessages, $model),
            ]);

            return $truncatedMessages;
        }

        // Check if we're approaching the limit (>75%)
        if ($this->tokenCounter->isApproachingLimit($totalTokens, $model, 75.0)) {
            $usagePercent = $this->tokenCounter->getContextUsagePercent($totalTokens, $model);

            Log::info('Conversation context approaching token limit', [
                'conversation_id' => $conversation->id,
                'model' => $model,
                'total_tokens' => $totalTokens,
                'safe_limit' => $safeLimit,
                'usage_percent' => round($usagePercent, 2),
                'message_count' => count($formattedMessages),
            ]);
        }

        return $formattedMessages;
    }

    /**
     * Truncate conversation context to fit within token limits.
     *
     * Removes older messages from the beginning of the conversation until
     * the token count is under the safe limit. Always preserves the most
     * recent messages to maintain conversation coherence.
     *
     * @param  array  $messages  Array of messages with 'role' and 'content'
     * @param  string|null  $model  Model name for token counting
     * @param  int  $safeLimit  Safe token limit to stay under
     * @return array Truncated array of messages
     */
    protected function truncateContext(array $messages, ?string $model, int $safeLimit): array
    {
        $messageCount = count($messages);

        // Always keep at least 2 messages (one exchange)
        $minMessages = min(2, $messageCount);

        // Start from the end and work backwards, keeping messages that fit
        $keptMessages = [];
        $currentTokens = 0;

        // Iterate from newest to oldest
        for ($i = $messageCount - 1; $i >= 0; $i--) {
            $message = $messages[$i];
            $messageTokens = $this->tokenCounter->countMessage($message, $model);

            // Check if adding this message would exceed the limit
            if ($currentTokens + $messageTokens > $safeLimit) {
                // Only break if we have the minimum messages
                if (count($keptMessages) >= $minMessages) {
                    break;
                }
            }

            // Add message to the beginning of kept messages
            array_unshift($keptMessages, $message);
            $currentTokens += $messageTokens;
        }

        return $keptMessages;
    }

    /**
     * Get conversation context with detailed token information.
     *
     * Returns both the message context and comprehensive token statistics
     * including usage percentage, warning level, and truncation status.
     *
     * @param  Conversation  $conversation  The conversation to analyze
     * @param  int  $messageLimit  Maximum number of messages to consider
     * @return array Array containing:
     *               - 'messages': array of formatted messages
     *               - 'token_info': array with detailed token statistics
     */
    public function getConversationContextWithTokenInfo(Conversation $conversation, int $messageLimit = 100): array
    {
        $model = $conversation->model ?? $conversation->provider;

        // Get all messages
        $allMessages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->limit($messageLimit)
            ->get();

        // Format messages
        $formattedMessages = $allMessages->map(function ($message) {
            return [
                'role' => $message->role,
                'content' => $message->content,
            ];
        })->toArray();

        // Count tokens before truncation
        $originalTokens = $this->tokenCounter->countMessages($formattedMessages, $model);
        $safeLimit = $this->tokenCounter->getSafeContextLimit($model);
        $fullLimit = $this->tokenCounter->getContextLimit($model);

        // Get truncated context (if needed)
        $finalMessages = $this->getConversationContext($conversation, $messageLimit);
        $finalTokens = $this->tokenCounter->countMessages($finalMessages, $model);

        $wasTruncated = count($formattedMessages) > count($finalMessages);

        return [
            'messages' => $finalMessages,
            'token_info' => [
                'current_tokens' => $finalTokens,
                'original_tokens' => $originalTokens,
                'safe_limit' => $safeLimit,
                'full_limit' => $fullLimit,
                'remaining_tokens' => $this->tokenCounter->getRemainingTokens($finalTokens, $model),
                'usage_percent' => round($this->tokenCounter->getContextUsagePercent($finalTokens, $model), 2),
                'warning_level' => $this->tokenCounter->getWarningLevel($finalTokens, $model),
                'was_truncated' => $wasTruncated,
                'messages_count' => count($finalMessages),
                'original_messages_count' => count($formattedMessages),
                'messages_removed' => $wasTruncated ? count($formattedMessages) - count($finalMessages) : 0,
                'model' => $model,
                'model_display_name' => $this->tokenCounter->getModelDisplayName($model),
            ],
        ];
    }

    /**
     * Auto-create or get existing conversation for a query.
     *
     * Creates a new conversation for a user with the specified LLM provider and optional model.
     * If no title is provided, generates one using the current timestamp.
     * Links the conversation to the user's current team if available.
     *
     * @param  int  $userId  The user ID to create the conversation for
     * @param  string  $provider  The LLM provider (claude, ollama, lmstudio, local-command)
     * @param  string|null  $model  Optional model name or ID
     * @param  string|null  $title  Optional conversation title (auto-generated if not provided)
     * @return Conversation The created or retrieved conversation model
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
     *
     * Takes a completed LLM query and creates an assistant message in the associated
     * conversation with the query's response. Returns null if the query has no conversation
     * or response. Automatically captures LLM query metadata in the message.
     *
     * @param  LLMQuery  $query  The completed LLM query with a response
     * @return ConversationMessage|null The created message or null if query lacks conversation/response
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
     * Uses database aggregation instead of loading all queries into memory
     * and filtering in PHP. This prevents N+1 queries and improves performance.
     *
     * @param  Conversation  $conversation  The conversation to get statistics for
     * @return array Associative array containing:
     *               - total_messages: int
     *               - total_queries: int
     *               - completed_queries: int
     *               - failed_queries: int
     *               - pending_queries: int
     *               - processing_queries: int
     *               - total_duration_ms: int
     *               - avg_duration_ms: float
     *               - total_tokens: int
     *               - input_tokens: int
     *               - output_tokens: int
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

        // Get cost statistics
        $costStats = DB::table('l_l_m_queries')
            ->select([
                DB::raw('SUM(CASE WHEN status = "completed" THEN cost_usd ELSE 0 END) as total_cost_usd'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN input_cost_usd ELSE 0 END) as total_input_cost_usd'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN output_cost_usd ELSE 0 END) as total_output_cost_usd'),
                DB::raw('AVG(CASE WHEN status = "completed" AND cost_usd IS NOT NULL THEN cost_usd ELSE NULL END) as avg_cost_usd'),
                DB::raw('MAX(CASE WHEN status = "completed" THEN cost_usd ELSE NULL END) as max_cost_usd'),
                DB::raw('COUNT(CASE WHEN over_budget = 1 THEN 1 ELSE NULL END) as over_budget_count'),
            ])
            ->where('conversation_id', $conversation->id)
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
            'total_cost_usd' => round($costStats->total_cost_usd ?? 0, 6),
            'total_input_cost_usd' => round($costStats->total_input_cost_usd ?? 0, 6),
            'total_output_cost_usd' => round($costStats->total_output_cost_usd ?? 0, 6),
            'avg_cost_usd' => round($costStats->avg_cost_usd ?? 0, 6),
            'max_cost_usd' => round($costStats->max_cost_usd ?? 0, 6),
            'over_budget_count' => $costStats->over_budget_count ?? 0,
        ];
    }
}
