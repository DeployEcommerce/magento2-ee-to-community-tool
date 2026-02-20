<?php

namespace App\Commands;

use App\Contracts\DatabaseConnectionInterface;
use App\Contracts\MagentoPathResolverInterface;
use App\Contracts\SnapshotComparatorInterface;
use App\Contracts\SnapshotInterface;
use App\Services\DisclaimerService;
use App\ValueObjects\DatabaseConfig;

class VerifyCommand extends BaseCommand
{
    protected $signature = 'verify
        {--path= : Path to the Magento root directory}
        {--snapshot= : Path to a specific before-snapshot JSON file}
        {--accept-terms : Accept the disclaimer and skip the confirmation prompt}';

    protected $description = 'Verify the EE→CE migration was successful by comparing snapshots';

    public function __construct(
        DisclaimerService $disclaimer,
        private readonly MagentoPathResolverInterface $resolver,
        private readonly DatabaseConnectionInterface $connection,
        private readonly SnapshotInterface $snapshot,
        private readonly SnapshotComparatorInterface $comparator,
    ) {
        parent::__construct($disclaimer);
    }

    public function handle(): int
    {
        $this->requireDisclaimer();

        $magentoPath = $this->resolver->resolve($this->option('path'));

        $envPhp = include $magentoPath . '/app/etc/env.php';
        $config = DatabaseConfig::fromEnvPhp($envPhp);
        $this->connection->connect($config);

        $this->info('Taking post-migration snapshot...');
        $after = $this->snapshot->capture($this->connection);

        // Load before snapshot
        $snapshotPath = $this->option('snapshot');
        if (!$snapshotPath) {
            $snapshotPath = $this->findLatestBeforeSnapshot();
        }

        if (!$snapshotPath || !file_exists($snapshotPath)) {
            $this->warn('No before-snapshot found. Running verification without comparison.');
            $this->reportSnapshotStatus($after->eeTablesPresent, $after->rowIdColumnsPresent, $after->sequenceTablesPresent);
            return $this->assertEmpty($after->eeTablesPresent, $after->rowIdColumnsPresent, $after->sequenceTablesPresent)
                ? self::SUCCESS
                : self::FAILURE;
        }

        $this->info("Loading before-snapshot: {$snapshotPath}");
        $before = $this->snapshot->load($snapshotPath);

        $diff = $this->comparator->compare($before, $after);
        $passed = $this->comparator->assertSuccess($diff);

        $this->newLine();
        $this->line('┌─────────────────────────────────────────┐');
        $this->line('│         MIGRATION VERIFICATION          │');
        $this->line('└─────────────────────────────────────────┘');
        $this->newLine();

        // Quick pass/fail checks
        $eeCount    = count($diff['eeTablesRemaining']);
        $rowIdCount = count($diff['rowIdColumnsRemaining']);
        $seqCount   = count($diff['sequenceTablesRemaining']);

        $this->renderCheck('EE-specific tables removed',  $eeCount === 0,    $eeCount    === 0 ? "All {$diff['eeTablesRemovedCount']} EE tables dropped"              : "{$eeCount} EE table(s) still present: "     . implode(', ', array_slice($diff['eeTablesRemaining'], 0, 5)));
        $this->renderCheck('row_id columns removed',      $rowIdCount === 0, $rowIdCount === 0 ? 'No row_id columns remaining'                                        : "{$rowIdCount} table(s) still have row_id: " . implode(', ', array_slice($diff['rowIdColumnsRemaining'], 0, 5)));
        $this->renderCheck('EE sequence tables removed',  $seqCount === 0,   $seqCount   === 0 ? 'All EE staging sequence tables dropped'                             : "{$seqCount} EE sequence table(s) still present");

        // Schema changes summary table
        $this->newLine();
        $this->line('  <fg=yellow>Schema Changes</>');
        $this->table(
            ['Metric', 'Before', 'After', 'Result'],
            [
                [
                    'EE-specific tables',
                    count($before->eeTablesPresent),
                    count($after->eeTablesPresent),
                    $eeCount === 0 ? '<fg=green>-' . $diff['eeTablesRemovedCount'] . ' removed ✓</>' : '<fg=red>' . $eeCount . ' remaining ✗</>',
                ],
                [
                    'Tables with row_id',
                    count($before->rowIdColumnsPresent),
                    count($after->rowIdColumnsPresent),
                    $rowIdCount === 0 ? '<fg=green>all cleaned ✓</>' : '<fg=red>' . $rowIdCount . ' remaining ✗</>',
                ],
                [
                    'EE staging sequence tables',
                    count($before->sequenceTablesPresent),
                    count($after->sequenceTablesPresent),
                    $seqCount === 0 ? '<fg=green>-' . count($before->sequenceTablesPresent) . ' removed ✓</>' : '<fg=red>' . $seqCount . ' remaining ✗</>',
                ],
            ]
        );

        // Data integrity table
        $dataLost = false;
        $rows = [];
        foreach ($diff['rowCountDeltas'] as $table => $delta) {
            $lost = $delta['delta'] !== null && $delta['delta'] < 0;
            if ($lost) {
                $dataLost = true;
            }
            $rows[] = [
                $table,
                $delta['before'] ?? 'N/A',
                $delta['after']  ?? 'N/A',
                $lost ? '<fg=red>' . sprintf('%+d', $delta['delta']) . ' ✗</>' : '<fg=green>✓</>',
            ];
        }

        $this->line('  <fg=yellow>Data Integrity</>');
        $this->table(['Table', 'Before', 'After', 'Status'], $rows);

        if ($dataLost) {
            $this->warn('  Warning: some tables lost rows during migration — review before proceeding.');
            $this->newLine();
        }

        if ($passed && !$dataLost) {
            $this->info('✓ VERIFICATION PASSED — Migration completed successfully.');
        } else {
            $this->error('✗ VERIFICATION FAILED — Migration may be incomplete.');
        }

        return ($passed && !$dataLost) ? self::SUCCESS : self::FAILURE;
    }

    private function findLatestBeforeSnapshot(): ?string
    {
        $files = glob(getcwd() . '/snapshot-before-*.json');
        if (!$files) {
            return null;
        }
        sort($files);
        return end($files);
    }

    private function reportSnapshotStatus(array $eeTables, array $rowIdCols, array $seqTables): void
    {
        $this->renderCheck('EE-specific tables removed', count($eeTables) === 0, count($eeTables) . ' EE tables remaining');
        $this->renderCheck('row_id columns removed', count($rowIdCols) === 0, count($rowIdCols) . ' row_id columns remaining');
        $this->renderCheck('sequence_* tables removed', count($seqTables) === 0, count($seqTables) . ' sequence_* tables remaining');
    }

    private function assertEmpty(array $eeTables, array $rowIdCols, array $seqTables): bool
    {
        return count($eeTables) === 0 && count($rowIdCols) === 0 && count($seqTables) === 0;
    }

    private function renderCheck(string $label, bool $passed, string $detail): void
    {
        $icon = $passed ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $status = $passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
        $this->line("  {$icon} {$status}  {$label}");
        $this->line("       {$detail}");
        $this->newLine();
    }
}
