<?php

namespace App\Contracts;

use App\ValueObjects\RowIdScanResult;

interface RowIdScannerInterface
{
    /**
     * Scan a directory for row_id references in PHP files.
     *
     * @param  string  $directory  The directory to scan
     * @param  array<string>  $excludePatterns  Glob patterns to exclude
     * @return RowIdScanResult[]
     */
    public function scanDirectory(string $directory, array $excludePatterns = []): array;
}
