<?php

declare(strict_types=1);

namespace Lukman\Database\Tests;

use InvalidArgumentException;
use LogicException;
use Lukman\Database\Connection;
use Lukman\Database\Exception\QueryException;
use Lukman\Database\Expression;
use Lukman\Database\QueryBuilder;
use PDO;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection(new PDO('sqlite::memory:'));
        $this->connection->statement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, active INTEGER NOT NULL)'
        );
        $this->connection->statement(
            'CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL, published INTEGER NOT NULL)'
        );
        $this->connection->statement(
            'INSERT INTO users (name, email, active) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?)',
            [
                'Alice',
                'alice@example.com',
                1,
                'Bob',
                'bob@example.com',
                0,
                'Charlie',
                'charlie@example.com',
                1,
            ]
        );
        $this->connection->statement(
            'INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?), (?, ?, ?)',
            [1, 'First', 1, 2, 'Draft', 0]
        );
    }

    public function testTableIsRequiredBeforeBuildingSql(): void
    {
        $this->expectException(LogicException::class);

        $this->builder()->toSql();
    }

    public function testDefaultSelectIsStarAndSqlIsStable(): void
    {
        $builder = $this->builder()
            ->table('users')
            ->where('active', 1)
            ->orWhere('email', 'bob@example.com')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->offset(5);

        $this->assertSame(
            'SELECT * FROM users WHERE active = ? OR email = ? ORDER BY id DESC LIMIT 10 OFFSET 5',
            $builder->toSql()
        );
        $this->assertSame(
            'SELECT * FROM users WHERE active = ? OR email = ? ORDER BY id DESC LIMIT 10 OFFSET 5',
            $builder->toSql()
        );
    }

    public function testSelectColumnsSqlAndBindingsOrder(): void
    {
        $builder = $this->builder()
            ->table('users')
            ->select('id', 'name')
            ->where('active', 1)
            ->where('email', 'like', '%example.com')
            ->orderBy('name');

        $this->assertSame('SELECT id, name FROM users WHERE active = ? AND email like ? ORDER BY name ASC', $builder->toSql());
        $this->assertSame([1, '%example.com'], $builder->bindings());
    }

    public function testInvalidOrderDirectionThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->table('users')->orderBy('id', 'sideways');
    }

    public function testNegativeLimitThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->table('users')->limit(-1);
    }

    public function testNegativeOffsetThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->table('users')->offset(-1);
    }

    public function testGetExecutesValidSqlInSqlite(): void
    {
        $rows = $this->builder()
            ->table('users')
            ->select('name')
            ->where('active', 1)
            ->orderBy('id')
            ->get();

        $this->assertSame([['name' => 'Alice'], ['name' => 'Charlie']], $rows);
    }

    public function testFirstLimitsResultToOne(): void
    {
        $row = $this->builder()
            ->table('users')
            ->select('name')
            ->where('active', 1)
            ->orderBy('id')
            ->first();

        $this->assertSame(['name' => 'Alice'], $row);
    }

    public function testInsertUsesBindings(): void
    {
        $result = $this->builder()
            ->table('users')
            ->insert([
                'name' => 'Dana',
                'email' => 'dana@example.com',
                'active' => 1,
            ]);

        $row = $this->builder()->table('users')->select('name')->where('email', 'dana@example.com')->first();

        $this->assertTrue($result);
        $this->assertSame(['name' => 'Dana'], $row);
    }

    public function testMultipleInsertWorks(): void
    {
        $this->builder()
            ->table('users')
            ->insert([
                ['name' => 'Dana', 'email' => 'dana@example.com', 'active' => 1],
                ['name' => 'Evan', 'email' => 'evan@example.com', 'active' => 0],
            ]);

        $rows = $this->builder()
            ->table('users')
            ->select('name')
            ->where('email', 'like', '%@example.com')
            ->orderBy('id')
            ->get();

        $this->assertSame(
            [['name' => 'Alice'], ['name' => 'Bob'], ['name' => 'Charlie'], ['name' => 'Dana'], ['name' => 'Evan']],
            $rows
        );
    }

    public function testMultipleInsertRejectsInconsistentColumns(): void
    {
        $this->expectException(QueryException::class);

        $this->builder()
            ->table('users')
            ->insert([
                ['name' => 'Dana', 'email' => 'dana@example.com', 'active' => 1],
                ['name' => 'Evan', 'active' => 0],
            ]);
    }

    public function testInsertGetIdReturnsLastInsertId(): void
    {
        $id = $this->builder()
            ->table('users')
            ->insertGetId([
                'name' => 'Dana',
                'email' => 'dana@example.com',
                'active' => 1,
            ]);

        $this->assertSame('4', $id);
    }

    public function testEmptyInsertThrowsQueryException(): void
    {
        $this->expectException(QueryException::class);

        $this->builder()->table('users')->insert([]);
    }

    public function testUpdateWithoutDataThrowsQueryException(): void
    {
        $this->expectException(QueryException::class);

        $this->builder()->table('users')->where('id', 1)->update([]);
    }

    public function testUpdateReturnsAffectedRowCountAndUsesWhereBindings(): void
    {
        $affected = $this->builder()
            ->table('users')
            ->where('active', 1)
            ->update(['name' => 'Updated']);

        $rows = $this->builder()->table('users')->select('name')->orderBy('id')->get();

        $this->assertSame(2, $affected);
        $this->assertSame([['name' => 'Updated'], ['name' => 'Bob'], ['name' => 'Updated']], $rows);
    }

    public function testDeleteReturnsAffectedRowCountAndUsesWhereBindings(): void
    {
        $affected = $this->builder()
            ->table('users')
            ->where('active', 0)
            ->delete();

        $rows = $this->builder()->table('users')->select('name')->orderBy('id')->get();

        $this->assertSame(1, $affected);
        $this->assertSame([['name' => 'Alice'], ['name' => 'Charlie']], $rows);
    }

    public function testRawExpressionDoesNotEnterBindingsAndCanBeStringified(): void
    {
        $expression = new Expression('COUNT(*) AS total');
        $builder = $this->builder()
            ->table('users')
            ->select($expression)
            ->where('active', 1);

        $this->assertSame('COUNT(*) AS total', (string) $expression);
        $this->assertSame('SELECT COUNT(*) AS total FROM users WHERE active = ?', $builder->toSql());
        $this->assertSame([1], $builder->bindings());
    }

    public function testSelectRawAndWhereRawSqlAndBindingsOrder(): void
    {
        $builder = $this->builder()
            ->table('users')
            ->selectRaw('COUNT(CASE WHEN active = ? THEN 1 END) AS active_total', [1])
            ->where('email', 'like', '%example.com')
            ->whereRaw('name <> ?', ['Bob']);

        $this->assertSame(
            'SELECT COUNT(CASE WHEN active = ? THEN 1 END) AS active_total FROM users WHERE email like ? AND name <> ?',
            $builder->toSql()
        );
        $this->assertSame([1, '%example.com', 'Bob'], $builder->bindings());
    }

    public function testJoinSqlAppearsBeforeWhereAndBindingsStayStable(): void
    {
        $builder = $this->builder()
            ->table('users')
            ->select('users.name', 'posts.title')
            ->join('posts', 'posts.user_id', '=', 'users.id')
            ->where('posts.published', 1)
            ->whereRaw('users.name = ?', ['Alice']);

        $this->assertSame(
            'SELECT users.name, posts.title FROM users INNER JOIN posts ON posts.user_id = users.id WHERE posts.published = ? AND users.name = ?',
            $builder->toSql()
        );
        $this->assertSame([1, 'Alice'], $builder->bindings());
        $this->assertSame([['name' => 'Alice', 'title' => 'First']], $builder->get());
    }

    public function testLeftJoinUsesLeftJoinSql(): void
    {
        $builder = $this->builder()
            ->table('users')
            ->select('users.name', 'posts.title')
            ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
            ->where('users.id', 3);

        $this->assertSame(
            'SELECT users.name, posts.title FROM users LEFT JOIN posts ON posts.user_id = users.id WHERE users.id = ?',
            $builder->toSql()
        );
        $this->assertSame([['name' => 'Charlie', 'title' => null]], $builder->get());
    }

    public function testInvalidJoinTypeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->table('users')->join('posts', 'posts.user_id', '=', 'users.id', 'right');
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }
}
