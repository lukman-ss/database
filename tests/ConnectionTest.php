<?php

declare(strict_types=1);

namespace Lukman\Database\Tests;

use Lukman\Database\Connection;
use Lukman\Database\Exception\QueryException;
use PDO;
use RuntimeException;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection(new PDO('sqlite::memory:'));
        $this->connection->statement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL)'
        );
    }

    public function testStatementReturnsBoolean(): void
    {
        $result = $this->connection->statement(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Alice', 'alice@example.com']
        );

        $this->assertTrue($result);
    }

    public function testSelectUsesPositionalBindingsAndReturnsAssociativeRows(): void
    {
        $this->connection->statement(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Alice', 'alice@example.com']
        );

        $rows = $this->connection->select('SELECT name, email FROM users WHERE email = ?', ['alice@example.com']);

        $this->assertSame([['name' => 'Alice', 'email' => 'alice@example.com']], $rows);
    }

    public function testSelectUsesNamedBindings(): void
    {
        $this->connection->statement(
            'INSERT INTO users (name, email) VALUES (:name, :email)',
            ['name' => 'Bob', 'email' => 'bob@example.com']
        );

        $row = $this->connection->selectOne(
            'SELECT name, email FROM users WHERE email = :email',
            ['email' => 'bob@example.com']
        );

        $this->assertSame(['name' => 'Bob', 'email' => 'bob@example.com'], $row);
    }

    public function testSelectOneReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->connection->selectOne('SELECT name FROM users WHERE email = ?', ['missing@example.com']));
    }

    public function testAffectingStatementReturnsRowCount(): void
    {
        $this->connection->statement(
            'INSERT INTO users (name, email) VALUES (?, ?), (?, ?)',
            ['Alice', 'alice@example.com', 'Bob', 'bob@example.com']
        );

        $affected = $this->connection->affectingStatement(
            'UPDATE users SET name = ? WHERE email IN (?, ?)',
            ['Updated', 'alice@example.com', 'bob@example.com']
        );

        $this->assertSame(2, $affected);
    }

    public function testPdoErrorsAreWrappedInQueryException(): void
    {
        $this->expectException(QueryException::class);

        $this->connection->select('SELECT * FROM missing_table WHERE id = ?', [1]);
    }

    public function testBeginTransactionAndCommitPersistData(): void
    {
        $this->assertTrue($this->connection->beginTransaction());
        $this->assertTrue($this->connection->inTransaction());

        $this->connection->statement(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Alice', 'alice@example.com']
        );

        $this->assertTrue($this->connection->commit());
        $this->assertFalse($this->connection->inTransaction());
        $this->assertSame(['name' => 'Alice'], $this->connection->selectOne('SELECT name FROM users WHERE email = ?', ['alice@example.com']));
    }

    public function testRollBackRemovesInsertedData(): void
    {
        $this->connection->beginTransaction();
        $this->connection->statement(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Alice', 'alice@example.com']
        );

        $this->assertTrue($this->connection->rollBack());
        $this->assertFalse($this->connection->inTransaction());
        $this->assertNull($this->connection->selectOne('SELECT name FROM users WHERE email = ?', ['alice@example.com']));
    }

    public function testTransactionCommitsWhenCallbackSucceedsAndReceivesConnection(): void
    {
        $result = $this->connection->transaction(function (Connection $connection): string {
            $connection->statement(
                'INSERT INTO users (name, email) VALUES (?, ?)',
                ['Alice', 'alice@example.com']
            );

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertFalse($this->connection->inTransaction());
        $this->assertSame(['name' => 'Alice'], $this->connection->selectOne('SELECT name FROM users WHERE email = ?', ['alice@example.com']));
    }

    public function testTransactionRollsBackAndRethrowsWhenCallbackThrows(): void
    {
        try {
            $this->connection->transaction(function (Connection $connection): void {
                $connection->statement(
                    'INSERT INTO users (name, email) VALUES (?, ?)',
                    ['Alice', 'alice@example.com']
                );

                throw new RuntimeException('fail');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('fail', $exception->getMessage());
        }

        $this->assertFalse($this->connection->inTransaction());
        $this->assertNull($this->connection->selectOne('SELECT name FROM users WHERE email = ?', ['alice@example.com']));
    }

    public function testNestedTransactionDoesNotStartPdoTransactionAgain(): void
    {
        $this->connection->transaction(function (Connection $connection): void {
            $connection->statement(
                'INSERT INTO users (name, email) VALUES (?, ?)',
                ['Alice', 'alice@example.com']
            );

            $connection->transaction(function (Connection $nested): void {
                $nested->statement(
                    'INSERT INTO users (name, email) VALUES (?, ?)',
                    ['Bob', 'bob@example.com']
                );
            });

            $this->assertTrue($connection->inTransaction());
        });

        $this->assertFalse($this->connection->inTransaction());
        $this->assertSame(
            [['name' => 'Alice'], ['name' => 'Bob']],
            $this->connection->select('SELECT name FROM users ORDER BY id')
        );
    }
}
