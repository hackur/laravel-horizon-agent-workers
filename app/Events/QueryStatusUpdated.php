<?php

namespace App\Events;

use App\Models\LLMQuery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueryStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $queryId;

    public string $status;

    public ?string $response;

    public ?string $reasoning_content;

    public ?array $usage_stats;

    public ?string $error;

    public ?int $duration_ms;

    public ?string $finish_reason;

    /**
     * Create a new event instance.
     */
    public function __construct(LLMQuery $query)
    {
        $this->queryId = $query->id;
        $this->status = $query->status;
        $this->response = $query->response;
        $this->reasoning_content = $query->reasoning_content;
        $this->usage_stats = $query->usage_stats;
        $this->error = $query->error;
        $this->duration_ms = $query->duration_ms;
        $this->finish_reason = $query->finish_reason;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('queries.'.$this->queryId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'status.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'query_id' => $this->queryId,
            'status' => $this->status,
            'response' => $this->response,
            'reasoning_content' => $this->reasoning_content,
            'usage_stats' => $this->usage_stats,
            'error' => $this->error,
            'duration_ms' => $this->duration_ms,
            'finish_reason' => $this->finish_reason,
        ];
    }
}
