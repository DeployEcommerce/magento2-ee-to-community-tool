<?php

namespace App\Commands\Concerns;

use App\ValueObjects\RowIdScanResult;

trait ScansForRowId
{
    /**
     * @param  RowIdScanResult[]  $results
     * @return array<int, array{file: string, line: int, extension: string}>
     */
    protected function deduplicateResults(array $results): array
    {
        $entries = [];
        $seen = [];

        foreach ($results as $result) {
            $key = $result->filePath.':'.$result->lineNumber;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $entries[] = [
                'file' => $result->filePath,
                'line' => $result->lineNumber,
                'extension' => $result->extensionName,
            ];
        }

        return $entries;
    }

    /**
     * @param  RowIdScanResult[]  $results
     */
    protected function outputJson(array $results): void
    {
        $entries = $this->deduplicateResults($results);
        $this->line(json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  RowIdScanResult[]  $results
     */
    protected function outputMarkdown(array $results): void
    {
        $entries = $this->deduplicateResults($results);

        if (empty($entries)) {
            $this->line('No `row_id` references found.');

            return;
        }

        $this->line('# row_id References');
        $this->line('');
        $this->line(sprintf('Found **%d** references that may need updating after EE to CE migration.', count($entries)));
        $this->line('');
        $this->line('| Extension | File | Line |');
        $this->line('|-----------|------|------|');

        foreach ($entries as $entry) {
            $this->line(sprintf(
                '| %s | `%s` | %d |',
                $entry['extension'],
                $entry['file'],
                $entry['line']
            ));
        }
    }

    /**
     * @param  RowIdScanResult[]  $results
     */
    protected function displayResults(string $title, array $results): void
    {
        $this->line('┌'.str_repeat('─', 64).'┐');
        $this->line('│  '.str_pad($title, 62).'│');
        $this->line('└'.str_repeat('─', 64).'┘');
        $this->newLine();

        if (empty($results)) {
            $this->line('  <fg=green>✓</> No row_id references found');
            $this->newLine();

            return;
        }

        $grouped = $this->groupByExtension($results);

        foreach ($grouped as $extensionName => $extensionResults) {
            $count = count($extensionResults);
            $plural = $count === 1 ? 'reference' : 'references';
            $this->line("<fg=yellow>⚠</> {$extensionName} ({$count} {$plural})");

            foreach ($extensionResults as $result) {
                $relativePath = $this->getRelativePath($result->filePath, $extensionName);
                $this->line("   → {$relativePath}:{$result->lineNumber}");
            }

            $this->newLine();
        }
    }

    /**
     * @param  RowIdScanResult[]  $results
     * @return array<string, RowIdScanResult[]>
     */
    protected function groupByExtension(array $results): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $grouped[$result->extensionName][] = $result;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Get the file path relative to the extension root.
     */
    protected function getRelativePath(string $filePath, string $extensionName): string
    {
        // For Vendor_Module format (app/code)
        if (str_contains($extensionName, '_')) {
            $parts = explode('_', $extensionName);
            $marker = '/'.$parts[0].'/'.$parts[1].'/';
        } else {
            // For vendor/package format
            $marker = '/'.$extensionName.'/';
        }

        $position = strpos($filePath, $marker);
        if ($position !== false) {
            return substr($filePath, $position + strlen($marker));
        }

        return basename($filePath);
    }

    /**
     * @param  RowIdScanResult[]  $customResults
     * @param  RowIdScanResult[]  $vendorResults
     */
    protected function displaySummary(array $customResults, array $vendorResults): void
    {
        $this->line(str_repeat('─', 64));

        $totalReferences = count($customResults) + count($vendorResults);
        $customExtensions = count($this->groupByExtension($customResults));
        $vendorExtensions = count($this->groupByExtension($vendorResults));
        $totalExtensions = $customExtensions + $vendorExtensions;

        if ($totalReferences === 0) {
            $this->info('No row_id references found. Your codebase appears ready for CE.');

            return;
        }

        $this->line("Summary: {$totalReferences} references found in {$totalExtensions} extensions");

        if ($customExtensions > 0) {
            $plural = $customExtensions === 1 ? 'extension' : 'extensions';
            $this->line("  • {$customExtensions} custom {$plural} in app/code (requires modification)");
        }

        if ($vendorExtensions > 0) {
            $plural = $vendorExtensions === 1 ? 'extension' : 'extensions';
            $this->line("  • {$vendorExtensions} third-party {$plural} in vendor/ (may need CE version)");
        }
    }

    /**
     * @param  RowIdScanResult[]  $results
     */
    protected function displaySingleSummary(array $results, string $location): void
    {
        $this->line(str_repeat('─', 64));

        $totalReferences = count($results);
        $extensions = count($this->groupByExtension($results));

        if ($totalReferences === 0) {
            $this->info("No row_id references found in {$location}.");

            return;
        }

        $refPlural = $totalReferences === 1 ? 'reference' : 'references';
        $extPlural = $extensions === 1 ? 'extension' : 'extensions';
        $this->line("Summary: {$totalReferences} {$refPlural} found in {$extensions} {$extPlural}");
    }
}
