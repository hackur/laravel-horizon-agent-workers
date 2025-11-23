<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conversation API Resource
 *
 * Transforms conversation model data into a consistent JSON structure.
 */
class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'provider' => $this->provider,
            'model' => $this->model,
            'user_id' => $this->user_id,
            'team_id' => $this->team_id,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Conditionally loaded relationships
            'messages_count' => $this->whenCounted('messages'),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'queries' => LLMQueryResource::collection($this->whenLoaded('queries')),

            // API navigation links
            'links' => [
                'self' => url("/api/conversations/{$this->id}"),
                'messages' => url("/api/conversations/{$this->id}/messages"),
            ],
        ];
    }
}
