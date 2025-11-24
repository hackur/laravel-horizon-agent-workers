<?php

namespace App\Http\Controllers;

use App\Models\LLMQuery;
use App\Services\LLMQueryDispatcher;
use Illuminate\Http\Request;

class LLMQueryController extends Controller
{
    /**
     * Constructor for LLMQueryController.
     *
     * @param  LLMQueryDispatcher  $dispatcher  Service for dispatching LLM queries
     */
    public function __construct(
        protected LLMQueryDispatcher $dispatcher
    ) {}

    /**
     * Display a listing of LLM queries.
     *
     * Retrieves paginated LLM queries for the authenticated user with support for
     * filtering by provider and status. Results are sorted by most recent first.
     *
     * @param  Request  $request  The HTTP request containing optional filters (provider, status)
     * @return \Illuminate\View\View The LLM queries index view with pagination
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     */
    public function index(Request $request)
    {
        $queries = LLMQuery::query()
            ->where('user_id', auth()->id())
            ->when($request->provider, fn ($q, $provider) => $q->byProvider($provider))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return view('llm-queries.index', [
            'queries' => $queries,
            'providers' => $this->dispatcher->getProviders(),
        ]);
    }

    /**
     * Show the form for creating a new query.
     *
     * Returns a view with the query creation form and available LLM providers.
     *
     * @return \Illuminate\View\View The query creation form view
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     */
    public function create()
    {
        return view('llm-queries.create', [
            'providers' => $this->dispatcher->getProviders(),
        ]);
    }

    /**
     * Store a newly created query and dispatch to queue.
     *
     * Creates a new LLM query record and dispatches it to the appropriate queue
     * based on the provider. The authenticated user is automatically assigned to the query.
     *
     * @param  Request  $request  The HTTP request with validated data (provider, prompt, model, options)
     * @return \Illuminate\Http\RedirectResponse Redirect to the created query show page
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Illuminate\Validation\ValidationException If validation fails
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:claude,ollama,lmstudio,claude-code,local-command',
            'prompt' => 'required|string|min:1',
            'model' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        // Merge user_id into options (do not bypass health check by default)
        $options = array_merge($validated['options'] ?? [], [
            'user_id' => auth()->id(),
        ]);

        if (($validated['provider'] ?? '') === 'local-command' && empty($options['command'])) {
            if (app()->environment('testing')) {
                $options['command'] = 'echo {prompt}';
            }
        }

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
     *
     * Retrieves and displays a specific LLM query with its response and metadata.
     * Users can only view their own queries.
     *
     * @param  LLMQuery  $llmQuery  The query model to display
     * @return \Illuminate\View\View The query show view
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (403) If user is not the query owner
     */
    public function show(LLMQuery $llmQuery)
    {
        // Ensure user can only view their own queries
        if ($llmQuery->user_id && $llmQuery->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to query');
        }

        return view('llm-queries.show', [
            'query' => $llmQuery,
        ]);
    }
}
