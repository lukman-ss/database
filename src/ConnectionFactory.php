<?php

declare(strict_types=1);

namespace Lukman\Database;

use Lukman\Database\Exception\ConnectionException;
use PDO;
use PDOException;

class ConnectionFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): Connection
    {
        $driver = $this->driver($config);
        $dsn = $this->makeDsn($config);
        $username = $driver === 'sqlite' ? null : ($config['username'] ?? null);
        $password = $driver === 'sqlite' ? null : ($config['password'] ?? null);
        $options = $this->options($config['options'] ?? []);

        try {
            return new Connection(new PDO($dsn, $username, $password, $options), false);
        } catch (PDOException $exception) {
            throw new ConnectionException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function makeDsn(array $config): string
    {
        return match ($this->driver($config)) {
            'sqlite' => $this->sqliteDsn($config),
            'mysql' => $this->mysqlDsn($config),
            'pgsql' => $this->pgsqlDsn($config),
            default => throw new ConnectionException('Unsupported database driver.'),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function driver(array $config): string
    {
        if (!isset($config['driver']) || $config['driver'] === '') {
            throw new ConnectionException('Database driver is required.');
        }

        return (string) $config['driver'];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function sqliteDsn(array $config): string
    {
        if (($config['database'] ?? null) === ':memory:') {
            return 'sqlite::memory:';
        }

        if (!isset($config['database']) || $config['database'] === '') {
            throw new ConnectionException('SQLite database is required.');
        }

        return 'sqlite:' . $config['database'];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mysqlDsn(array $config): string
    {
        $host = $this->required($config, 'host');
        $database = $this->required($config, 'database');
        $port = isset($config['port']) ? ';port=' . $config['port'] : '';
        $charset = isset($config['charset']) ? ';charset=' . $config['charset'] : '';

        return "mysql:host={$host};dbname={$database}{$port}{$charset}";
    }

    /**
     * @param array<string, mixed> $config
     */
    private function pgsqlDsn(array $config): string
    {
        $host = $this->required($config, 'host');
        $database = $this->required($config, 'database');
        $port = isset($config['port']) ? ';port=' . $config['port'] : '';

        return "pgsql:host={$host};dbname={$database}{$port}";
    }

    /**
     * @param array<string, mixed> $config
     */
    private function required(array $config, string $key): string
    {
        if (!isset($config[$key]) || $config[$key] === '') {
            throw new ConnectionException("Database {$key} is required.");
        }

        return (string) $config[$key];
    }

    /**
     * @param array<int, mixed> $options
     * @return array<int, mixed>
     */
    private function options(array $options): array
    {
        return $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }
}
