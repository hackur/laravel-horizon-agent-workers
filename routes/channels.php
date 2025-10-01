<?php

use App\Models\Conversation;
use App\Models\LLMQuery;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('queries.{id}', function ($user, $id) {
    $query = LLMQuery::find($id);
    return $query && (int) $query->user_id === (int) $user->id;
});

Broadcast::channel('conversations.{id}', function ($user, $id) {
    $conversation = Conversation::find($id);
    return $conversation && (int) $conversation->user_id === (int) $user->id;
});
