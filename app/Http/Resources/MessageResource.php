<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Message API Resource
 *
 * Transforms message model data into a consistent JSON structure.
 */
class MessageResource extends JsonResource
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
            'conversation_id' => $this->conversation_id,
            'llm_query_id' => $this->llm_query_id,
            'role' => $this->role,
            'content' => $this->content,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Conditionally loaded relationships
            'llmQuery' => new LLMQueryResource($this->whenLoaded('llmQuery')),
        ];
    }
}
