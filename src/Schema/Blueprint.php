<?php

declare(strict_types=1);

namespace Lukman\Database\Schema;

use InvalidArgumentException;

final class Blueprint
{
    /**
     * @var array<int, array{name: string, type: string, nullable: bool, default: mixed, hasDefault: bool, primary: bool}>
     */
    private array $columns = [];

    private ?int $lastColumn = null;

    public function id(string $name = 'id'): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'nullable' => false,
            'default' => null,
            'hasDefault' => false,
            'primary' => false,
        ];
        $this->lastColumn = array_key_last($this->columns);

        return $this;
    }

    public function string(string $name): self
    {
        return $this->column($name, 'TEXT');
    }

    public function integer(string $name): self
    {
        return $this->column($name, 'INTEGER');
    }

    public function boolean(string $name): self
    {
        return $this->column($name, 'INTEGER');
    }

    public function text(string $name): self
    {
        return $this->column($name, 'TEXT');
    }

    public function nullable(): self
    {
        $this->currentColumn()['nullable'] = true;

        return $this;
    }

    public function default(mixed $value): self
    {
        $column = &$this->currentColumn();
        $column['default'] = $value;
        $column['hasDefault'] = true;

        return $this;
    }

    public function primary(): self
    {
        $this->currentColumn()['primary'] = true;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return array_map(fn (array $column): string => $this->compileColumn($column), $this->columns);
    }

    private function column(string $name, string $type): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => $type,
            'nullable' => false,
            'default' => null,
            'hasDefault' => false,
            'primary' => false,
        ];
        $this->lastColumn = array_key_last($this->columns);

        return $this;
    }

    /**
     * @return array{name: string, type: string, nullable: bool, default: mixed, hasDefault: bool, primary: bool}
     */
    private function &currentColumn(): array
    {
        if ($this->lastColumn === null) {
            throw new InvalidArgumentException('No column has been defined.');
        }

        return $this->columns[$this->lastColumn];
    }

    /**
     * @param array{name: string, type: string, nullable: bool, default: mixed, hasDefault: bool, primary: bool} $column
     */
    private function compileColumn(array $column): string
    {
        $sql = $column['name'] . ' ' . $column['type'];

        if ($column['primary'] && !str_contains($column['type'], 'PRIMARY KEY')) {
            $sql .= ' PRIMARY KEY';
        }

        if (!$column['nullable'] && !str_contains($column['type'], 'PRIMARY KEY')) {
            $sql .= ' NOT NULL';
        }

        if ($column['hasDefault']) {
            $sql .= ' DEFAULT ' . $this->formatDefault($column['default']);
        }

        return $sql;
    }

    private function formatDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
