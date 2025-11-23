<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ConversationService;
use App\Services\LLMQueryDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ConversationController extends Controller
{
    /**
     * Constructor for ConversationController.
     *
     * @param  ConversationService  $conversationService  Service for managing conversations
     * @param  LLMQueryDispatcher  $dispatcher  Service for dispatching LLM queries
     */
    public function __construct(
        protected ConversationService $conversationService,
        protected LLMQueryDispatcher $dispatcher
    ) {}

    /**
     * Display a listing of the user's conversations.
     *
     * Retrieves paginated conversations for the authenticated user with support for:
     * - Full-text search across titles and message content
     * - Filtering by provider
     * - Date range filtering
     * - Status filtering
     * - Search type (content or title only)
     * Results are sorted by most recent message timestamp or relevance.
     *
     * @param  Request  $request  The HTTP request containing optional filters
     * @return \Illuminate\View\View The conversations index view
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     */
    public function index(Request $request)
    {
        $query = Conversation::query()
            ->where('user_id', auth()->id())
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->withCount('messages');

        // Full-text search across title and message content
        if ($request->filled('search')) {
            $search = substr($request->search, 0, 255); // Limit length for security

            // Determine search type (content search uses FTS, title uses simple LIKE)
            if ($request->search_type === 'content' || ! $request->filled('search_type')) {
                // Full-text search in messages and titles (default)
                $query->fullTextSearch($search);
            } else {
                // Simple title search (legacy mode)
                $query->search($search);
            }
        }

        // Filter by provider if specified (validate input)
        if ($request->filled('provider') && in_array($request->provider, ['claude', 'ollama', 'lmstudio', 'local-command'])) {
            $query->where('provider', $request->provider);
        }

        // Filter by date range
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Filter by status
        if ($request->filled('status') && in_array($request->status, ['pending', 'processing', 'completed', 'failed'])) {
            $query->byStatus($request->status);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'recent');
        if ($sortBy === 'oldest') {
            $query->orderBy('last_message_at', 'asc');
        } elseif ($sortBy === 'title') {
            $query->orderBy('title', 'asc');
        } else {
            // Default: most recent
            $query->latest('last_message_at');
        }

        $conversations = $query->paginate(15)->withQueryString();

        return view('conversations.index', [
            'conversations' => $conversations,
            'providers' => $this->dispatcher->getProviders(),
            'searchTerm' => $request->search,
            'filters' => [
                'provider' => $request->provider,
                'status' => $request->status,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'search_type' => $request->search_type ?? 'content',
                'sort_by' => $sortBy,
            ],
        ]);
    }

    /**
     * Show the form for creating a new conversation.
     *
     * Returns a view with the conversation creation form and available LLM providers.
     *
     * @return \Illuminate\View\View The conversation creation form view
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     */
    public function create()
    {
        return view('conversations.create', [
            'providers' => $this->dispatcher->getProviders(),
        ]);
    }

    /**
     * Store a newly created conversation.
     *
     * Creates a new conversation with the provided details, adds the initial user message,
     * and dispatches an LLM query to the specified provider. The conversation is linked
     * to the authenticated user and their current team.
     *
     * @param  Request  $request  The HTTP request with validated data (title, provider, model, prompt)
     * @return \Illuminate\Http\RedirectResponse Redirect to the created conversation show page
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Illuminate\Validation\ValidationException If validation fails
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'provider' => 'required|string|in:claude,ollama,lmstudio,local-command',
            'model' => 'nullable|string|max:255',
            'prompt' => 'required|string|min:1|max:100000',
        ]);

        $conversation = Conversation::create([
            'user_id' => auth()->id(),
            'team_id' => auth()->user()->currentTeam?->id,
            'title' => $validated['title'],
            'provider' => $validated['provider'],
            'model' => $validated['model'] ?? null,
            'last_message_at' => now(),
        ]);

        // Add the user's message to the conversation
        $this->conversationService->addMessage(
            $conversation,
            'user',
            $validated['prompt']
        );

        // Dispatch the query
        $query = $this->dispatcher->dispatch(
            $validated['provider'],
            $validated['prompt'],
            $validated['model'] ?? null,
            [
                'user_id' => auth()->id(),
                'conversation_id' => $conversation->id,
            ]
        );

        // Link query to conversation
        $query->update(['conversation_id' => $conversation->id]);

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('success', 'Conversation created and query dispatched!');
    }

    /**
     * Display the specified conversation.
     *
     * Retrieves and displays a specific conversation with all its messages and queries.
     * Messages are loaded with their associated LLM queries and sorted chronologically.
     * Includes token usage information and warnings. Enforces authorization - users can
     * only view their own conversations.
     *
     * @param  Conversation  $conversation  The conversation model to display
     * @return \Illuminate\View\View The conversation show view with messages, queries, and token info
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (403) If user is not the conversation owner
     */
    public function show(Conversation $conversation)
    {
        // Ensure user can only view their own conversations
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to conversation');
        }

        $conversation->load([
            'messages' => fn ($q) => $q->with('llmQuery')->oldest(),
            'queries' => fn ($q) => $q->latest(),
        ]);

        // Get conversation statistics including costs
        $statistics = $this->conversationService->getStatistics($conversation);

        // Get token information for the conversation context
        $contextData = $this->conversationService->getConversationContextWithTokenInfo($conversation);

        return view('conversations.show', [
            'conversation' => $conversation,
            'providers' => $this->dispatcher->getProviders(),
            'statistics' => $statistics,
            'tokenInfo' => $contextData['token_info'],
        ]);
    }

    /**
     * Add a new message to an existing conversation.
     *
     * Adds a new user message to the conversation, retrieves conversation context,
     * and dispatches an LLM query with the conversation history for contextual responses.
     * Updates the conversation's last message timestamp. Enforces authorization.
     *
     * @param  Request  $request  The HTTP request with validated data (prompt)
     * @param  Conversation  $conversation  The conversation to add a message to
     * @return \Illuminate\Http\RedirectResponse Redirect to the updated conversation show page
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (403) If user is not the conversation owner
     * @throws \Illuminate\Validation\ValidationException If validation fails
     */
    public function addMessage(Request $request, Conversation $conversation)
    {
        // Ensure user can only add to their own conversations
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to conversation');
        }

        $validated = $request->validate([
            'prompt' => 'required|string|min:1|max:100000',
        ]);

        // Add the user's message
        $this->conversationService->addMessage(
            $conversation,
            'user',
            $validated['prompt']
        );

        // Build conversation context for the LLM
        $context = $this->conversationService->getConversationContext($conversation);

        // Dispatch the query with conversation context
        $query = $this->dispatcher->dispatch(
            $conversation->provider,
            $validated['prompt'],
            $conversation->model,
            [
                'user_id' => auth()->id(),
                'conversation_id' => $conversation->id,
                'conversation_context' => $context,
            ]
        );

        // Link query to conversation
        $query->update(['conversation_id' => $conversation->id]);

        // Update last message timestamp
        $conversation->update(['last_message_at' => now()]);

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('success', 'Message added and query dispatched!');
    }

    /**
     * Fetch available models from LM Studio (with caching).
     *
     * Retrieves the list of available models from the LM Studio API with a 5-minute cache.
     * Gracefully handles connection failures and clears cache on error for recovery.
     * The cache is automatically invalidated on errors to ensure fresh attempts on retry.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *                                       - success: bool
     *                                       - models: array of model IDs
     *                                       - cached: bool indicating if result was from cache
     *                                       - error: string error message if failed (status 500)
     */
    public function getLMStudioModels()
    {
        try {
            // Cache for 5 minutes to reduce API calls
            $models = Cache::remember('lmstudio.models', 300, function () {
                $baseUrl = env('LMSTUDIO_BASE_URL', 'http://127.0.0.1:1234');

                $response = Http::timeout(5)
                    ->get("{$baseUrl}/v1/models");

                if ($response->successful()) {
                    $data = $response->json();

                    return collect($data['data'] ?? [])
                        ->pluck('id')
                        ->values()
                        ->toArray();
                }

                throw new \Exception('Failed to fetch models from LM Studio');
            });

            return response()->json([
                'success' => true,
                'models' => $models,
                'cached' => true,
            ]);
        } catch (\Exception $e) {
            // Clear cache on error so next request will retry
            Cache::forget('lmstudio.models');

            return response()->json([
                'success' => false,
                'error' => 'LM Studio is not running or not accessible: '.$e->getMessage(),
                'models' => [],
            ], 500);
        }
    }

    /**
     * Fetch available models from Ollama (with caching).
     *
     * Retrieves the list of available models from the Ollama API with a 5-minute cache.
     * Gracefully handles connection failures and clears cache on error for recovery.
     * The cache is automatically invalidated on errors to ensure fresh attempts on retry.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *                                       - success: bool
     *                                       - models: array of model names
     *                                       - cached: bool indicating if result was from cache
     *                                       - error: string error message if failed (status 500)
     */
    public function getOllamaModels()
    {
        try {
            // Cache for 5 minutes to reduce API calls
            $models = Cache::remember('ollama.models', 300, function () {
                $baseUrl = env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434');

                $response = Http::timeout(5)
                    ->get("{$baseUrl}/api/tags");

                if ($response->successful()) {
                    $data = $response->json();

                    return collect($data['models'] ?? [])
                        ->pluck('name')
                        ->values()
                        ->toArray();
                }

                throw new \Exception('Failed to fetch models from Ollama');
            });

            return response()->json([
                'success' => true,
                'models' => $models,
                'cached' => true,
            ]);
        } catch (\Exception $e) {
            // Clear cache on error so next request will retry
            Cache::forget('ollama.models');

            return response()->json([
                'success' => false,
                'error' => 'Ollama is not running or not accessible: '.$e->getMessage(),
                'models' => [],
            ], 500);
        }
    }

    /**
     * Update a conversation.
     *
     * Updates the title of an existing conversation. Only the conversation owner
     * can perform this action. Enforces authorization checks.
     *
     * @param  Request  $request  The HTTP request with validated data (title)
     * @param  Conversation  $conversation  The conversation to update
     * @return \Illuminate\Http\RedirectResponse Redirect to the updated conversation show page
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (403) If user is not the conversation owner
     * @throws \Illuminate\Validation\ValidationException If validation fails
     */
    public function update(Request $request, Conversation $conversation)
    {
        // Ensure user can only update their own conversations
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to conversation');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $conversation->update([
            'title' => $validated['title'],
        ]);

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('success', 'Conversation title updated successfully.');
    }

    /**
     * Delete a conversation.
     *
     * Permanently deletes a conversation and all its associated messages and queries
     * through cascade delete. Only the conversation owner can perform this action.
     * Returns user to conversations index with success message.
     *
     * @param  Conversation  $conversation  The conversation to delete
     * @return \Illuminate\Http\RedirectResponse Redirect to conversations index with success message
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (403) If user is not the conversation owner
     */
    public function destroy(Conversation $conversation)
    {
        // Ensure user can only delete their own conversations
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to conversation');
        }

        $title = $conversation->title;

        // Delete the conversation (cascade will handle messages and queries)
        $conversation->delete();

        return redirect()
            ->route('conversations.index')
            ->with('success', "Conversation \"{$title}\" deleted successfully.");
    }

    /**
     * Export conversation as JSON.
     *
     * Exports a conversation with all messages, queries, and metadata in JSON format.
     * The export includes detailed query metadata such as tokens, duration, finish reason,
     * and reasoning content where applicable. Only the conversation owner can export.
     *
     * @param  Conversation  $conversation  The conversation to export
     * @return \Illuminate\Http\Response JSON file download response
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (403) If user is not the conversation owner
     */
    public function exportJson(Conversation $conversation)
    {
        // Ensure user can only export their own conversations
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to conversation');
        }

        // Load all related data
        $conversation->load([
            'messages' => fn ($q) => $q->with('llmQuery')->oldest(),
            'queries' => fn ($q) => $q->latest(),
            'user:id,name,email',
        ]);

        // Build export data structure
        $exportData = [
            'exported_at' => now()->toIso8601String(),
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'provider' => $conversation->provider,
                'model' => $conversation->model,
                'created_at' => $conversation->created_at->toIso8601String(),
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'metadata' => $conversation->metadata,
            ],
            'user' => [
                'name' => $conversation->user->name,
                'email' => $conversation->user->email,
            ],
            'messages' => $conversation->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                    'llm_query' => $message->llmQuery ? [
                        'id' => $message->llmQuery->id,
                        'status' => $message->llmQuery->status,
                        'provider' => $message->llmQuery->provider,
                        'model' => $message->llmQuery->model,
                        'duration_ms' => $message->llmQuery->duration_ms,
                        'finish_reason' => $message->llmQuery->finish_reason,
                        'usage_stats' => $message->llmQuery->usage_stats,
                        'reasoning_content' => $message->llmQuery->reasoning_content,
                    ] : null,
                ];
            })->values(),
            'queries_summary' => [
                'total' => $conversation->queries->count(),
                'by_status' => $conversation->queries->groupBy('status')->map->count(),
            ],
        ];

        // Generate filename
        $filename = 'conversation-'.$conversation->id.'-'.now()->format('Y-m-d-His').'.json';

        // Return JSON download
        return response()
            ->json($exportData, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export conversation as Markdown.
     *
     * Exports a conversation as formatted Markdown with conversation metadata, message history,
     * and LLM query statistics (tokens, duration, finish reason). Includes reasoning content
     * for models that provide it. Only the conversation owner can export. Returns a downloadable
     * Markdown file with conversation ID and timestamp in the filename.
     *
     * @param  Conversation  $conversation  The conversation to export
     * @return \Illuminate\Http\Response Markdown file download response
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (403) If user is not the conversation owner
     */
    public function exportMarkdown(Conversation $conversation)
    {
        // Ensure user can only export their own conversations
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to conversation');
        }

        // Load all related data
        $conversation->load([
            'messages' => fn ($q) => $q->with('llmQuery')->oldest(),
            'user:id,name,email',
        ]);

        // Build Markdown content
        $markdown = "# {$conversation->title}\n\n";

        // Metadata section
        $markdown .= "## Conversation Details\n\n";
        $markdown .= "- **Created:** {$conversation->created_at->format('F j, Y g:i A')}\n";
        $markdown .= "- **Provider:** {$conversation->provider}\n";
        if ($conversation->model) {
            $markdown .= "- **Model:** {$conversation->model}\n";
        }
        $markdown .= "- **Last Message:** {$conversation->last_message_at?->format('F j, Y g:i A')}\n";
        $markdown .= "- **Total Messages:** {$conversation->messages->count()}\n";
        $markdown .= "- **User:** {$conversation->user->name} ({$conversation->user->email})\n";
        $markdown .= "\n---\n\n";

        // Messages section
        $markdown .= "## Conversation History\n\n";

        foreach ($conversation->messages as $index => $message) {
            $messageNumber = $index + 1;
            $roleIcon = $message->role === 'user' ? '👤' : '🤖';
            $roleLabel = ucfirst($message->role);

            $markdown .= "### Message {$messageNumber}: {$roleIcon} {$roleLabel}\n\n";
            $markdown .= "*{$message->created_at->format('F j, Y g:i A')}*\n\n";

            // Message content
            $markdown .= "{$message->content}\n\n";

            // Add LLM query metadata for assistant messages
            if ($message->role === 'assistant' && $message->llmQuery) {
                $markdown .= "#### Query Metadata\n\n";

                if ($message->llmQuery->usage_stats) {
                    $usageStats = $message->llmQuery->usage_stats;
                    if (isset($usageStats['total_tokens'])) {
                        $markdown .= '- **Tokens:** '.number_format($usageStats['total_tokens']);
                        if (isset($usageStats['prompt_tokens'])) {
                            $markdown .= ' (Prompt: '.number_format($usageStats['prompt_tokens']);
                        }
                        if (isset($usageStats['completion_tokens'])) {
                            $markdown .= ', Completion: '.number_format($usageStats['completion_tokens']);
                        }
                        $markdown .= ")\n";
                    }
                }

                if ($message->llmQuery->duration_ms) {
                    $durationSeconds = round($message->llmQuery->duration_ms / 1000, 2);
                    $markdown .= "- **Duration:** {$durationSeconds}s\n";
                }

                if ($message->llmQuery->finish_reason) {
                    $markdown .= "- **Finish Reason:** {$message->llmQuery->finish_reason}\n";
                }

                // Add reasoning content if present
                if ($message->llmQuery->reasoning_content) {
                    $markdown .= "\n##### Reasoning\n\n";
                    $markdown .= "```\n{$message->llmQuery->reasoning_content}\n```\n";
                }

                $markdown .= "\n";
            }

            $markdown .= "---\n\n";
        }

        // Footer
        $markdown .= "## Export Information\n\n";
        $markdown .= '- **Exported At:** '.now()->format('F j, Y g:i A')."\n";
        $markdown .= "- **Exported By:** {$conversation->user->name}\n";

        // Generate filename
        $filename = 'conversation-'.$conversation->id.'-'.now()->format('Y-m-d-His').'.md';

        // Return Markdown download
        return response($markdown, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
