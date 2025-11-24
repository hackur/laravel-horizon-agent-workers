<?php

namespace Database\Factories;

use App\Models\LLMQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

class LLMQueryFactory extends Factory
{
    protected $model = LLMQuery::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'conversation_id' => null,
            'provider' => $this->faker->randomElement(['lmstudio', 'local-command', 'ollama']),
            'model' => null,
            'prompt' => $this->faker->sentence(),
            'response' => null,
            'reasoning_content' => null,
            'status' => 'pending',
            'finish_reason' => null,
            'error' => null,
            'duration_ms' => null,
            'metadata' => [],
            'usage_stats' => [],
            'cost_usd' => null,
            'input_cost_usd' => null,
            'output_cost_usd' => null,
            'pricing_tier' => null,
            'over_budget' => false,
            'completed_at' => null,
        ];
    }
}
