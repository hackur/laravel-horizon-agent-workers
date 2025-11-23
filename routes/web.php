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
        ->only(['index', 'create', 'store', 'show', 'destroy', 'update']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'addMessage'])
        ->name('conversations.add-message');

    // LLM Queries
    Route::resource('llm-queries', LLMQueryController::class)
        ->only(['index', 'create', 'store', 'show']);

    // LM Studio models endpoint for web UI
    Route::get('/lmstudio/models', [ConversationController::class, 'getLMStudioModels'])
        ->name('lmstudio.models');
});
