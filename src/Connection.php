<?php

declare(strict_types=1);

namespace Lukman\Database;

use Lukman\Database\Exception\QueryException;
use PDO;
use PDOException;
use Throwable;

final class Connection
{
    private int $transactions = 0;

    public function __construct(private readonly PDO $pdo, bool $configurePdo = true)
    {
        if (!$configurePdo) {
            return;
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public function select(string $query, array $bindings = []): array
    {
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute($bindings);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            throw new QueryException($query, $bindings, $exception);
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        return $this->select($query, $bindings)[0] ?? null;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $query, array $bindings = []): bool
    {
        try {
            $statement = $this->pdo->prepare($query);

            return $statement->execute($bindings);
        } catch (PDOException $exception) {
            throw new QueryException($query, $bindings, $exception);
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute($bindings);

            return $statement->rowCount();
        } catch (PDOException $exception) {
            throw new QueryException($query, $bindings, $exception);
        }
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        if ($this->transactions === 0) {
            $this->pdo->beginTransaction();
        }

        $this->transactions++;

        return true;
    }

    public function commit(): bool
    {
        if ($this->transactions === 0) {
            return false;
        }

        $this->transactions--;

        if ($this->transactions === 0) {
            $this->pdo->commit();
        }

        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactions === 0) {
            return false;
        }

        $this->transactions = 0;
        $this->pdo->rollBack();

        return true;
    }

    /**
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->rollBack();

            throw $exception;
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactions > 0 && $this->pdo->inTransaction();
    }
}
