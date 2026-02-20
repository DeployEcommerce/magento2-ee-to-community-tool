<?php

namespace App\Contracts;

use App\ValueObjects\SnapshotReport;

interface SnapshotComparatorInterface
{
    public function compare(SnapshotReport $before, SnapshotReport $after): array;

    public function assertSuccess(array $diff): bool;
}
