<?php

namespace App\Services\Scanner;

use App\Contracts\RowIdScannerInterface;
use App\ValueObjects\RowIdScanResult;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class RowIdScanner implements RowIdScannerInterface
{
    /**
     * Patterns to detect row_id references in PHP code.
     * Matches: 'row_id', "row_id", ->row_id, ['row_id'], ["row_id"]
     */
    private const ROW_ID_PATTERN = '/[\'"]row_id[\'"]|->row_id\b/i';

    /**
     * {@inheritDoc}
     */
    public function scanDirectory(string $directory, array $excludePatterns = []): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        // Normalize the directory path to handle symlinks (e.g., /var -> /private/var on macOS)
        $normalizedDirectory = realpath($directory) ?: $directory;

        $results = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($normalizedDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();

            if ($this->shouldExclude($filePath, $excludePatterns)) {
                continue;
            }

            $fileResults = $this->scanFile($filePath, $normalizedDirectory);
            $results = array_merge($results, $fileResults);
        }

        return $results;
    }

    /**
     * Scan a single file for row_id references.
     *
     * @return RowIdScanResult[]
     */
    private function scanFile(string $filePath, string $baseDirectory): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $results = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineIndex => $line) {
            if (preg_match(self::ROW_ID_PATTERN, $line)) {
                $results[] = new RowIdScanResult(
                    filePath: $filePath,
                    lineNumber: $lineIndex + 1,
                    lineContent: trim($line),
                    extensionName: $this->extractExtensionName($filePath, $baseDirectory),
                );
            }
        }

        return $results;
    }

    /**
     * Extract extension name from file path.
     * For app/code: Vendor_Module format
     * For vendor: vendor/package format
     */
    private function extractExtensionName(string $filePath, string $baseDirectory): string
    {
        $relativePath = str_replace($baseDirectory.'/', '', $filePath);
        $parts = explode('/', $relativePath);

        // app/code/Vendor/Module/... -> Vendor_Module
        if (count($parts) >= 2 && $this->isAppCodePath($baseDirectory)) {
            return $parts[0].'_'.$parts[1];
        }

        // vendor/vendor-name/package-name/... -> vendor-name/package-name
        if (count($parts) >= 2) {
            return $parts[0].'/'.$parts[1];
        }

        return $parts[0] ?? 'unknown';
    }

    /**
     * Check if the base directory is an app/code path.
     */
    private function isAppCodePath(string $directory): bool
    {
        return str_ends_with($directory, '/app/code') || str_ends_with($directory, '/app/code/');
    }

    /**
     * Check if a file path matches any exclude pattern.
     */
    private function shouldExclude(string $filePath, array $excludePatterns): bool
    {
        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $filePath)) {
                return true;
            }
        }

        return false;
    }
}
