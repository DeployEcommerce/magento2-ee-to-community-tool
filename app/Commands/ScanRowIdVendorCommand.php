<?php

namespace App\Commands;

use App\Commands\Concerns\ScansForRowId;
use App\Contracts\MagentoPathResolverInterface;
use App\Contracts\RowIdScannerInterface;
use App\Services\DisclaimerService;

class ScanRowIdVendorCommand extends BaseCommand
{
    use ScansForRowId;

    protected $signature = 'scan:row-id:vendor
        {--path= : Path to the Magento root directory}
        {--json : Output results as JSON}
        {--markdown : Output results as Markdown}
        {--accept-terms : Accept the disclaimer and skip the confirmation prompt}';

    protected $description = 'Scan for row_id references in vendor (third-party extensions, excluding magento/*)';

    public function __construct(
        DisclaimerService $disclaimer,
        private readonly MagentoPathResolverInterface $resolver,
        private readonly RowIdScannerInterface $scanner,
    ) {
        parent::__construct($disclaimer);
    }

    public function handle(): int
    {
        $this->requireDisclaimer();

        $magentoPath = $this->resolver->resolve($this->option('path'));
        $jsonOutput = (bool) $this->option('json');
        $markdownOutput = (bool) $this->option('markdown');

        if (! $jsonOutput && ! $markdownOutput) {
            $this->info('Scanning vendor for row_id references...');
            $this->newLine();
        }

        $results = $this->scanner->scanDirectory($magentoPath.'/vendor', [
            '*/magento/*',
            '*magento/*',
        ]);

        if ($jsonOutput) {
            $this->outputJson($results);

            return self::SUCCESS;
        }

        if ($markdownOutput) {
            $this->outputMarkdown($results);

            return self::SUCCESS;
        }

        $this->displayResults('Third-Party Extensions (vendor/)', $results);
        $this->displaySingleSummary($results, 'vendor');

        return self::SUCCESS;
    }
}
