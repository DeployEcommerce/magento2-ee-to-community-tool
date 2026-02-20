<?php

namespace App\Services\Database;

use App\Contracts\SqlLoggerInterface;

class SqlLogger implements SqlLoggerInterface
{
    private string $logFilePath;
    private bool $initialized = false;

    public function __construct()
    {
        $timestamp = date('Ymd-His');
        $this->logFilePath = getcwd() . "/ee-to-ce-migration-{$timestamp}.sql.log";
    }

    public function log(string $sql, int $affectedRows, float $durationMs): void
    {
        $this->ensureInitialized();
        $timestamp = date('Y-m-d H:i:s');
        $entry = sprintf(
            "-- [%s] Affected: %d rows | Duration: %.2fms\n%s\n\n",
            $timestamp,
            $affectedRows,
            $durationMs,
            rtrim($sql, ';') . ';'
        );
        file_put_contents($this->logFilePath, $entry, FILE_APPEND);
    }

    public function logError(string $sql, \Throwable $e): void
    {
        $this->ensureInitialized();
        $timestamp = date('Y-m-d H:i:s');
        $entry = sprintf(
            "-- [%s] ERROR: %s\n%s\n\n",
            $timestamp,
            $e->getMessage(),
            rtrim($sql, ';') . ';'
        );
        file_put_contents($this->logFilePath, $entry, FILE_APPEND);
    }

    public function logFileStart(string $filename): void
    {
        $this->ensureInitialized();
        $timestamp = date('Y-m-d H:i:s');
        $entry = sprintf("\n-- [%s] FILE: %s\n", $timestamp, $filename);
        file_put_contents($this->logFilePath, $entry, FILE_APPEND);
    }

    public function getLogFilePath(): string
    {
        return $this->logFilePath;
    }

    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $header = sprintf(
            "-- EE to CE Migration Log\n-- Started: %s\n\n",
            date('Y-m-d H:i:s')
        );
        file_put_contents($this->logFilePath, $header);
        $this->initialized = true;
    }
}
