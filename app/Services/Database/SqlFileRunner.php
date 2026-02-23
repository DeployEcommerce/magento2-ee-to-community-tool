<?php

namespace App\Services\Database;

use App\Contracts\DatabaseConnectionInterface;
use App\Contracts\SqlLoggerInterface;
use App\Contracts\SqlRunnerInterface;
use App\ValueObjects\MigrationResult;

class SqlFileRunner implements SqlRunnerInterface
{
    public function __construct(
        private readonly DatabaseConnectionInterface $connection,
        private readonly SqlLoggerInterface $logger,
        private readonly string $sqlDirectory,
    ) {}

    public function runAll(): array
    {
        return $this->runFrom(1);
    }

    public function runFrom(int $fromNumber): array
    {
        $files = $this->getSqlFiles();
        $results = [];

        foreach ($files as $fileNumber => $filePath) {
            if ($fileNumber < $fromNumber) {
                continue;
            }
            $results[] = $this->runFile($fileNumber, $filePath);
        }

        return $results;
    }

    /**
     * @return array<int, string> fileNumber => filePath
     */
    private function getSqlFiles(): array
    {
        if (! is_dir($this->sqlDirectory)) {
            throw new \RuntimeException("No SQL files found in [{$this->sqlDirectory}]");
        }

        $files = [];
        foreach (new \DirectoryIterator($this->sqlDirectory) as $entry) {
            if ($entry->isDot() || $entry->getExtension() !== 'sql') {
                continue;
            }
            $files[] = $entry->getPathname();
        }

        if (count($files) === 0) {
            throw new \RuntimeException("No SQL files found in [{$this->sqlDirectory}]");
        }

        sort($files);

        $numbered = [];
        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/^(\d+)_/', $basename, $matches)) {
                $numbered[(int) $matches[1]] = $file;
            }
        }

        return $numbered;
    }

    private function runFile(int $fileNumber, string $filePath): MigrationResult
    {
        $filename = basename($filePath);
        $this->logger->logFileStart($filename);

        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new \RuntimeException("Cannot read SQL file: {$filePath}");
        }

        $statements = $this->splitStatements($sql);
        $statementsExecuted = 0;
        $startTime = microtime(true);

        try {
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }

                $stmtStart = microtime(true);
                $affected = $this->connection->execute($statement);
                $durationMs = (microtime(true) - $stmtStart) * 1000;

                $this->logger->log($statement, $affected, $durationMs);
                $statementsExecuted++;
            }

            $totalMs = (microtime(true) - $startTime) * 1000;

            return new MigrationResult(
                fileNumber: $fileNumber,
                filename: $filename,
                success: true,
                statementsExecuted: $statementsExecuted,
                durationMs: $totalMs,
            );
        } catch (\Throwable $e) {
            $totalMs = (microtime(true) - $startTime) * 1000;
            $this->logger->logError('', $e);

            return new MigrationResult(
                fileNumber: $fileNumber,
                filename: $filename,
                success: false,
                statementsExecuted: $statementsExecuted,
                durationMs: $totalMs,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Split SQL content into individual statements.
     * Handles DELIMITER $$ blocks used in stored procedures/triggers.
     */
    public function splitStatements(string $sql): array
    {
        $statements = [];
        $delimiter = ';';
        $lines = explode("\n", $sql);
        $buffer = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Handle DELIMITER changes
            if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $trimmed, $matches)) {
                $delimiter = $matches[1];

                continue;
            }

            $buffer .= $line."\n";

            // Check if buffer ends with current delimiter
            $bufferTrimmed = rtrim($buffer);
            if (str_ends_with($bufferTrimmed, $delimiter)) {
                // Remove the delimiter from the end
                $statement = substr($bufferTrimmed, 0, -strlen($delimiter));
                $statement = trim($statement);

                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }

        // Flush remaining buffer
        $remaining = trim($buffer);
        if ($remaining !== '') {
            $statements[] = $remaining;
        }

        return $statements;
    }
}
