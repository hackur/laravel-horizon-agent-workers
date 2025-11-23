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
                            {provider : The LLM provider (claude, ollama, lmstudio, claude-code, local-command)}
                            {prompt : The prompt to send to the LLM}
                            {--model= : The model to use (optional)}
                            {--command= : Command template for local-command provider (use {prompt} placeholder)}
                            {--shell= : Shell to use for local-command (default: system default)}
                            {--queue= : The queue to use (optional)}
                            {--sync : Run synchronously instead of dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch an LLM query to a background worker job (default: lmstudio at http://127.0.0.1:1234/v1)';

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
        if (! isset($providers[$provider])) {
            $this->error("Invalid provider: {$provider}");
            $this->info('Available providers: '.implode(', ', array_keys($providers)));

            return 1;
        }

        $this->info("Dispatching query to {$provider}...");

        try {
            // Build options array
            $options = [];

            // Add local-command specific options
            if ($provider === 'local-command') {
                if ($this->option('command')) {
                    $options['command'] = $this->option('command');
                }
                if ($this->option('shell')) {
                    $options['shell'] = $this->option('shell');
                }
            }

            $query = $dispatcher->dispatch($provider, $prompt, $model, $options);

            $this->info('Query dispatched successfully!');
            $this->table(
                ['ID', 'Provider', 'Model', 'Status', 'Queue'],
                [
                    [
                        $query->id,
                        $query->provider,
                        $query->model ?? 'default',
                        $query->status,
                        $providers[$provider]['queue'],
                    ],
                ]
            );

            $this->info("\nMonitor the query:");
            $this->line('  - View in Horizon: http://localhost:8000/horizon');
            $this->line('  - Check status: php artisan tinker');
            $this->line("    > App\\Models\\LLMQuery::find({$query->id})");

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch query: '.$e->getMessage());

            return 1;
        }
    }
}
