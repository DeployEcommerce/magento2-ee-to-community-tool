<?php

namespace App\ValueObjects;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $dbname,
        public string $username,
        public string $password,
    ) {}

    public static function fromEnvPhp(array $envPhp): self
    {
        $connection = $envPhp['db']['connection']['default']
            ?? throw new \InvalidArgumentException('Missing db.connection.default in env.php');

        $host = $connection['host'] ?? '127.0.0.1';
        $port = 3306;

        if (str_contains((string) $host, ':')) {
            [$host, $portStr] = explode(':', $host, 2);
            $port = (int) $portStr;
        }

        return new self(
            host: $host,
            port: $port,
            dbname: $connection['dbname'] ?? throw new \InvalidArgumentException('Missing dbname in env.php'),
            username: $connection['username'] ?? throw new \InvalidArgumentException('Missing username in env.php'),
            password: $connection['password'] ?? '',
        );
    }
}
