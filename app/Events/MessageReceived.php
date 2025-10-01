<?php

namespace App\Events;

use App\Models\ConversationMessage;
use App\Models\LLMQuery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;
    public array $message;
    public array $queryStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(int $conversationId, ConversationMessage $message, LLMQuery $query)
    {
        $this->conversationId = $conversationId;
        $this->message = [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'created_at' => $message->created_at?->toISOString(),
        ];
        $this->queryStatus = [
            'id' => $query->id,
            'status' => $query->status,
            'response' => $query->response,
            'reasoning_content' => $query->reasoning_content,
            'error' => $query->error,
            'duration_ms' => $query->duration_ms,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversations.' . $this->conversationId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.received';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message' => $this->message,
            'query_status' => $this->queryStatus,
        ];
    }
}
