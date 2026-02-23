<?php

namespace App\Commands;

use App\Contracts\DatabaseConnectionInterface;
use App\Contracts\MagentoPathResolverInterface;
use App\Contracts\SnapshotInterface;
use App\Contracts\SqlLoggerInterface;
use App\Contracts\SqlRunnerInterface;
use App\Services\DisclaimerService;
use App\ValueObjects\DatabaseConfig;

class DatabaseMigrateCommand extends BaseCommand
{
    protected $signature = 'db:migrate
        {--path= : Path to the Magento root directory}
        {--dry-run : Parse and log SQL files without executing against the database}
        {--from=1 : Start from this SQL file number (for resuming failed migrations)}
        {--accept-terms : Accept the disclaimer and skip the confirmation prompt}';

    protected $description = 'Run the EE→CE database migration SQL files';

    public function __construct(
        DisclaimerService $disclaimer,
        private readonly MagentoPathResolverInterface $resolver,
        private readonly DatabaseConnectionInterface $connection,
        private readonly SqlRunnerInterface $runner,
        private readonly SnapshotInterface $snapshot,
        private readonly SqlLoggerInterface $logger,
    ) {
        parent::__construct($disclaimer);
    }

    public function handle(): int
    {
        $this->requireDisclaimer();

        $magentoPath = $this->resolver->resolve($this->option('path'));
        $isDryRun = (bool) $this->option('dry-run');
        $fromNumber = (int) ($this->option('from') ?? 1);

        $envPhp = include $magentoPath.'/app/etc/env.php';
        $config = DatabaseConfig::fromEnvPhp($envPhp);

        if ($isDryRun) {
            $this->warn('DRY RUN MODE — No changes will be made to the database.');
        }

        $this->connection->connect($config);
        $this->info("Connected to database: {$config->dbname}@{$config->host}:{$config->port}");

        // Take pre-migration snapshot
        $this->info('Taking pre-migration snapshot...');
        $beforeSnapshot = $this->snapshot->capture($this->connection);
        $snapshotPath = $this->snapshot->save($beforeSnapshot, getcwd());
        $this->line("  Snapshot saved: {$snapshotPath}");

        $this->line('  EE tables found: '.count($beforeSnapshot->eeTablesPresent));
        $this->line('  Tables with row_id: '.count($beforeSnapshot->rowIdColumnsPresent));
        $this->line('  Sequence tables: '.count($beforeSnapshot->sequenceTablesPresent));
        $this->newLine();

        if ($isDryRun) {
            $this->info('Dry run complete. Snapshot captured. No SQL executed.');

            return self::SUCCESS;
        }

        $this->line('SQL log: '.$this->logger->getLogFilePath());
        $this->newLine();

        if ($fromNumber > 1) {
            $this->warn("Resuming from SQL file #{$fromNumber}");
        }

        $this->info('Running migration SQL files...');
        $results = $this->runner->runFrom($fromNumber);

        $failed = false;
        foreach ($results as $result) {
            if ($result->success) {
                $this->line(sprintf(
                    '  <fg=green>✓</> %02d. %-35s  %d statements  %.0fms',
                    $result->fileNumber,
                    $result->filename,
                    $result->statementsExecuted,
                    $result->durationMs
                ));
            } else {
                $this->line(sprintf(
                    '  <fg=red>✗</> %02d. %-35s  FAILED: %s',
                    $result->fileNumber,
                    $result->filename,
                    $result->error
                ));
                $failed = true;
                $this->newLine();
                $this->error("Migration stopped at file #{$result->fileNumber}. You can resume with --from={$result->fileNumber}");
                break;
            }
        }

        $this->newLine();

        if ($failed) {
            return self::FAILURE;
        }

        $this->info('All SQL files executed successfully.');

        return self::SUCCESS;
    }
}
