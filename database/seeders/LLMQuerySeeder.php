<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\LLMQuery;
use Illuminate\Database\Seeder;

class LLMQuerySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (!$admin) {
            $this->command->warn('Admin user not found. Run UserSeeder first.');
            return;
        }

        // Create sample completed query
        LLMQuery::create([
            'user_id' => $admin->id,
            'provider' => 'claude',
            'model' => 'claude-3-5-sonnet-20241022',
            'prompt' => 'Explain Laravel Horizon in simple terms',
            'response' => 'Laravel Horizon is a queue monitoring dashboard for Laravel applications. It provides real-time insights into your background jobs, helps you monitor performance, and makes it easy to manage Redis-based queues.',
            'status' => 'completed',
            'duration_ms' => 1250,
            'completed_at' => now()->subMinutes(5),
        ]);

        // Create sample pending query for LM Studio
        LLMQuery::create([
            'user_id' => $admin->id,
            'provider' => 'lmstudio',
            'model' => 'local-model',
            'prompt' => 'Write a haiku about coding',
            'status' => 'pending',
        ]);

        // Create sample failed query for Ollama (not configured by default)
        LLMQuery::create([
            'user_id' => $admin->id,
            'provider' => 'ollama',
            'model' => 'llama3.2',
            'prompt' => 'Test query',
            'status' => 'failed',
            'error' => 'Connection refused: Ollama is not running',
        ]);

        $this->command->info('✓ Created sample LLM queries (claude completed, lmstudio pending, ollama failed)');
    }
}
