<?php

namespace App\Contracts;

use App\ValueObjects\DatabaseConfig;

interface DatabaseConnectionInterface
{
    public function connect(DatabaseConfig $config): void;

    public function execute(string $sql): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function query(string $sql): array;
}
