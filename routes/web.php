<?php

use App\Http\Controllers\LLMQueryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('llm-queries.index');
});

// LLM Query Routes
Route::resource('llm-queries', LLMQueryController::class)
    ->only(['index', 'create', 'store', 'show']);

// API Routes for LLM Queries
Route::prefix('api')->group(function () {
    Route::post('/llm/query', [LLMQueryController::class, 'apiStore'])->name('api.llm-queries.store');
    Route::get('/llm/query/{llmQuery}', [LLMQueryController::class, 'apiShow'])->name('api.llm-queries.show');
    Route::get('/llm/queries', [LLMQueryController::class, 'apiIndex'])->name('api.llm-queries.index');
});
