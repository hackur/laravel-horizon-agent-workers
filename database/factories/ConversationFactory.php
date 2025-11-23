<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => null,
            'title' => fake()->sentence(),
            'provider' => fake()->randomElement(['claude', 'ollama', 'lmstudio', 'local-command']),
            'model' => fake()->randomElement([
                'claude-3-5-sonnet-20241022',
                'llama2',
                'mistral',
                null,
            ]),
            'last_message_at' => now(),
        ];
    }

    /**
     * Indicate that the conversation uses Claude.
     */
    public function claude(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'claude',
            'model' => 'claude-3-5-sonnet-20241022',
        ]);
    }

    /**
     * Indicate that the conversation uses Ollama.
     */
    public function ollama(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'ollama',
            'model' => 'llama2',
        ]);
    }

    /**
     * Indicate that the conversation uses LM Studio.
     */
    public function lmstudio(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'lmstudio',
            'model' => 'mistral',
        ]);
    }
}
