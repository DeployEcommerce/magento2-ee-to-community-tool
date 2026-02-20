<?php

namespace App\Commands;

use App\Contracts\ComposerAnalyserInterface;
use App\Contracts\MagentoPathResolverInterface;
use App\Services\Composer\ComposerMigrator;
use App\Services\DisclaimerService;

class ComposerMigrateCommand extends BaseCommand
{
    protected $signature = 'composer:migrate
        {--path= : Path to the Magento root directory}
        {--dry-run : Analyse composer.json without writing changes}
        {--accept-terms : Accept the disclaimer and skip the confirmation prompt}';

    protected $description = 'Migrate composer.json from EE to CE';

    public function __construct(
        DisclaimerService $disclaimer,
        private readonly MagentoPathResolverInterface $resolver,
        private readonly ComposerAnalyserInterface $analyser,
        private readonly ComposerMigrator $migrator,
    ) {
        parent::__construct($disclaimer);
    }

    public function handle(): int
    {
        $this->requireDisclaimer();

        $magentoPath = $this->resolver->resolve($this->option('path'));
        $isDryRun = (bool) $this->option('dry-run');
        $composerJsonPath = $magentoPath . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            $this->error("composer.json not found at: {$composerJsonPath}");
            return self::FAILURE;
        }

        $analysis = $this->analyser->analyse($composerJsonPath);

        if (!$this->analyser->isEnterpriseEdition($analysis)) {
            $this->warn('composer.json does not require magento/product-enterprise-edition. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Enterprise Edition detected: {$analysis->eeVersion}");

        // Report replace section
        $magentoReplaceCount = count(array_filter($analysis->replaceKeys, fn($k) => str_starts_with($k, 'magento/')));
        if ($magentoReplaceCount > 0) {
            $this->line("  replace section: {$magentoReplaceCount} magento/* entries will be removed");
        }

        // Report conflicts
        $conflicts = $this->analyser->detectConflicts($analysis);
        if (!empty($conflicts)) {
            $this->newLine();
            $this->warn('Potential conflicts detected:');
            foreach ($conflicts as $conflict) {
                $this->line("  <fg=yellow>⚠</> {$conflict['package']} ({$conflict['version']})");
                $this->line("    {$conflict['message']}");
            }
        }

        // Report what will change
        $packagesToRemove = $this->analyser->getPackagesToRemove($analysis);
        $packagesToAdd = $this->analyser->getPackagesToAdd($analysis);

        $this->newLine();
        $this->line('Changes to be applied:');
        foreach ($packagesToRemove as $pkg) {
            $this->line("  <fg=red>- remove</> {$pkg}");
        }
        foreach ($packagesToAdd as $pkg => $ver) {
            $this->line("  <fg=green>+ add</>    {$pkg}: {$ver}");
        }
        if ($magentoReplaceCount > 0) {
            $this->line("  <fg=yellow>~ clear</>  replace section (magento/* entries)");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->warn('DRY RUN MODE — No changes written to composer.json.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->migrator->migrate($composerJsonPath, $analysis);
        $this->info("composer.json updated successfully.");
        $this->line("  Next step: run 'composer update --no-dev' to resolve CE dependencies.");

        return self::SUCCESS;
    }
}
