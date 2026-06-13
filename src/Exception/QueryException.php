<?php

declare(strict_types=1);

namespace Lukman\Database\Exception;

use PDOException;
use RuntimeException;

final class QueryException extends RuntimeException
{
    /**
     * @param array<int|string, mixed> $bindings
     */
    public function __construct(
        private readonly string $query,
        private readonly array $bindings,
        PDOException $previous
    ) {
        parent::__construct($previous->getMessage(), (int) $previous->getCode(), $previous);
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
