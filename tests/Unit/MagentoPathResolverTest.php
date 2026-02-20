<?php

use App\Services\MagentoPathResolver;

test('resolve returns provided path when env.php exists', function () {
    $tempDir = sys_get_temp_dir() . '/magento_test_' . uniqid();
    mkdir($tempDir . '/app/etc', 0755, true);
    file_put_contents($tempDir . '/app/etc/env.php', '<?php return [];');

    $resolver = new MagentoPathResolver();
    $result = $resolver->resolve($tempDir);

    expect($result)->toBe($tempDir);

    // Cleanup
    unlink($tempDir . '/app/etc/env.php');
    rmdir($tempDir . '/app/etc');
    rmdir($tempDir . '/app');
    rmdir($tempDir);
});

test('resolve strips trailing slash', function () {
    $tempDir = sys_get_temp_dir() . '/magento_test_' . uniqid();
    mkdir($tempDir . '/app/etc', 0755, true);
    file_put_contents($tempDir . '/app/etc/env.php', '<?php return [];');

    $resolver = new MagentoPathResolver();
    $result = $resolver->resolve($tempDir . '/');

    expect($result)->toBe($tempDir);

    // Cleanup
    unlink($tempDir . '/app/etc/env.php');
    rmdir($tempDir . '/app/etc');
    rmdir($tempDir . '/app');
    rmdir($tempDir);
});

test('resolve throws when env.php does not exist', function () {
    $resolver = new MagentoPathResolver();
    $resolver->resolve('/non/existent/path');
})->throws(\RuntimeException::class, 'app/etc/env.php not found');

test('resolve uses cwd when path is null', function () {
    // Use a dedicated temp directory to avoid polluting the project tree
    $tempDir = sys_get_temp_dir() . '/magento_cwd_test_' . uniqid();
    mkdir($tempDir . '/app/etc', 0755, true);
    file_put_contents($tempDir . '/app/etc/env.php', '<?php return [];');
    $tempDir = realpath($tempDir); // resolve symlinks (macOS /var -> /private/var)

    $originalDir = getcwd();
    chdir($tempDir);

    try {
        $resolver = new MagentoPathResolver();
        $result = $resolver->resolve(null);
        expect($result)->toBe($tempDir);
    } finally {
        chdir($originalDir);
        unlink($tempDir . '/app/etc/env.php');
        rmdir($tempDir . '/app/etc');
        rmdir($tempDir . '/app');
        rmdir($tempDir);
    }
});
