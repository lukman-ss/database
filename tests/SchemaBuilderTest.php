<?php

declare(strict_types=1);

namespace Lukman\Database\Tests;

use Lukman\Database\Connection;
use Lukman\Database\QueryBuilder;
use Lukman\Database\Schema\Blueprint;
use Lukman\Database\Schema\SchemaBuilder;
use PDO;
use PHPUnit\Framework\TestCase;

class SchemaBuilderTest extends TestCase
{
    private Connection $connection;

    private SchemaBuilder $schema;

    protected function setUp(): void
    {
        $this->connection = new Connection(new PDO('sqlite::memory:'));
        $this->schema = new SchemaBuilder($this->connection);
    }

    public function testCreateMakesTableAndHasTableIsAccurate(): void
    {
        $this->assertFalse($this->schema->hasTable('users'));

        $this->assertTrue($this->schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        }));

        $this->assertTrue($this->schema->hasTable('users'));
    }

    public function testDropRemovesTable(): void
    {
        $this->schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        $this->assertTrue($this->schema->drop('users'));
        $this->assertFalse($this->schema->hasTable('users'));
    }

    public function testDropIfExistsDoesNotErrorWhenTableMissing(): void
    {
        $this->assertTrue($this->schema->dropIfExists('missing_table'));
    }

    public function testToSqlUsesValidSqliteColumnDefinitions(): void
    {
        $blueprint = new Blueprint();
        $blueprint->id();
        $blueprint->string('email')->primary();
        $blueprint->integer('age')->nullable();
        $blueprint->boolean('active')->default(true);

        $this->assertSame(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT PRIMARY KEY NOT NULL, age INTEGER, active INTEGER NOT NULL DEFAULT 1)",
            $this->schema->toSql('users', $blueprint)
        );
    }

    public function testNullableAndDefaultApplyToLastColumnAndInsertWorks(): void
    {
        $this->schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('age')->nullable();
            $table->boolean('active')->default(true);
        });

        (new QueryBuilder($this->connection))
            ->table('users')
            ->insert([
                'name' => 'Alice',
                'age' => null,
            ]);

        $row = $this->connection->selectOne('SELECT name, age, active FROM users');

        $this->assertSame(['name' => 'Alice', 'age' => null, 'active' => 1], $row);
    }
}
