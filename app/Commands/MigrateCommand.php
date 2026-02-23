<?php

namespace App\Commands;

use App\Contracts\MagentoPathResolverInterface;
use App\Services\DisclaimerService;

class MigrateCommand extends BaseCommand
{
    protected $signature = 'migrate
        {--path= : Path to the Magento root directory (defaults to current directory)}
        {--dry-run : Parse and analyse without making changes}
        {--accept-terms : Accept the disclaimer and skip the confirmation prompt}';

    protected $description = 'Run the full EE→CE migration (database + composer)';

    public function __construct(
        DisclaimerService $disclaimer,
        private readonly MagentoPathResolverInterface $resolver,
    ) {
        parent::__construct($disclaimer);
    }

    public function handle(): int
    {
        $this->requireDisclaimer();

        $magentoPath = $this->resolver->resolve($this->option('path'));

        $this->newLine();
        $this->line("  Magento root: <fg=cyan>{$magentoPath}</>");
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $acceptTerms = $this->option('accept-terms');
        $commonOptions = array_filter([
            '--path' => $magentoPath,
            '--dry-run' => $isDryRun ?: null,
            '--accept-terms' => $acceptTerms ?: null,
        ]);

        // Step 1: Database migration
        $this->line('┌─────────────────────────────────────────┐');
        $this->line('│  Step 1/3: Database Migration           │');
        $this->line('└─────────────────────────────────────────┘');
        $this->newLine();

        $exitCode = $this->call('db:migrate', $commonOptions);
        if ($exitCode !== self::SUCCESS) {
            $this->newLine();
            $this->error('Database migration failed. Aborting.');
            $this->line('  Fix the issue above and re-run with --from=N to resume.');

            return self::FAILURE;
        }

        $this->newLine();

        // Step 2: Composer migration
        $this->line('┌─────────────────────────────────────────┐');
        $this->line('│  Step 2/3: Composer Migration           │');
        $this->line('└─────────────────────────────────────────┘');
        $this->newLine();

        $exitCode = $this->call('composer:migrate', $commonOptions);
        if ($exitCode !== self::SUCCESS) {
            $this->newLine();
            $this->error('Composer migration failed. Aborting.');

            return self::FAILURE;
        }

        $this->newLine();

        // Step 3: Verify
        $this->line('┌─────────────────────────────────────────┐');
        $this->line('│  Step 3/3: Verification                 │');
        $this->line('└─────────────────────────────────────────┘');
        $this->newLine();

        $exitCode = $this->call('verify', ['--path' => $magentoPath]);

        $this->newLine();

        if ($exitCode === self::SUCCESS) {
            $this->info('Migration complete! Run the following to finish:');
            $this->newLine();
            $this->line('  1. cd '.$magentoPath);
            $this->line('  2. composer update --no-dev');
            $this->line('  3. bin/magento setup:upgrade');
            $this->line('  4. bin/magento setup:di:compile');
            $this->line('  5. bin/magento setup:static-content:deploy');
            $this->line('  6. bin/magento cache:flush');
        } else {
            $this->error('Verification failed. Review the output above.');
        }

        return $exitCode;
    }
}
