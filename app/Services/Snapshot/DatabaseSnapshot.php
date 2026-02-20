<?php

namespace App\Services\Snapshot;

use App\Contracts\DatabaseConnectionInterface;
use App\Contracts\SnapshotInterface;
use App\ValueObjects\SnapshotReport;

class DatabaseSnapshot implements SnapshotInterface
{
    private const KEY_TABLES = [
        'catalog_product_entity',
        'catalog_category_entity',
        'cms_page',
        'cms_block',
        'customer_entity',
        'sales_order',
        'quote',
        'catalogrule',
    ];

    private const EE_TABLES = [
        'enterprise_catalog_category_rewrite',
        'enterprise_catalog_product_rewrite',
        'enterprise_cms_hierarchy_lock',
        'enterprise_cms_hierarchy_metadata',
        'enterprise_cms_hierarchy_node',
        'enterprise_cms_increment',
        'enterprise_cms_page_revision',
        'enterprise_cms_page_version',
        'enterprise_customer_sales_flat_order',
        'enterprise_customer_sales_flat_order_address',
        'enterprise_giftregistry_data',
        'enterprise_giftregistry_entity',
        'enterprise_giftregistry_item',
        'enterprise_giftregistry_item_option',
        'enterprise_giftregistry_label',
        'enterprise_giftregistry_person',
        'enterprise_giftregistry_type',
        'enterprise_giftregistry_type_info',
        'enterprise_logging_event',
        'enterprise_logging_event_changes',
        'enterprise_permission_role_website',
        'enterprise_permission_variable',
        'enterprise_permission_website',
        'enterprise_reminder_rule',
        'enterprise_reminder_rule_coupon',
        'enterprise_reminder_rule_log',
        'enterprise_reminder_rule_website',
        'enterprise_reward',
        'enterprise_reward_history',
        'enterprise_reward_salesrule',
        'enterprise_reward_website',
        'magento_staging_update',
        'magento_staging_versions',
        'magento_banner',
        'magento_banner_content',
        'magento_banner_catalogrule',
        'magento_banner_salesrule',
        'magento_customerbalance',
        'magento_customerbalance_history',
        'magento_giftcardaccount',
        'magento_giftcardaccount_history',
        'magento_giftcardaccount_pool',
        'magento_reward',
        'magento_reward_history',
        'magento_reward_salesrule',
        'magento_reward_website',
        'magento_rma',
        'magento_rma_grid',
        'magento_rma_item_entity',
        'magento_rma_shipping_label',
        'magento_rma_status_history',
        'magento_sales_creditmemo_grid_archive',
        'magento_sales_invoice_grid_archive',
        'magento_sales_order_grid_archive',
        'magento_sales_shipment_grid_archive',
    ];

    public function capture(DatabaseConnectionInterface $connection): SnapshotReport
    {
        $tableCounts = $this->captureTableCounts($connection);
        $tableChecksums = $this->captureTableChecksums($connection);
        $eeTablesPresent = $this->findEeTables($connection);
        $rowIdColumnsPresent = $this->findRowIdColumns($connection);
        $sequenceTablesPresent = $this->findSequenceTables($connection);

        return new SnapshotReport(
            capturedAt: new \DateTimeImmutable(),
            tableCounts: $tableCounts,
            tableChecksums: $tableChecksums,
            eeTablesPresent: $eeTablesPresent,
            rowIdColumnsPresent: $rowIdColumnsPresent,
            sequenceTablesPresent: $sequenceTablesPresent,
        );
    }

    public function save(SnapshotReport $report, string $directory): string
    {
        $timestamp = $report->capturedAt->format('Ymd-His');
        $path = rtrim($directory, '/') . "/snapshot-before-{$timestamp}.json";

        $json = json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json);

        return $path;
    }

    public function load(string $path): SnapshotReport
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Snapshot file not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true);
        if ($data === null) {
            throw new \RuntimeException("Invalid JSON in snapshot file: {$path}");
        }

        return SnapshotReport::fromArray($data);
    }

    private function captureTableCounts(DatabaseConnectionInterface $connection): array
    {
        $counts = [];
        foreach (self::KEY_TABLES as $table) {
            try {
                $rows = $connection->query("SELECT COUNT(*) as cnt FROM `{$table}`");
                $counts[$table] = (int) ($rows[0]['cnt'] ?? 0);
            } catch (\Throwable) {
                $counts[$table] = null;
            }
        }
        return $counts;
    }

    private function captureTableChecksums(DatabaseConnectionInterface $connection): array
    {
        $checksums = [];
        foreach (self::KEY_TABLES as $table) {
            try {
                $rows = $connection->query("CHECKSUM TABLE `{$table}`");
                $checksums[$table] = (string) ($rows[0]['Checksum'] ?? '');
            } catch (\Throwable) {
                $checksums[$table] = null;
            }
        }
        return $checksums;
    }

    private function findEeTables(DatabaseConnectionInterface $connection): array
    {
        $found = [];
        try {
            $rows = $connection->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
            $existing = array_column($rows, 'TABLE_NAME');
            $found = array_values(array_intersect(self::EE_TABLES, $existing));
        } catch (\Throwable) {
        }
        return $found;
    }

    private function findRowIdColumns(DatabaseConnectionInterface $connection): array
    {
        try {
            // Exclude flat catalog tables (regenerated by indexer after CE migration)
            // and paypal_settlement_report_row (CE table with a legitimate row_id column)
            $rows = $connection->query(
                "SELECT TABLE_NAME FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'row_id'
                AND TABLE_NAME NOT LIKE 'catalog_product_flat_%'
                AND TABLE_NAME NOT LIKE 'catalog_category_flat_%'
                AND TABLE_NAME != 'paypal_settlement_report_row'
                ORDER BY TABLE_NAME"
            );
            return array_column($rows, 'TABLE_NAME');
        } catch (\Throwable) {
            return [];
        }
    }

    private function findSequenceTables(DatabaseConnectionInterface $connection): array
    {
        // Only check for EE-specific sequence tables that should have been removed.
        // CE-legitimate sequence tables (sequence_order_*, sequence_invoice_*, etc.) are intentionally kept.
        $eeSequenceTables = [
            'sequence_product',
            'sequence_catalog_category',
            'sequence_cms_page',
            'sequence_cms_block',
            'sequence_catalogrule',
            'sequence_salesrule',
            'sequence_product_bundle_option',
            'sequence_product_bundle_selection',
        ];

        try {
            $rows = $connection->query(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'sequence_%'
                ORDER BY TABLE_NAME"
            );
            $allSequence = array_column($rows, 'TABLE_NAME');
            return array_values(array_intersect($allSequence, $eeSequenceTables));
        } catch (\Throwable) {
            return [];
        }
    }
}
