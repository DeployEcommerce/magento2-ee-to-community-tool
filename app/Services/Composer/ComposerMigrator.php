<?php

namespace App\Services\Composer;

use App\Contracts\ComposerAnalyserInterface;
use App\ValueObjects\ComposerAnalysis;

class ComposerMigrator
{
    public function migrate(string $composerJsonPath, ComposerAnalysis $analysis): void
    {
        $data = $analysis->data;
        $analyser = new ComposerAnalyser();

        // Remove EE, CE (if present), and Cloud metapackage from require
        foreach ($analyser->getPackagesToRemove($analysis) as $package) {
            unset($data['require'][$package]);
        }

        // Add CE package with correct version
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
