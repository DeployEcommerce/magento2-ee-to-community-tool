<?php

use App\Services\Composer\ComposerAnalyser;

function makeComposerJson(array $data): string
{
    $path = sys_get_temp_dir().'/composer_test_'.uniqid().'.json';
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

    return $path;
}

afterEach(function () {
    // Pest doesn't auto-cleanup temp files, but tests do it inline
});

test('analyse detects enterprise edition', function () {
    $path = makeComposerJson([
        'require' => [
            'php' => '^8.3',
            'magento/product-enterprise-edition' => '2.4.7-p8',
        ],
        'replace' => [
            'magento/module-cms' => '*',
            'magento/module-catalog' => '*',
        ],
    ]);

    $analyser = new ComposerAnalyser;
    $analysis = $analyser->analyse($path);

    expect($analysis->hasEnterpriseEdition)->toBeTrue();
    expect($analysis->eeVersion)->toBe('2.4.7-p8');
    expect($analysis->replaceKeys)->toContain('magento/module-cms');
    expect($analysis->replaceKeys)->toContain('magento/module-catalog');

    unlink($path);
});

test('isEnterpriseEdition returns false for CE composer.json', function () {
    $path = makeComposerJson([
        'require' => [
            'magento/product-community-edition' => '2.4.7',
        ],
    ]);

    $analyser = new ComposerAnalyser;
    $analysis = $analyser->analyse($path);

    expect($analyser->isEnterpriseEdition($analysis))->toBeFalse();

    unlink($path);
});

test('getPackagesToAdd strips patch suffix from version', function () {
    $path = makeComposerJson([
        'require' => [
            'magento/product-enterprise-edition' => '2.4.7-p8',
        ],
    ]);

    $analyser = new ComposerAnalyser;
    $analysis = $analyser->analyse($path);
    $toAdd = $analyser->getPackagesToAdd($analysis);

    expect($toAdd)->toHaveKey('magento/product-community-edition');
    expect($toAdd['magento/product-community-edition'])->toBe('2.4.7');

    unlink($path);
});

test('getPackagesToAdd handles version without patch suffix', function () {
    $path = makeComposerJson([
        'require' => [
            'magento/product-enterprise-edition' => '2.4.7',
        ],
    ]);

    $analyser = new ComposerAnalyser;
    $analysis = $analyser->analyse($path);
    $toAdd = $analyser->getPackagesToAdd($analysis);

    expect($toAdd['magento/product-community-edition'])->toBe('2.4.7');

    unlink($path);
});

test('getPackagesToRemove returns EE package when present', function () {
    $path = makeComposerJson([
        'require' => ['magento/product-enterprise-edition' => '2.4.7-p3'],
    ]);

    $analyser = new ComposerAnalyser;
    $analysis = $analyser->analyse($path);
    $toRemove = $analyser->getPackagesToRemove($analysis);

    expect($toRemove)->toContain('magento/product-enterprise-edition');

    unlink($path);
});

test('detectConflicts flags EE-only packages', function () {
    $path = makeComposerJson([
        'require' => [
            'magento/product-enterprise-edition' => '2.4.7',
            'magento/module-staging' => '100.4.7',
        ],
    ]);

    $analyser = new ComposerAnalyser;
    $analysis = $analyser->analyse($path);
    $conflicts = $analyser->detectConflicts($analysis);

    expect($conflicts)->not->toBeEmpty();
    $packages = array_column($conflicts, 'package');
    expect($packages)->toContain('magento/module-staging');

    unlink($path);
});

test('detectConflicts returns empty array when no EE packages', function () {
    $path = makeComposerJson([
        'require' => [
            'magento/product-enterprise-edition' => '2.4.7',
            'some/other-package' => '^1.0',
        ],
    ]);

    $analyser = new ComposerAnalyser;
    $analysis = $analyser->analyse($path);
    $conflicts = $analyser->detectConflicts($analysis);

    expect($conflicts)->toBeEmpty();

    unlink($path);
});

test('analyse throws on missing file', function () {
    $analyser = new ComposerAnalyser;
    $analyser->analyse('/non/existent/composer.json');
})->throws(\InvalidArgumentException::class, 'composer.json not found');

// =============================================================================
// Enterprise Repository Detection
// =============================================================================

function makeTempMagentoDir(): string
{
    $dir = sys_get_temp_dir().'/magento_test_'.uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function cleanupTempDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = glob($dir.'/*');
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($dir);
}

test('detectEnterpriseRepositories finds amasty enterprise in composer.json', function () {
    $dir = makeTempMagentoDir();
    file_put_contents($dir.'/composer.json', json_encode([
        'repositories' => [
            ['type' => 'composer', 'url' => 'https://composer.amasty.com/enterprise/'],
        ],
    ], JSON_UNESCAPED_SLASHES));

    $analyser = new ComposerAnalyser;
    $findings = $analyser->detectEnterpriseRepositories($dir);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['pattern'])->toBe('composer.amasty.com/enterprise/');
    expect($findings[0]['file'])->toBe('composer.json');

    cleanupTempDir($dir);
});

test('detectEnterpriseRepositories finds amasty enterprise in composer.lock', function () {
    $dir = makeTempMagentoDir();
    file_put_contents($dir.'/composer.json', '{}');
    file_put_contents($dir.'/composer.lock', json_encode([
        'packages' => [
            ['name' => 'amasty/module-special-promo', 'dist' => ['url' => 'https://composer.amasty.com/enterprise/dist/amasty/module-special-promo.zip']],
        ],
    ], JSON_UNESCAPED_SLASHES));

    $analyser = new ComposerAnalyser;
    $findings = $analyser->detectEnterpriseRepositories($dir);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['file'])->toBe('composer.lock');

    cleanupTempDir($dir);
});

test('detectEnterpriseRepositories returns empty when no enterprise repos found', function () {
    $dir = makeTempMagentoDir();
    file_put_contents($dir.'/composer.json', json_encode([
        'repositories' => [
            ['type' => 'composer', 'url' => 'https://repo.magento.com/'],
        ],
    ]));

    $analyser = new ComposerAnalyser;
    $findings = $analyser->detectEnterpriseRepositories($dir);

    expect($findings)->toBeEmpty();

    cleanupTempDir($dir);
});

test('detectEnterpriseRepositories handles missing files gracefully', function () {
    $dir = makeTempMagentoDir();
    // No files created

    $analyser = new ComposerAnalyser;
    $findings = $analyser->detectEnterpriseRepositories($dir);

    expect($findings)->toBeEmpty();

    cleanupTempDir($dir);
});

test('detectEnterpriseRepositories finds pattern in both files', function () {
    $dir = makeTempMagentoDir();
    file_put_contents($dir.'/composer.json', json_encode([
        'repositories' => [
            ['type' => 'composer', 'url' => 'https://composer.amasty.com/enterprise/'],
        ],
    ], JSON_UNESCAPED_SLASHES));
    file_put_contents($dir.'/composer.lock', json_encode([
        'packages' => [
            ['dist' => ['url' => 'https://composer.amasty.com/enterprise/foo.zip']],
        ],
    ], JSON_UNESCAPED_SLASHES));

    $analyser = new ComposerAnalyser;
    $findings = $analyser->detectEnterpriseRepositories($dir);

    expect($findings)->toHaveCount(2);
    $files = array_column($findings, 'file');
    expect($files)->toContain('composer.json');
    expect($files)->toContain('composer.lock');

    cleanupTempDir($dir);
});
