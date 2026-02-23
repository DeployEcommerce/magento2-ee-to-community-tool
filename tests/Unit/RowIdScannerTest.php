<?php

use App\Services\Scanner\RowIdScanner;

function createTempDirectory(): string
{
    $dir = sys_get_temp_dir().'/rowid_test_'.uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function createTempFile(string $directory, string $relativePath, string $content): string
{
    $fullPath = $directory.'/'.$relativePath;
    $dirPath = dirname($fullPath);
    if (! is_dir($dirPath)) {
        mkdir($dirPath, 0755, true);
    }
    file_put_contents($fullPath, $content);

    return $fullPath;
}

function cleanupTempDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($directory);
}

test('scanDirectory finds row_id in single quotes', function () {
    $dir = createTempDirectory();
    createTempFile($dir, 'Vendor/Module/Model/Test.php', "<?php\n\$data['row_id'] = 123;");

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir);

    expect($results)->toHaveCount(1);
    expect($results[0]->lineNumber)->toBe(2);
    expect($results[0]->lineContent)->toContain('row_id');

    cleanupTempDirectory($dir);
});

test('scanDirectory finds row_id in double quotes', function () {
    $dir = createTempDirectory();
    createTempFile($dir, 'Vendor/Module/Model/Test.php', "<?php\n\$data[\"row_id\"] = 123;");

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir);

    expect($results)->toHaveCount(1);
    expect($results[0]->lineContent)->toContain('row_id');

    cleanupTempDirectory($dir);
});

test('scanDirectory finds row_id property access', function () {
    $dir = createTempDirectory();
    createTempFile($dir, 'Vendor/Module/Model/Test.php', "<?php\n\$entity->row_id;");

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir);

    expect($results)->toHaveCount(1);
    expect($results[0]->lineContent)->toContain('->row_id');

    cleanupTempDirectory($dir);
});

test('scanDirectory extracts Vendor_Module extension name for app/code', function () {
    $dir = createTempDirectory().'/app/code';
    mkdir($dir, 0755, true);
    createTempFile($dir, 'Acme/CustomModule/Model/Product.php', "<?php\n\$row['row_id'];");

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir);

    expect($results)->toHaveCount(1);
    expect($results[0]->extensionName)->toBe('Acme_CustomModule');

    cleanupTempDirectory(dirname(dirname($dir)));
});

test('scanDirectory extracts vendor/package extension name', function () {
    $dir = createTempDirectory();
    createTempFile($dir, 'amasty/module-special/Model/Rule.php', "<?php\n\$data['row_id'];");

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir);

    expect($results)->toHaveCount(1);
    expect($results[0]->extensionName)->toBe('amasty/module-special');

    cleanupTempDirectory($dir);
});

test('scanDirectory returns empty array for non-existent directory', function () {
    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory('/non/existent/path');

    expect($results)->toBeEmpty();
});

test('scanDirectory ignores non-php files', function () {
    $dir = createTempDirectory();
    createTempFile($dir, 'Vendor/Module/docs.txt', 'row_id references here');
    createTempFile($dir, 'Vendor/Module/config.xml', '<field>row_id</field>');

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir);

    expect($results)->toBeEmpty();

    cleanupTempDirectory($dir);
});

test('scanDirectory respects exclude patterns', function () {
    $dir = createTempDirectory();
    createTempFile($dir, 'magento/module-staging/Model/Test.php', "<?php\n\$data['row_id'];");
    createTempFile($dir, 'amasty/module-promo/Model/Rule.php', "<?php\n\$data['row_id'];");

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir, ['*/magento/*']);

    expect($results)->toHaveCount(1);
    expect($results[0]->extensionName)->toBe('amasty/module-promo');

    cleanupTempDirectory($dir);
});

test('scanDirectory finds multiple occurrences in same file', function () {
    $dir = createTempDirectory();
    $content = <<<'PHP'
<?php
$data['row_id'] = 1;
$entity->row_id = 2;
$other["row_id"] = 3;
PHP;
    createTempFile($dir, 'Vendor/Module/Model/Test.php', $content);

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir);

    expect($results)->toHaveCount(3);
    expect($results[0]->lineNumber)->toBe(2);
    expect($results[1]->lineNumber)->toBe(3);
    expect($results[2]->lineNumber)->toBe(4);

    cleanupTempDirectory($dir);
});

test('scanDirectory is case insensitive for row_id', function () {
    $dir = createTempDirectory();
    createTempFile($dir, 'Vendor/Module/Model/Test.php', "<?php\n\$data['ROW_ID'];");

    $scanner = new RowIdScanner;
    $results = $scanner->scanDirectory($dir);

    expect($results)->toHaveCount(1);

    cleanupTempDirectory($dir);
});
