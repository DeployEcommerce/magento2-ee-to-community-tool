<?php

namespace App\ValueObjects;

final readonly class SnapshotReport
{
    public function __construct(
        public \DateTimeImmutable $capturedAt,
        public array $tableCounts,
        public array $tableChecksums,
        public array $eeTablesPresent,
        public array $rowIdColumnsPresent,
        public array $sequenceTablesPresent,
    ) {}

    public function toArray(): array
    {
        return [
            'capturedAt' => $this->capturedAt->format(\DateTimeInterface::ATOM),
            'tableCounts' => $this->tableCounts,
            'tableChecksums' => $this->tableChecksums,
            'eeTablesPresent' => $this->eeTablesPresent,
            'rowIdColumnsPresent' => $this->rowIdColumnsPresent,
            'sequenceTablesPresent' => $this->sequenceTablesPresent,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            capturedAt: new \DateTimeImmutable($data['capturedAt']),
            tableCounts: $data['tableCounts'],
            tableChecksums: $data['tableChecksums'],
            eeTablesPresent: $data['eeTablesPresent'],
            rowIdColumnsPresent: $data['rowIdColumnsPresent'],
            sequenceTablesPresent: $data['sequenceTablesPresent'],
        );
    }
}
