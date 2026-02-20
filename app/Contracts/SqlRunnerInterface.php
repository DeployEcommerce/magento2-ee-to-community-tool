<?php

namespace App\Contracts;

use App\ValueObjects\MigrationResult;

interface SqlRunnerInterface
{
    /** @return MigrationResult[] */
    public function runAll(): array;

    /** @return MigrationResult[] */
    public function runFrom(int $fromNumber): array;
}
