<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\LLMQueryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Protected Routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Conversations
    Route::resource('conversations', ConversationController::class)
        ->only(['index', 'create', 'store', 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'addMessage'])
        ->name('conversations.add-message');

    // LLM Queries
    Route::resource('llm-queries', LLMQueryController::class)
        ->only(['index', 'create', 'store', 'show']);
});

// API Routes for LLM Queries
Route::prefix('api')->group(function () {
    Route::post('/llm/query', [LLMQueryController::class, 'apiStore'])->name('api.llm-queries.store');
    Route::get('/llm/query/{llmQuery}', [LLMQueryController::class, 'apiShow'])->name('api.llm-queries.show');
    Route::get('/llm/queries', [LLMQueryController::class, 'apiIndex'])->name('api.llm-queries.index');
});
