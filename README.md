# Lukman Database

Lightweight PHP database package built on PDO.

## Requirements
- PHP 8.2 or higher
- PDO Extension (`ext-pdo`)

## Installation
```bash
composer require lukman-ss/database
```

## Usage

```php
<?php

declare(strict_types=1);

use Lukman\Database\ConnectionFactory;
use Lukman\Database\QueryBuilder;
use Lukman\Database\Schema\Blueprint;
use Lukman\Database\Schema\SchemaBuilder;

$factory = new ConnectionFactory();
$connection = $factory->create([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

$schema = new SchemaBuilder($connection);
$schema->create('users', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->boolean('active')->default(true);
});

$users = new QueryBuilder($connection);
$id = $users->table('users')->insertGetId([
    'name' => 'Alice',
    'email' => 'alice@example.com',
    'active' => true,
]);

$row = (new QueryBuilder($connection))
    ->table('users')
    ->where('id', $id)
    ->first();
```

## Supported Features

- PDO connection factory for SQLite, MySQL, and PostgreSQL DSNs.
- Prepared statements with positional and named bindings.
- Lazy connection manager.
- Select, insert, update, and delete query builder.
- Raw expressions, raw where clauses, inner join, and left join.
- Transactions without savepoints.
- SQLite schema create/drop helpers.

## Error Handling

- Query execution errors throw `Lukman\Database\Exception\QueryException`.
- Connection/configuration errors throw `Lukman\Database\Exception\ConnectionException`.

## Tests

```bash
composer test
```
