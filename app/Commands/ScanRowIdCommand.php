<?php

namespace App\Commands;

use App\Commands\Concerns\ScansForRowId;
use App\Contracts\MagentoPathResolverInterface;
use App\Contracts\RowIdScannerInterface;
use App\Services\DisclaimerService;

class ScanRowIdCommand extends BaseCommand
{
    use ScansForRowId;

    protected $signature = 'scan:row-id
        {--path= : Path to the Magento root directory}
        {--json : Output results as JSON}
        {--markdown : Output results as Markdown}
        {--accept-terms : Accept the disclaimer and skip the confirmation prompt}';

    protected $description = 'Scan for row_id references in both app/code and vendor directories';

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
            $this->info('Scanning for row_id references...');
            $this->newLine();
        }

        $customResults = $this->scanner->scanDirectory($magentoPath.'/app/code');
        $vendorResults = $this->scanner->scanDirectory($magentoPath.'/vendor', [
            '*/magento/*',
            '*magento/*',
        ]);

        $allResults = array_merge($customResults, $vendorResults);

        if ($jsonOutput) {
            $this->outputJson($allResults);

            return self::SUCCESS;
        }

        if ($markdownOutput) {
            $this->outputMarkdown($allResults);

            return self::SUCCESS;
        }

        $this->displayResults('Custom Extensions (app/code)', $customResults);
        $this->displayResults('Third-Party Extensions (vendor/)', $vendorResults);
        $this->displaySummary($customResults, $vendorResults);

        return self::SUCCESS;
    }
}
