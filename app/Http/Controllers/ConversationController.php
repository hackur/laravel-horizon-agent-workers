<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ConversationService;
use App\Services\LLMQueryDispatcher;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected LLMQueryDispatcher $dispatcher
    ) {}

    /**
     * Display a listing of the user's conversations.
     */
    public function index(Request $request)
    {
        $query = Conversation::query()
            ->where('user_id', auth()->id())
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->withCount('messages');

        // Filter by provider if specified
        if ($request->provider) {
            $query->where('provider', $request->provider);
        }

        // Search by title
        if ($request->search) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $conversations = $query->latest('last_message_at')->paginate(15);

        return view('conversations.index', [
            'conversations' => $conversations,
            'providers' => $this->dispatcher->getProviders(),
        ]);
    }

    /**
     * Show the form for creating a new conversation.
     */
    public function create()
    {
        return view('conversations.create', [
            'providers' => $this->dispatcher->getProviders(),
        ]);
    }

    /**
     * Store a newly created conversation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'provider' => 'required|string|in:claude,ollama,lmstudio,claude-code,local-command',
            'model' => 'nullable|string',
            'prompt' => 'required|string|min:1',
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
     */
    public function show(Conversation $conversation)
    {
        // Ensure user can only view their own conversations
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to conversation');
        }

        $conversation->load([
            'messages' => fn($q) => $q->with('llmQuery')->oldest(),
            'queries' => fn($q) => $q->latest()
        ]);

        return view('conversations.show', [
            'conversation' => $conversation,
            'providers' => $this->dispatcher->getProviders(),
        ]);
    }

    /**
     * Add a new message to an existing conversation.
     */
    public function addMessage(Request $request, Conversation $conversation)
    {
        // Ensure user can only add to their own conversations
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to conversation');
        }

        $validated = $request->validate([
            'prompt' => 'required|string|min:1',
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
}
