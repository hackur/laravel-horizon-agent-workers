<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            $this->command->warn('Admin user not found. Run UserSeeder first.');

            return;
        }

        // Conversation 1: Laravel Horizon Discussion (Multi-turn)
        $conversation1 = Conversation::create([
            'user_id' => $admin->id,
            'team_id' => $admin->currentTeam->id,
            'title' => 'Understanding Laravel Horizon',
            'provider' => 'claude',
            'model' => 'claude-3-5-sonnet-20241022',
            'last_message_at' => now()->subHours(2),
        ]);

        $messages1 = [
            ['role' => 'user', 'content' => 'Hello! Can you explain what Laravel Horizon is?'],
            ['role' => 'assistant', 'content' => 'Laravel Horizon provides a beautiful dashboard and code-driven configuration for your Laravel powered Redis queues. Horizon allows you to easily monitor key metrics of your queue system such as job throughput, runtime, and job failures.'],
            ['role' => 'user', 'content' => 'How do I configure different queues for different job types?'],
            ['role' => 'assistant', 'content' => 'You can configure Horizon queues in the config/horizon.php file under the "environments" section. Each supervisor can watch specific queues using the "queue" parameter. For example:\n\n\'supervisor-1\' => [\n    \'connection\' => \'redis\',\n    \'queue\' => [\'high\', \'default\'],\n    \'processes\' => 3,\n]\n\nThis would make supervisor-1 process jobs from both "high" and "default" queues.'],
            ['role' => 'user', 'content' => 'Can I set priority for certain queues?'],
            ['role' => 'assistant', 'content' => 'Yes! Queue priority in Horizon is determined by the order you list them in the "queue" array. Jobs from queues listed first will be processed before jobs from queues listed later. So if you have [\'high\', \'default\', \'low\'], jobs from the "high" queue will always be processed before "default" or "low" queue jobs.'],
        ];

        foreach ($messages1 as $messageData) {
            ConversationMessage::create([
                'conversation_id' => $conversation1->id,
                'role' => $messageData['role'],
                'content' => $messageData['content'],
            ]);
        }

        // Conversation 2: LLM Providers Discussion
        $conversation2 = Conversation::create([
            'user_id' => $admin->id,
            'team_id' => $admin->currentTeam->id,
            'title' => 'Comparing AI Providers',
            'provider' => 'ollama',
            'model' => 'llama3.2',
            'last_message_at' => now()->subHour(),
        ]);

        $messages2 = [
            ['role' => 'user', 'content' => 'What are the main differences between Claude, Ollama, and LM Studio?'],
            ['role' => 'assistant', 'content' => 'Great question! Here are the key differences:\n\nClaude: Cloud-based API from Anthropic, requires API key, costs per token, very powerful and reliable.\n\nOllama: Free, runs locally on your machine, supports many open-source models like Llama, no API costs but requires local resources.\n\nLM Studio: Desktop app for running local models with a user-friendly interface, good for testing different models locally.'],
            ['role' => 'user', 'content' => 'Which one should I use for development?'],
            ['role' => 'assistant', 'content' => 'For development, I recommend:\n- Ollama or LM Studio for testing and debugging (free, fast)\n- Claude API for production or when you need the highest quality responses\n\nYou can also use different providers for different use cases in the same application!'],
        ];

        foreach ($messages2 as $messageData) {
            ConversationMessage::create([
                'conversation_id' => $conversation2->id,
                'role' => $messageData['role'],
                'content' => $messageData['content'],
            ]);
        }

        // Conversation 3: Quick Question (Short conversation)
        $conversation3 = Conversation::create([
            'user_id' => $admin->id,
            'team_id' => $admin->currentTeam->id,
            'title' => 'Quick Laravel Question',
            'provider' => 'lmstudio',
            'model' => 'local-model',
            'last_message_at' => now()->subMinutes(30),
        ]);

        $messages3 = [
            ['role' => 'user', 'content' => 'How do I run Laravel migrations?'],
            ['role' => 'assistant', 'content' => 'To run Laravel migrations, use the command:\n\nphp artisan migrate\n\nThis will execute all pending migrations. You can also use:\n- php artisan migrate:fresh (drop all tables and re-run)\n- php artisan migrate:rollback (undo last batch)\n- php artisan migrate:status (see migration status)'],
        ];

        foreach ($messages3 as $messageData) {
            ConversationMessage::create([
                'conversation_id' => $conversation3->id,
                'role' => $messageData['role'],
                'content' => $messageData['content'],
            ]);
        }

        $this->command->info('✓ Created 3 sample conversations with multiple messages');
    }
}
