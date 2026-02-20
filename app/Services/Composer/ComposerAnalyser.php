<?php

namespace App\Services\Composer;

use App\Contracts\ComposerAnalyserInterface;
use App\ValueObjects\ComposerAnalysis;

class ComposerAnalyser implements ComposerAnalyserInterface
{
    private const EE_PACKAGE = 'magento/product-enterprise-edition';
    private const CE_PACKAGE = 'magento/product-community-edition';

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
        if (!file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException("composer.json not found at: {$composerJsonPath}");
        }

        $data = json_decode(file_get_contents($composerJsonPath), true);
        if ($data === null) {
            throw new \InvalidArgumentException("Invalid JSON in composer.json: {$composerJsonPath}");
        }

        $require = $data['require'] ?? [];
        $eeVersion = $require[self::EE_PACKAGE] ?? '';
        $hasEe = !empty($eeVersion);

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
        if (!$analysis->hasEnterpriseEdition) {
            return [];
        }
        return [self::EE_PACKAGE];
    }

    public function getPackagesToAdd(ComposerAnalysis $analysis): array
    {
        if (!$analysis->hasEnterpriseEdition) {
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
}
