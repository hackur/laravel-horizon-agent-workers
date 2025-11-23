<?php

namespace App\Console\Commands;

use App\Services\EnvironmentValidator;
use Illuminate\Console\Command;

class ValidateEnvironmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'env:validate
                            {--strict : Exit with code 1 if any warnings are found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate all required environment variables and configurations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->info('Validating environment configuration...');
        $this->newLine();

        $validator = new EnvironmentValidator;
        $failFast = false; // Don't throw exceptions in this command, handle them gracefully

        try {
            $validator->validate(failFast: $failFast);
        } catch (\Exception $e) {
            $this->error('Validation failed with exception: '.$e->getMessage());

            return 1;
        }

        $summary = $validator->getSummary();
        $this->displayValidationResults($summary);

        // Determine exit code
        if ($summary['error_count'] > 0) {
            return 1;
        }

        if ($this->option('strict') && $summary['warning_count'] > 0) {
            return 1;
        }

        return 0;
    }

    /**
     * Display validation results in a user-friendly format.
     *
     * @param  array  $summary  Validation summary from EnvironmentValidator
     */
    protected function displayValidationResults(array $summary): void
    {
        $errorCount = $summary['error_count'];
        $warningCount = $summary['warning_count'];

        // Display errors
        if ($errorCount > 0) {
            $this->error("✗ Found {$errorCount} error(s):");
            $this->newLine();

            foreach ($summary['errors'] as $error) {
                $this->line("  <fg=red>✗</> {$error}");
            }

            $this->newLine();
        }

        // Display warnings
        if ($warningCount > 0) {
            $this->warn("⚠ Found {$warningCount} warning(s):");
            $this->newLine();

            foreach ($summary['warnings'] as $warning) {
                $this->line("  <fg=yellow>⚠</> {$warning}");
            }

            $this->newLine();
        }

        // Display success message
        if ($errorCount === 0 && $warningCount === 0) {
            $this->info('✓ All environment variables are properly configured!');
            $this->newLine();
        } elseif ($errorCount === 0) {
            $this->warn("✓ Validation passed with {$warningCount} warning(s)");
            $this->newLine();
        }

        // Display summary table
        $this->table(
            ['Status', 'Count'],
            [
                ['Errors', $errorCount > 0 ? "<fg=red>{$errorCount}</>" : '<fg=green>0</>'],
                ['Warnings', $warningCount > 0 ? "<fg=yellow>{$warningCount}</>" : '<fg=green>0</>'],
                ['Passed', $summary['passed'] ? '<fg=green>Yes</>' : '<fg=red>No</>'],
            ]
        );

        // Display recommendations
        if ($errorCount > 0 || $warningCount > 0) {
            $this->newLine();
            $this->info('Recommendations:');

            if ($errorCount > 0) {
                $this->line('  1. Fix all errors before running the application in production');
            }

            if ($warningCount > 0) {
                $this->line('  2. Review and address warnings to optimize application security and stability');
            }

            if ($this->option('strict') && $warningCount > 0) {
                $this->line('  3. Run without --strict flag to ignore warnings');
            }

            $this->newLine();
        }
    }
}
