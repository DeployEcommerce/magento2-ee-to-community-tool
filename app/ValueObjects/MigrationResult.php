<?php

namespace App\ValueObjects;

final readonly class MigrationResult
{
    public function __construct(
        public int $fileNumber,
        public string $filename,
        public bool $success,
        public int $statementsExecuted,
        public float $durationMs,
        public ?string $error = null,
    ) {}
}
