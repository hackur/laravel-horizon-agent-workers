<?php

use App\Http\Controllers\Api\ConversationApiController;
use App\Http\Controllers\Api\LLMQueryApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * API Routes
 *
 * All API routes are prefixed with /api and return JSON responses.
 * Authentication is handled via Laravel Sanctum tokens.
 */

/**
 * @api {get} /api/user Get Authenticated User
 * @apiName GetAuthenticatedUser
 * @apiGroup Authentication
 * @apiVersion 1.0.0
 *
 * @apiHeader {String} Authorization Bearer {token}
 * @apiSuccess {Object} user The authenticated user object
 * @apiSuccessExample {json} Success-Response:
 *     HTTP/1.1 200 OK
 *     {
 *       "id": 1,
 *       "name": "John Doe",
 *       "email": "john@example.com"
 *     }
 */
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/**
 * Public API Routes (rate limited for guests)
 */
Route::middleware(['throttle:60,1'])->group(function () {
    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    });
});

/**
 * Authenticated API Routes
 * Requires valid Sanctum token
 * Higher rate limits for authenticated users
 */
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    /**
     * @api {get} /api/conversations List Conversations
     * @apiName ListConversations
     * @apiGroup Conversations
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token}
     * @apiParam {String} [provider] Filter by LLM provider
     * @apiParam {String} [search] Search in conversation titles
     * @apiParam {Number} [page=1] Page number for pagination
     * @apiSuccess {Object[]} data Array of conversation objects
     * @apiSuccess {Object} meta Pagination metadata
     */
    Route::apiResource('conversations', ConversationApiController::class);

    /**
     * @api {post} /api/conversations/:id/messages Add Message to Conversation
     * @apiName AddConversationMessage
     * @apiGroup Conversations
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token}
     * @apiParam {Number} id Conversation ID
     * @apiParam {String} prompt The message content
     * @apiSuccess {Object} data The created message object
     */
    Route::post('/conversations/{conversation}/messages', [ConversationApiController::class, 'addMessage'])
        ->name('conversations.messages.store');

    /**
     * @api {get} /api/llm-queries List LLM Queries
     * @apiName ListLLMQueries
     * @apiGroup LLM Queries
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token}
     * @apiParam {String} [provider] Filter by provider (claude, ollama, lmstudio, local-command)
     * @apiParam {String} [status] Filter by status (pending, processing, completed, failed)
     * @apiParam {Number} [per_page=20] Items per page
     * @apiSuccess {Object[]} data Array of query objects
     * @apiSuccess {Object} meta Pagination metadata
     */
    Route::apiResource('llm-queries', LLMQueryApiController::class)
        ->only(['index', 'store', 'show']);

    /**
     * @api {get} /api/lmstudio/models Get Available LM Studio Models
     * @apiName GetLMStudioModels
     * @apiGroup LLM
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token}
     * @apiSuccess {Boolean} success Request success status
     * @apiSuccess {String[]} models Array of available model names
     * @apiSuccess {Boolean} cached Whether the result was cached
     */
    Route::get('/lmstudio/models', [ConversationApiController::class, 'getLMStudioModels']);
});

/**
 * API Token Management Routes
 * These routes are protected by Sanctum's session authentication
 */
Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
    /**
     * @api {get} /api/tokens List API Tokens
     * @apiName ListTokens
     * @apiGroup API Tokens
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Bearer {token}
     * @apiSuccess {Object[]} data Array of token objects (without actual token values)
     */
    Route::get('/tokens', function (Request $request) {
        return response()->json([
            'data' => $request->user()->tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                ];
            }),
        ]);
    });

    /**
     * @api {post} /api/tokens Create API Token
     * @apiName CreateToken
     * @apiGroup API Tokens
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Session authentication
     * @apiParam {String} name Token name/description
     * @apiParam {String[]} [abilities=['*']] Token abilities/permissions
     * @apiSuccess {String} token The plain-text API token (only shown once)
     * @apiSuccess {Object} tokenObject The token object metadata
     */
    Route::post('/tokens', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
        ]);

        $token = $request->user()->createToken(
            $validated['name'],
            $validated['abilities'] ?? ['*']
        );

        return response()->json([
            'message' => 'API token created successfully. Please save this token as it will not be shown again.',
            'token' => $token->plainTextToken,
            'tokenObject' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'created_at' => $token->accessToken->created_at,
            ],
        ], 201);
    });

    /**
     * @api {delete} /api/tokens/:id Delete API Token
     * @apiName DeleteToken
     * @apiGroup API Tokens
     * @apiVersion 1.0.0
     *
     * @apiHeader {String} Authorization Session authentication
     * @apiParam {Number} id Token ID
     * @apiSuccess {String} message Success message
     */
    Route::delete('/tokens/{tokenId}', function (Request $request, $tokenId) {
        $token = $request->user()->tokens()->find($tokenId);

        if (!$token) {
            return response()->json([
                'message' => 'Token not found',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Token deleted successfully',
        ]);
    });
});
