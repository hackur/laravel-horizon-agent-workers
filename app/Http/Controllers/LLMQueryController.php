<?php

namespace App\Http\Controllers;

use App\Models\LLMQuery;
use App\Services\LLMQueryDispatcher;
use Illuminate\Http\Request;

class LLMQueryController extends Controller
{
    public function __construct(
        protected LLMQueryDispatcher $dispatcher
    ) {}

    /**
     * Display a listing of LLM queries.
     */
    public function index(Request $request)
    {
        $queries = LLMQuery::query()
            ->where('user_id', auth()->id())
            ->when($request->provider, fn($q, $provider) => $q->byProvider($provider))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return view('llm-queries.index', [
            'queries' => $queries,
            'providers' => $this->dispatcher->getProviders(),
        ]);
    }

    /**
     * Show the form for creating a new query.
     */
    public function create()
    {
        return view('llm-queries.create', [
            'providers' => $this->dispatcher->getProviders(),
        ]);
    }

    /**
     * Store a newly created query and dispatch to queue.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:claude,ollama,lmstudio,claude-code,local-command',
            'prompt' => 'required|string|min:1',
            'model' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        // Merge user_id into options
        $options = array_merge($validated['options'] ?? [], [
            'user_id' => auth()->id(),
        ]);

        $query = $this->dispatcher->dispatch(
            $validated['provider'],
            $validated['prompt'],
            $validated['model'] ?? null,
            $options
        );

        // Auto-assign user_id to the query
        $query->update(['user_id' => auth()->id()]);

        return redirect()
            ->route('llm-queries.show', $query)
            ->with('success', 'Query dispatched successfully!');
    }

    /**
     * Display the specified query.
     */
    public function show(LLMQuery $llmQuery)
    {
        return view('llm-queries.show', [
            'query' => $llmQuery,
        ]);
    }

    /**
     * API endpoint to create a query.
     */
    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:claude,ollama,lmstudio,claude-code,local-command',
            'prompt' => 'required|string|min:1',
            'model' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        $query = $this->dispatcher->dispatch(
            $validated['provider'],
            $validated['prompt'],
            $validated['model'] ?? null,
            $validated['options'] ?? []
        );

        return response()->json([
            'success' => true,
            'query' => $query,
        ], 201);
    }

    /**
     * API endpoint to get query by ID.
     */
    public function apiShow(LLMQuery $llmQuery)
    {
        return response()->json($llmQuery);
    }

    /**
     * API endpoint to list queries.
     */
    public function apiIndex(Request $request)
    {
        $queries = LLMQuery::query()
            ->when($request->provider, fn($q, $provider) => $q->byProvider($provider))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($queries);
    }
}
