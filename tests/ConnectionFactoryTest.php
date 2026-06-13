<?php

declare(strict_types=1);

namespace Lukman\Database\Tests;

use Lukman\Database\Connection;
use Lukman\Database\ConnectionFactory;
use Lukman\Database\Exception\ConnectionException;
use PDO;
use PHPUnit\Framework\TestCase;

class ConnectionFactoryTest extends TestCase
{
    private ConnectionFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ConnectionFactory();
    }

    public function testCreatesSqliteMemoryConnection(): void
    {
        $connection = $this->factory->create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertTrue($connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)'));
    }

    public function testBuildsSqliteDsn(): void
    {
        $this->assertSame('sqlite:/tmp/database.sqlite', $this->factory->makeDsn([
            'driver' => 'sqlite',
            'database' => '/tmp/database.sqlite',
        ]));
    }

    public function testBuildsMysqlDsnWithoutConnecting(): void
    {
        $dsn = $this->factory->makeDsn([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'app',
            'port' => 3307,
            'charset' => 'utf8mb4',
        ]);

        $this->assertSame('mysql:host=127.0.0.1;dbname=app;port=3307;charset=utf8mb4', $dsn);
    }

    public function testBuildsPgsqlDsnWithoutConnecting(): void
    {
        $dsn = $this->factory->makeDsn([
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'database' => 'app',
            'port' => 5433,
        ]);

        $this->assertSame('pgsql:host=127.0.0.1;dbname=app;port=5433', $dsn);
    }

    public function testMissingDriverThrowsConnectionException(): void
    {
        $this->expectException(ConnectionException::class);

        $this->factory->create([]);
    }

    public function testUnsupportedDriverThrowsConnectionException(): void
    {
        $this->expectException(ConnectionException::class);

        $this->factory->create(['driver' => 'sqlsrv']);
    }

    public function testMissingRequiredConfigThrowsConnectionException(): void
    {
        $this->expectException(ConnectionException::class);

        $this->factory->makeDsn(['driver' => 'mysql', 'host' => '127.0.0.1']);
    }

    public function testUserOptionsOverrideDefaultOptions(): void
    {
        $connection = $this->factory->create([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'options' => [
                PDO::ATTR_CASE => PDO::CASE_UPPER,
            ],
        ]);

        $row = $connection->selectOne('SELECT 1 AS value');

        $this->assertSame(['VALUE' => 1], $row);
    }
}
