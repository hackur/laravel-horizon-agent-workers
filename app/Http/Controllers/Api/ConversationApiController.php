<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationCollection;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Services\LLMQueryDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Conversation API Controller
 *
 * Handles all API operations for conversations including listing,
 * creating, viewing, and adding messages to conversations.
 */
class ConversationApiController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected LLMQueryDispatcher $dispatcher
    ) {}

    /**
     * Display a listing of the user's conversations.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Conversation::query()
            ->where('user_id', $request->user()->id)
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->withCount('messages');

        // Filter by provider if specified
        if ($request->provider) {
            $query->where('provider', $request->provider);
        }

        // Search by title
        if ($request->search) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        $conversations = $query->latest('last_message_at')
            ->paginate($request->per_page ?? 15);

        return response()->json(new ConversationCollection($conversations));
    }

    /**
     * Store a newly created conversation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'provider' => 'required|string|in:claude,ollama,lmstudio,local-command',
            'model' => 'nullable|string',
            'prompt' => 'required|string|min:1',
        ]);

        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'team_id' => $request->user()->currentTeam?->id,
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
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
            ]
        );

        // Link query to conversation
        $query->update(['conversation_id' => $conversation->id]);

        return response()->json([
            'message' => 'Conversation created successfully',
            'data' => new ConversationResource($conversation->load(['messages', 'queries'])),
        ], 201);
    }

    /**
     * Display the specified conversation.
     *
     * @param Conversation $conversation
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Conversation $conversation)
    {
        // Authorize access
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized access to conversation',
                'errors' => [
                    'conversation' => ['You do not have permission to view this conversation.'],
                ],
            ], 403);
        }

        $conversation->load([
            'messages' => fn ($q) => $q->with('llmQuery')->oldest(),
            'queries' => fn ($q) => $q->latest(),
        ]);

        return response()->json([
            'data' => new ConversationResource($conversation),
        ]);
    }

    /**
     * Update the specified conversation.
     *
     * @param Request $request
     * @param Conversation $conversation
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Conversation $conversation)
    {
        // Authorize access
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized access to conversation',
                'errors' => [
                    'conversation' => ['You do not have permission to update this conversation.'],
                ],
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $conversation->update([
            'title' => $validated['title'],
        ]);

        return response()->json([
            'message' => 'Conversation updated successfully',
            'data' => new ConversationResource($conversation),
        ]);
    }

    /**
     * Remove the specified conversation.
     *
     * @param Conversation $conversation
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Conversation $conversation)
    {
        // Authorize access
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized access to conversation',
                'errors' => [
                    'conversation' => ['You do not have permission to delete this conversation.'],
                ],
            ], 403);
        }

        $title = $conversation->title;
        $conversation->delete();

        return response()->json([
            'message' => "Conversation \"{$title}\" deleted successfully",
        ]);
    }

    /**
     * Add a new message to an existing conversation.
     *
     * @param Request $request
     * @param Conversation $conversation
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMessage(Request $request, Conversation $conversation)
    {
        // Authorize access
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized access to conversation',
                'errors' => [
                    'conversation' => ['You do not have permission to add messages to this conversation.'],
                ],
            ], 403);
        }

        $validated = $request->validate([
            'prompt' => 'required|string|min:1',
        ]);

        // Add the user's message
        $message = $this->conversationService->addMessage(
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
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
                'conversation_context' => $context,
            ]
        );

        // Link query to conversation
        $query->update(['conversation_id' => $conversation->id]);

        // Update last message timestamp
        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'message' => 'Message added and query dispatched successfully',
            'data' => new MessageResource($message),
        ], 201);
    }

    /**
     * Fetch available models from LM Studio (with caching).
     *
     * @return \Illuminate\Http\JsonResponse
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
}
