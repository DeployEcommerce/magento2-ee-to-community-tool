<?php

namespace App\Contracts;

use App\ValueObjects\SnapshotReport;

interface SnapshotInterface
{
    public function capture(DatabaseConnectionInterface $connection): SnapshotReport;

    public function save(SnapshotReport $report, string $directory): string;

    public function load(string $path): SnapshotReport;
}
