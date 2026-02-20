<?php

namespace App\Contracts;

interface SqlLoggerInterface
{
    public function log(string $sql, int $affectedRows, float $durationMs): void;

    public function logError(string $sql, \Throwable $e): void;

    public function logFileStart(string $filename): void;

    public function getLogFilePath(): string;
}
