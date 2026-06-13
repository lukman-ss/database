<?php

declare(strict_types=1);

namespace Lukman\Database;

use Lukman\Database\Exception\ConnectionException;

final class DatabaseManager
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $configs = [];

    /**
     * @var array<string, Connection>
     */
    private array $connections = [];

    private ?string $defaultConnection = null;

    public function __construct(private readonly ConnectionFactory $factory = new ConnectionFactory())
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function addConnection(string $name, array $config, bool $default = false): void
    {
        $this->configs[$name] = $config;

        if ($default || $this->defaultConnection === null) {
            $this->defaultConnection = $name;
        }
    }

    public function getDefaultConnection(): ?string
    {
        return $this->defaultConnection;
    }

    public function setDefaultConnection(string $name): void
    {
        if (!isset($this->configs[$name])) {
            throw new ConnectionException("Database connection [{$name}] is not configured.");
        }

        $this->defaultConnection = $name;
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->defaultConnection;

        if ($name === null || !isset($this->configs[$name])) {
            throw new ConnectionException('Database connection is not configured.');
        }

        return $this->connections[$name] ??= $this->factory->create($this->configs[$name]);
    }

    public function purge(?string $name = null): void
    {
        $name ??= $this->defaultConnection;

        if ($name !== null) {
            unset($this->connections[$name]);
        }
    }

    public function disconnect(?string $name = null): void
    {
        $this->purge($name);
    }

    public function reconnect(?string $name = null): Connection
    {
        $this->purge($name);

        return $this->connection($name);
    }
}
