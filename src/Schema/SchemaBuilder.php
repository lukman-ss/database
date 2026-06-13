<?php

declare(strict_types=1);

namespace Lukman\Database\Schema;

use Lukman\Database\Connection;

final class SchemaBuilder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $table, callable $callback): bool
    {
        $blueprint = new Blueprint();
        $callback($blueprint);

        return $this->connection->statement($this->toSql($table, $blueprint));
    }

    public function hasTable(string $table): bool
    {
        return $this->connection->selectOne(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table]
        ) !== null;
    }

    public function drop(string $table): bool
    {
        return $this->connection->statement('DROP TABLE ' . $table);
    }

    public function dropIfExists(string $table): bool
    {
        return $this->connection->statement('DROP TABLE IF EXISTS ' . $table);
    }

    public function toSql(string $table, Blueprint $blueprint): string
    {
        return 'CREATE TABLE ' . $table . ' (' . implode(', ', $blueprint->columns()) . ')';
    }
}
