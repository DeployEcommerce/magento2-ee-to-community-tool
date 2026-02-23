<?php

use App\Commands\ScanRowIdCommand;
use App\Commands\ScanRowIdCustomCommand;
use App\Commands\ScanRowIdVendorCommand;
use App\Contracts\MagentoPathResolverInterface;
use App\Contracts\RowIdScannerInterface;
use App\Services\DisclaimerService;
use App\ValueObjects\RowIdScanResult;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function createMockDisclaimer(): DisclaimerService
{
    $disclaimer = Mockery::mock(DisclaimerService::class);
    $disclaimer->shouldReceive('accept')->andReturn(null);
    $disclaimer->shouldReceive('requireConfirmation')->andReturn(null);

    return $disclaimer;
}

function runCommand(mixed $command, array $options = []): BufferedOutput
{
    $input = new ArrayInput(array_merge(['--accept-terms' => true], $options));
    $output = new BufferedOutput;

    $command->setLaravel(app());
    $command->run($input, $output);

    return $output;
}

// =============================================================================
// scan:row-id (combined)
// =============================================================================

test('scan:row-id displays results from both directories', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/app/code')
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/app/code/Vendor/Module/Model/Product.php', 45, 'row_id', 'Vendor_Module'),
        ]);

    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/vendor', Mockery::any())
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/vendor/amasty/module-promo/Model/Rule.php', 112, 'row_id', 'amasty/module-promo'),
        ]);

    $command = new ScanRowIdCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command)->fetch();

    expect($content)->toContain('Vendor_Module');
    expect($content)->toContain('amasty/module-promo');
    expect($content)->toContain('2 references found in 2 extensions');
});

test('scan:row-id outputs JSON when --json flag is used', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/app/code')
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/app/code/Vendor/Module/Model/Product.php', 45, 'row_id', 'Vendor_Module'),
        ]);

    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/vendor', Mockery::any())
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/vendor/amasty/promo/Rule.php', 10, 'row_id', 'amasty/promo'),
        ]);

    $command = new ScanRowIdCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--json' => true])->fetch();

    $json = json_decode($content, true);
    expect($json)->toBeArray();
    expect($json)->toHaveCount(2);
    expect($json[0])->toHaveKeys(['file', 'line', 'extension']);
    expect($json[0]['file'])->toBe('/tmp/magento/app/code/Vendor/Module/Model/Product.php');
    expect($json[0]['line'])->toBe(45);
});

test('scan:row-id JSON output deduplicates by file and line', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/app/code')
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/app/code/Vendor/Module/Model/Product.php', 45, 'row_id', 'Vendor_Module'),
            new RowIdScanResult('/tmp/magento/app/code/Vendor/Module/Model/Product.php', 45, 'row_id again', 'Vendor_Module'),
        ]);

    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/vendor', Mockery::any())
        ->once()
        ->andReturn([]);

    $command = new ScanRowIdCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--json' => true])->fetch();

    $json = json_decode($content, true);
    expect($json)->toHaveCount(1);
});

// =============================================================================
// scan:row-id:custom
// =============================================================================

test('scan:row-id:custom only scans app/code directory', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/app/code')
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/app/code/Acme/Module/Model/Test.php', 10, 'row_id', 'Acme_Module'),
        ]);

    $command = new ScanRowIdCustomCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command)->fetch();

    expect($content)->toContain('Acme_Module');
    expect($content)->toContain('Custom Extensions (app/code)');
    expect($content)->not->toContain('vendor');
});

test('scan:row-id:custom outputs JSON', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/app/code')
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/app/code/Acme/Module/Model/Test.php', 10, 'row_id', 'Acme_Module'),
        ]);

    $command = new ScanRowIdCustomCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--json' => true])->fetch();

    $json = json_decode($content, true);
    expect($json)->toBeArray();
    expect($json)->toHaveCount(1);
    expect($json[0]['extension'])->toBe('Acme_Module');
});

// =============================================================================
// scan:row-id:vendor
// =============================================================================

test('scan:row-id:vendor only scans vendor directory excluding magento', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/vendor', ['*/magento/*', '*magento/*'])
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/vendor/mirasvit/rewards/Model/Earning.php', 89, 'row_id', 'mirasvit/rewards'),
        ]);

    $command = new ScanRowIdVendorCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command)->fetch();

    expect($content)->toContain('mirasvit/rewards');
    expect($content)->toContain('Third-Party Extensions (vendor/)');
    expect($content)->not->toContain('app/code');
});

test('scan:row-id:vendor outputs JSON', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/vendor', ['*/magento/*', '*magento/*'])
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/vendor/mirasvit/rewards/Model/Earning.php', 89, 'row_id', 'mirasvit/rewards'),
        ]);

    $command = new ScanRowIdVendorCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--json' => true])->fetch();

    $json = json_decode($content, true);
    expect($json)->toBeArray();
    expect($json)->toHaveCount(1);
    expect($json[0]['extension'])->toBe('mirasvit/rewards');
});

// =============================================================================
// Edge cases
// =============================================================================

test('scan commands show success message when no references found', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')->andReturn([]);

    $command = new ScanRowIdCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command)->fetch();

    expect($content)->toContain('No row_id references found');
});

test('scan commands output empty JSON array when no references found', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')->andReturn([]);

    $command = new ScanRowIdCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--json' => true])->fetch();

    $json = json_decode($content, true);
    expect($json)->toBeArray();
    expect($json)->toBeEmpty();
});

// =============================================================================
// Markdown output
// =============================================================================

test('scan:row-id outputs Markdown when --markdown flag is used', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/app/code')
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/app/code/Vendor/Module/Model/Product.php', 45, 'row_id', 'Vendor_Module'),
        ]);

    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/vendor', Mockery::any())
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/vendor/amasty/promo/Rule.php', 10, 'row_id', 'amasty/promo'),
        ]);

    $command = new ScanRowIdCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--markdown' => true])->fetch();

    expect($content)->toContain('# row_id References');
    expect($content)->toContain('Found **2** references');
    expect($content)->toContain('| Extension | File | Line |');
    expect($content)->toContain('| Vendor_Module |');
    expect($content)->toContain('| amasty/promo |');
});

test('scan:row-id:custom outputs Markdown', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/app/code')
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/app/code/Acme/Module/Model/Test.php', 10, 'row_id', 'Acme_Module'),
        ]);

    $command = new ScanRowIdCustomCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--markdown' => true])->fetch();

    expect($content)->toContain('# row_id References');
    expect($content)->toContain('| Acme_Module |');
});

test('scan:row-id:vendor outputs Markdown', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')
        ->with('/tmp/magento/vendor', ['*/magento/*', '*magento/*'])
        ->once()
        ->andReturn([
            new RowIdScanResult('/tmp/magento/vendor/mirasvit/rewards/Model/Earning.php', 89, 'row_id', 'mirasvit/rewards'),
        ]);

    $command = new ScanRowIdVendorCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--markdown' => true])->fetch();

    expect($content)->toContain('# row_id References');
    expect($content)->toContain('| mirasvit/rewards |');
});

test('Markdown output shows message when no references found', function () {
    $resolver = Mockery::mock(MagentoPathResolverInterface::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('/tmp/magento');

    $scanner = Mockery::mock(RowIdScannerInterface::class);
    $scanner->shouldReceive('scanDirectory')->andReturn([]);

    $command = new ScanRowIdCommand(createMockDisclaimer(), $resolver, $scanner);
    $content = runCommand($command, ['--markdown' => true])->fetch();

    expect($content)->toContain('No `row_id` references found.');
});
