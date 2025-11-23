<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LLMQueryCollection;
use App\Http\Resources\LLMQueryResource;
use App\Models\LLMQuery;
use App\Services\LLMQueryDispatcher;
use Illuminate\Http\Request;

/**
 * LLM Query API Controller
 *
 * Handles all API operations for LLM queries including listing,
 * creating, and viewing query status.
 */
class LLMQueryApiController extends Controller
{
    public function __construct(
        protected LLMQueryDispatcher $dispatcher
    ) {}

    /**
     * Display a listing of LLM queries.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $queries = LLMQuery::query()
            ->where('user_id', $request->user()->id)
            ->when($request->provider, fn ($q, $provider) => $q->byProvider($provider))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json(new LLMQueryCollection($queries));
    }

    /**
     * Store a newly created query and dispatch to queue.
     *
     * @return \Illuminate\Http\JsonResponse
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
            'user_id' => $request->user()->id,
        ]);

        $query = $this->dispatcher->dispatch(
            $validated['provider'],
            $validated['prompt'],
            $validated['model'] ?? null,
            $options
        );

        // Auto-assign user_id to the query
        $query->update(['user_id' => $request->user()->id]);

        return response()->json([
            'message' => 'Query dispatched successfully',
            'data' => new LLMQueryResource($query),
        ], 201);
    }

    /**
     * Display the specified query.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, LLMQuery $llmQuery)
    {
        // Authorize access
        if ($llmQuery->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized access to query',
                'errors' => [
                    'query' => ['You do not have permission to view this query.'],
                ],
            ], 403);
        }

        return response()->json([
            'data' => new LLMQueryResource($llmQuery),
        ]);
    }
}
