<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LLM Query API Resource
 *
 * Transforms LLM query model data into a consistent JSON structure.
 */
class LLMQueryResource extends JsonResource
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
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt' => $this->prompt,
            'response' => $this->response,
            'status' => $this->status,
            'error' => $this->error,
            'metadata' => $this->metadata,
            'reasoning' => $this->reasoning,
            'usage' => $this->usage,
            'user_id' => $this->user_id,
            'conversation_id' => $this->conversation_id,
            'job_id' => $this->job_id,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // API navigation links
            'links' => [
                'self' => route('api.llm-queries.show', $this->id),
                'conversation' => $this->when(
                    $this->conversation_id,
                    fn () => route('api.conversations.show', $this->conversation_id)
                ),
            ],
        ];
    }
}
