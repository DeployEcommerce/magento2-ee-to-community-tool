<?php

namespace App\Services\Snapshot;

use App\Contracts\SnapshotComparatorInterface;
use App\ValueObjects\SnapshotReport;

class SnapshotComparator implements SnapshotComparatorInterface
{
    public function compare(SnapshotReport $before, SnapshotReport $after): array
    {
        $rowCountDeltas = [];
        foreach ($before->tableCounts as $table => $beforeCount) {
            $afterCount = $after->tableCounts[$table] ?? null;
            $rowCountDeltas[$table] = [
                'before' => $beforeCount,
                'after' => $afterCount,
                'delta' => ($beforeCount !== null && $afterCount !== null)
                    ? $afterCount - $beforeCount
                    : null,
            ];
        }

        return [
            'eeTablesRemaining' => $after->eeTablesPresent,
            'rowIdColumnsRemaining' => $after->rowIdColumnsPresent,
            'sequenceTablesRemaining' => $after->sequenceTablesPresent,
            'eeTablesRemovedCount' => count($before->eeTablesPresent) - count($after->eeTablesPresent),
            'rowCountDeltas' => $rowCountDeltas,
            'checksumChanges' => $this->compareChecksums($before->tableChecksums, $after->tableChecksums),
        ];
    }

    public function assertSuccess(array $diff): bool
    {
        return count($diff['eeTablesRemaining']) === 0
            && count($diff['rowIdColumnsRemaining']) === 0
            && count($diff['sequenceTablesRemaining']) === 0;
    }

    private function compareChecksums(array $before, array $after): array
    {
        $changes = [];
        foreach ($before as $table => $beforeChecksum) {
            $afterChecksum = $after[$table] ?? null;
            if ($beforeChecksum !== $afterChecksum) {
                $changes[$table] = ['before' => $beforeChecksum, 'after' => $afterChecksum];
            }
        }
        return $changes;
    }
}
