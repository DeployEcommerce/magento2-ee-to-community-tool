<?php

namespace App\Services\Database;

use App\Contracts\DatabaseConnectionInterface;
use App\ValueObjects\DatabaseConfig;

class MagentoDatabaseConnection implements DatabaseConnectionInterface
{
    private ?\PDO $pdo = null;

    public function connect(DatabaseConfig $config): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config->host,
            $config->port,
            $config->dbname
        );

        $this->pdo = new \PDO($dsn, $config->username, $config->password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ]);
    }

    public function execute(string $sql): int
    {
        $this->assertConnected();
        $affected = $this->pdo->exec($sql);

        return $affected === false ? 0 : (int) $affected;
    }

    public function beginTransaction(): void
    {
        $this->assertConnected();
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->assertConnected();
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollback(): void
    {
        $this->assertConnected();
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function query(string $sql): array
    {
        $this->assertConnected();
        $stmt = $this->pdo->query($sql);

        return $stmt ? $stmt->fetchAll() : [];
    }

    private function assertConnected(): void
    {
        if ($this->pdo === null) {
            throw new \RuntimeException('Database connection not established. Call connect() first.');
        }
    }
}
