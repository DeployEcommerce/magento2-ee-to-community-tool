<?php

namespace App\Services\Composer;

use App\Contracts\ComposerAnalyserInterface;
use App\ValueObjects\ComposerAnalysis;

class ComposerAnalyser implements ComposerAnalyserInterface
{
    private const EE_PACKAGE = 'magento/product-enterprise-edition';

    private const CE_PACKAGE = 'magento/product-community-edition';

    private const CLOUD_METAPACKAGE = 'magento/magento-cloud-metapackage';

    /**
     * Repository URL patterns that indicate enterprise-only access.
     * These repositories typically require changing to community equivalents after migration.
     */
    private const ENTERPRISE_REPOSITORY_PATTERNS = [
        'composer.amasty.com/enterprise/' => 'Amasty Enterprise repository - may need to switch to community version',
    ];

    private const EE_DEPENDENT_PACKAGES = [
        'magento/module-staging',
        'magento/module-visual-merchandiser',
        'magento/module-gift-card',
        'magento/module-gift-card-account',
        'magento/module-gift-registry',
        'magento/module-gift-wrapping',
        'magento/module-reward',
        'magento/module-rma',
        'magento/module-customer-balance',
        'magento/module-banner',
        'magento/module-search-staging',
        'magento/module-catalog-staging',
        'magento/module-cms-staging',
        'magento/module-sales-rule-staging',
        'magento/module-catalog-rule-staging',
        'magento/module-checkout-staging',
        'magento/module-payment-staging',
    ];

    public function analyse(string $composerJsonPath): ComposerAnalysis
    {
        if (! file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException("composer.json not found at: {$composerJsonPath}");
        }

        $data = json_decode(file_get_contents($composerJsonPath), true);
        if ($data === null) {
            throw new \InvalidArgumentException("Invalid JSON in composer.json: {$composerJsonPath}");
        }

        $require = $data['require'] ?? [];
        $eeVersion = $require[self::EE_PACKAGE] ?? '';
        $hasEe = ! empty($eeVersion);

        $replaceKeys = array_keys($data['replace'] ?? []);

        return new ComposerAnalysis(
            data: $data,
            eeVersion: $eeVersion,
            hasEnterpriseEdition: $hasEe,
            replaceKeys: $replaceKeys,
        );
    }

    public function isEnterpriseEdition(ComposerAnalysis $analysis): bool
    {
        return $analysis->hasEnterpriseEdition;
    }

    public function detectConflicts(ComposerAnalysis $analysis): array
    {
        $conflicts = [];
        $require = array_merge(
            $analysis->data['require'] ?? [],
            $analysis->data['require-dev'] ?? []
        );

        foreach (self::EE_DEPENDENT_PACKAGES as $package) {
            if (isset($require[$package])) {
                $conflicts[] = [
                    'package' => $package,
                    'version' => $require[$package],
                    'severity' => 'warning',
                    'message' => "EE-only package found: {$package}. This will not be available in CE.",
                ];
            }
        }

        return $conflicts;
    }

    public function getPackagesToRemove(ComposerAnalysis $analysis): array
    {
        $require = $analysis->data['require'] ?? [];
        $packagesToRemove = [];

        // Remove EE package if present
        if (isset($require[self::EE_PACKAGE])) {
            $packagesToRemove[] = self::EE_PACKAGE;
        }

        // Remove CE package if present (will be re-added with correct version)
        if (isset($require[self::CE_PACKAGE])) {
            $packagesToRemove[] = self::CE_PACKAGE;
        }

        // Remove Cloud metapackage if present
        if (isset($require[self::CLOUD_METAPACKAGE])) {
            $packagesToRemove[] = self::CLOUD_METAPACKAGE;
        }

        return $packagesToRemove;
    }

    public function getPackagesToAdd(ComposerAnalysis $analysis): array
    {
        if (! $analysis->hasEnterpriseEdition) {
            return [];
        }

        $ceVersion = $this->stripPatchSuffix($analysis->eeVersion);

        return [self::CE_PACKAGE => $ceVersion];
    }

    private function stripPatchSuffix(string $version): string
    {
        // Strip -pN suffix: e.g. "2.4.7-p8" → "2.4.7", "^2.4.7-p3" → "^2.4.7"
        return preg_replace('/-p\d+$/', '', $version);
    }

    public function detectEnterpriseRepositories(string $magentoPath): array
    {
        $findings = [];
        $filesToCheck = [
            'composer.json',
            'composer.lock',
        ];

        foreach ($filesToCheck as $filename) {
            $filePath = $magentoPath.'/'.$filename;
            if (! file_exists($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            foreach (self::ENTERPRISE_REPOSITORY_PATTERNS as $pattern => $message) {
                if (str_contains($content, $pattern)) {
                    $findings[] = [
                        'pattern' => $pattern,
                        'file' => $filename,
                        'message' => $message,
                    ];
                }
            }
        }

        return $findings;
    }
}
