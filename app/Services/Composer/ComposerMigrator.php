<?php

namespace App\Services\Composer;

use App\Contracts\ComposerAnalyserInterface;
use App\ValueObjects\ComposerAnalysis;

class ComposerMigrator
{
    private const EE_PACKAGE = 'magento/product-enterprise-edition';

    public function migrate(string $composerJsonPath, ComposerAnalysis $analysis): void
    {
        $data = $analysis->data;

        // Remove EE package from require
        unset($data['require'][self::EE_PACKAGE]);

        // Add CE packages
        $analyser = new ComposerAnalyser();
        $packagesToAdd = $analyser->getPackagesToAdd($analysis);
        foreach ($packagesToAdd as $package => $version) {
            $data['require'][$package] = $version;
        }

        // Remove only magento/* entries from the replace section
        if (isset($data['replace'])) {
            foreach (array_keys($data['replace']) as $key) {
                if (str_starts_with($key, 'magento/')) {
                    unset($data['replace'][$key]);
                }
            }
            // Remove empty replace section
            if (empty($data['replace'])) {
                unset($data['replace']);
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($composerJsonPath, $json);
    }
}
