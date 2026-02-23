<?php

use App\Services\Snapshot\SnapshotComparator;
use App\ValueObjects\SnapshotReport;

function makeReport(array $overrides = []): SnapshotReport
{
    return new SnapshotReport(
        capturedAt: new \DateTimeImmutable('2026-02-20 12:00:00'),
        tableCounts: $overrides['tableCounts'] ?? ['catalog_product_entity' => 100, 'cms_page' => 10],
        tableChecksums: $overrides['tableChecksums'] ?? ['catalog_product_entity' => 'abc123'],
        eeTablesPresent: $overrides['eeTablesPresent'] ?? [],
        rowIdColumnsPresent: $overrides['rowIdColumnsPresent'] ?? [],
        sequenceTablesPresent: $overrides['sequenceTablesPresent'] ?? [],
    );
}

test('assertSuccess returns true when all EE indicators are empty', function () {
    $comparator = new SnapshotComparator;

    $before = makeReport(['eeTablesPresent' => ['magento_staging_update', 'magento_banner']]);
    $after = makeReport();

    $diff = $comparator->compare($before, $after);
    expect($comparator->assertSuccess($diff))->toBeTrue();
});

test('assertSuccess returns false when EE tables remain', function () {
    $comparator = new SnapshotComparator;

    $before = makeReport();
    $after = makeReport(['eeTablesPresent' => ['magento_staging_update']]);

    $diff = $comparator->compare($before, $after);
    expect($comparator->assertSuccess($diff))->toBeFalse();
});

test('assertSuccess returns false when row_id columns remain', function () {
    $comparator = new SnapshotComparator;

    $before = makeReport();
    $after = makeReport(['rowIdColumnsPresent' => ['catalog_product_entity']]);

    $diff = $comparator->compare($before, $after);
    expect($comparator->assertSuccess($diff))->toBeFalse();
});

test('assertSuccess returns false when sequence tables remain', function () {
    $comparator = new SnapshotComparator;

    $before = makeReport();
    $after = makeReport(['sequenceTablesPresent' => ['sequence_order_1']]);

    $diff = $comparator->compare($before, $after);
    expect($comparator->assertSuccess($diff))->toBeFalse();
});

test('compare reports correct row count deltas', function () {
    $comparator = new SnapshotComparator;

    $before = makeReport(['tableCounts' => ['catalog_product_entity' => 100, 'cms_page' => 10]]);
    $after = makeReport(['tableCounts' => ['catalog_product_entity' => 98, 'cms_page' => 8]]);

    $diff = $comparator->compare($before, $after);

    expect($diff['rowCountDeltas']['catalog_product_entity']['delta'])->toBe(-2);
    expect($diff['rowCountDeltas']['cms_page']['delta'])->toBe(-2);
});

test('compare reports EE tables removed count', function () {
    $comparator = new SnapshotComparator;

    $before = makeReport(['eeTablesPresent' => ['magento_staging_update', 'magento_banner', 'magento_rma']]);
    $after = makeReport(['eeTablesPresent' => []]);

    $diff = $comparator->compare($before, $after);

    expect($diff['eeTablesRemovedCount'])->toBe(3);
    expect($diff['eeTablesRemaining'])->toBeEmpty();
});

test('compare detects checksum changes', function () {
    $comparator = new SnapshotComparator;

    $before = makeReport(['tableChecksums' => ['catalog_product_entity' => 'abc123']]);
    $after = makeReport(['tableChecksums' => ['catalog_product_entity' => 'xyz789']]);

    $diff = $comparator->compare($before, $after);

    expect($diff['checksumChanges'])->toHaveKey('catalog_product_entity');
    expect($diff['checksumChanges']['catalog_product_entity']['before'])->toBe('abc123');
    expect($diff['checksumChanges']['catalog_product_entity']['after'])->toBe('xyz789');
});

test('SnapshotReport serializes and deserializes correctly', function () {
    $report = makeReport([
        'tableCounts' => ['catalog_product_entity' => 42],
        'eeTablesPresent' => ['magento_staging_update'],
        'rowIdColumnsPresent' => ['catalog_product_entity'],
        'sequenceTablesPresent' => ['sequence_order_1'],
    ]);

    $array = $report->toArray();
    $restored = SnapshotReport::fromArray($array);

    expect($restored->tableCounts)->toBe($report->tableCounts);
    expect($restored->eeTablesPresent)->toBe($report->eeTablesPresent);
    expect($restored->rowIdColumnsPresent)->toBe($report->rowIdColumnsPresent);
    expect($restored->sequenceTablesPresent)->toBe($report->sequenceTablesPresent);
    expect($restored->capturedAt->format('Y-m-d H:i:s'))->toBe('2026-02-20 12:00:00');
});
