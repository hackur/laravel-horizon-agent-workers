<?php

namespace App\Console\Commands;

use App\Services\LLMQueryDispatcher;
use Illuminate\Console\Command;

class LLMQueryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'llm:query
                            {provider : The LLM provider (claude, ollama, lmstudio, claude-code)}
                            {prompt : The prompt to send to the LLM}
                            {--model= : The model to use (optional)}
                            {--queue= : The queue to use (optional)}
                            {--sync : Run synchronously instead of dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch an LLM query to a background worker job';

    /**
     * Execute the console command.
     */
    public function handle(LLMQueryDispatcher $dispatcher)
    {
        $provider = $this->argument('provider');
        $prompt = $this->argument('prompt');
        $model = $this->option('model');

        // Validate provider
        $providers = $dispatcher->getProviders();
        if (!isset($providers[$provider])) {
            $this->error("Invalid provider: {$provider}");
            $this->info("Available providers: " . implode(', ', array_keys($providers)));
            return 1;
        }

        $this->info("Dispatching query to {$provider}...");

        try {
            $query = $dispatcher->dispatch($provider, $prompt, $model);

            $this->info("Query dispatched successfully!");
            $this->table(
                ['ID', 'Provider', 'Model', 'Status', 'Queue'],
                [
                    [
                        $query->id,
                        $query->provider,
                        $query->model ?? 'default',
                        $query->status,
                        $providers[$provider]['queue'],
                    ]
                ]
            );

            $this->info("\nMonitor the query:");
            $this->line("  - View in Horizon: http://localhost:8000/horizon");
            $this->line("  - Check status: php artisan tinker");
            $this->line("    > App\\Models\\LLMQuery::find({$query->id})");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to dispatch query: " . $e->getMessage());
            return 1;
        }
    }
}
