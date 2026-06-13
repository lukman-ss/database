<?php

declare(strict_types=1);

namespace Lukman\Database;

use InvalidArgumentException;
use LogicException;
use Lukman\Database\Exception\QueryException;
use PDOException;

final class QueryBuilder
{
    private ?string $table = null;

    /**
     * @var array<int, string|Expression>
     */
    private array $columns = ['*'];

    /**
     * @var array<int, mixed>
     */
    private array $selectBindings = [];

    /**
     * @var array<int, array{type: string, boolean: string, column?: string, operator?: string, value?: mixed, sql?: string, bindings?: array<int, mixed>}>
     */
    private array $wheres = [];

    /**
     * @var array<int, array{type: string, table: string, first: string, operator: string, second: string}>
     */
    private array $joins = [];

    /**
     * @var array<int, array{column: string, direction: string}>
     */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function table(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function select(string|Expression ...$columns): self
    {
        $this->columns = $columns === [] ? ['*'] : $columns;

        return $this;
    }

    /**
     * @param array<int, mixed> $bindings
     */
    public function selectRaw(string $sql, array $bindings = []): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        $this->columns[] = new Expression($sql);
        array_push($this->selectBindings, ...array_values($bindings));

        return $this;
    }

    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'boolean' => 'and',
            'column' => $column,
            'operator' => (string) $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'boolean' => 'or',
            'column' => $column,
            'operator' => (string) $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * @param array<int, mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean);

        if (!in_array($boolean, ['and', 'or'], true)) {
            throw new InvalidArgumentException('Where boolean must be and or or.');
        }

        $this->wheres[] = [
            'type' => 'raw',
            'boolean' => $boolean,
            'sql' => $sql,
            'bindings' => array_values($bindings),
        ];

        return $this;
    }

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'inner'
    ): self {
        $type = strtolower($type);

        if (!in_array($type, ['inner', 'left'], true)) {
            throw new InvalidArgumentException('Join type must be inner or left.');
        }

        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be asc or desc.');
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be zero or greater.');
        }

        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be zero or greater.');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->bindings());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $this->limit(1);

        return $this->connection->selectOne($this->toSql(), $this->bindings());
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $values
     */
    public function insert(array $values): bool
    {
        [$sql, $bindings] = $this->insertSqlAndBindings($values);

        return $this->connection->statement($sql, $bindings);
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $values
     */
    public function insertGetId(array $values): string
    {
        $this->insert($values);

        return $this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        $this->ensureTable();

        if ($values === []) {
            throw new QueryException('UPDATE ' . $this->table, [], new PDOException('Update data cannot be empty.'));
        }

        $columns = array_keys($values);
        $sets = array_map(static fn (string $column): string => $column . ' = ?', $columns);
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . $this->compileWhereClause();

        return $this->connection->affectingStatement($sql, [...array_values($values), ...$this->bindings()]);
    }

    public function delete(): int
    {
        $this->ensureTable();

        return $this->connection->affectingStatement(
            'DELETE FROM ' . $this->table . $this->compileWhereClause(),
            $this->bindings()
        );
    }

    public function toSql(): string
    {
        $this->ensureTable();

        $sql = 'SELECT ' . implode(', ', array_map(
            static fn (string|Expression $column): string => (string) $column,
            $this->columns
        )) . ' FROM ' . $this->table;

        $sql .= $this->compileJoinClause();

        $sql .= $this->compileWhereClause();

        if ($this->orders !== []) {
            $orders = array_map(
                static fn (array $order): string => $order['column'] . ' ' . strtoupper($order['direction']),
                $this->orders
            );

            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * @return array<int, mixed>
     */
    public function bindings(): array
    {
        $bindings = $this->selectBindings;

        foreach ($this->wheres as $where) {
            if ($where['type'] === 'raw') {
                array_push($bindings, ...$where['bindings']);
                continue;
            }

            if (array_key_exists('value', $where)) {
                $bindings[] = $where['value'];
            }
        }

        return $bindings;
    }

    private function ensureTable(): void
    {
        if ($this->table === null || $this->table === '') {
            throw new LogicException('Table must be set before building query.');
        }
    }

    private function compileWhereClause(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $prefix = $index === 0 ? '' : strtoupper($where['boolean']) . ' ';
            $clauses[] = $prefix . ($where['type'] === 'raw'
                ? $where['sql']
                : $where['column'] . ' ' . $where['operator'] . ' ?');
        }

        return ' WHERE ' . implode(' ', $clauses);
    }

    private function compileJoinClause(): string
    {
        if ($this->joins === []) {
            return '';
        }

        $joins = array_map(static function (array $join): string {
            $type = $join['type'] === 'left' ? 'LEFT JOIN' : 'INNER JOIN';

            return "{$type} {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }, $this->joins);

        return ' ' . implode(' ', $joins);
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $values
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function insertSqlAndBindings(array $values): array
    {
        $this->ensureTable();

        if ($values === []) {
            throw new QueryException('INSERT INTO ' . $this->table, [], new PDOException('Insert data cannot be empty.'));
        }

        $rows = array_is_list($values) ? $values : [$values];

        if ($rows === [] || !is_array($rows[0]) || $rows[0] === []) {
            throw new QueryException('INSERT INTO ' . $this->table, [], new PDOException('Insert data cannot be empty.'));
        }

        $columns = array_keys($rows[0]);
        $bindings = [];
        $placeholders = [];

        foreach ($rows as $row) {
            if (!is_array($row) || array_keys($row) !== $columns) {
                throw new QueryException(
                    'INSERT INTO ' . $this->table,
                    [],
                    new PDOException('Multiple insert rows must have consistent columns.')
                );
            }

            $placeholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            array_push($bindings, ...array_values($row));
        }

        return [
            'INSERT INTO ' . $this->table . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $placeholders),
            $bindings,
        ];
    }
}
