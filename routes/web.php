<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CostController;
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
        ->only(['index', 'create', 'store', 'show', 'destroy', 'update']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'addMessage'])
        ->name('conversations.add-message');
    Route::get('/conversations/{conversation}/export/json', [ConversationController::class, 'exportJson'])
        ->name('conversations.export.json');
    Route::get('/conversations/{conversation}/export/markdown', [ConversationController::class, 'exportMarkdown'])
        ->name('conversations.export.markdown');

    // LLM Queries
    Route::resource('llm-queries', LLMQueryController::class)
        ->only(['index', 'create', 'store', 'show']);

    // Cost Tracking
    Route::get('/costs', [CostController::class, 'index'])
        ->name('costs.index');
    Route::get('/costs/stats', [CostController::class, 'stats'])
        ->name('costs.stats');

    // LM Studio models endpoint for web UI
    Route::get('/lmstudio/models', [ConversationController::class, 'getLMStudioModels'])
        ->name('lmstudio.models');

    // Ollama models endpoint for web UI
    Route::get('/ollama/models', [ConversationController::class, 'getOllamaModels'])
        ->name('ollama.models');
});
