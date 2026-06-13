<?php

declare(strict_types=1);

namespace Lukman\Database\Tests;

use Lukman\Database\Connection;
use Lukman\Database\ConnectionFactory;
use Lukman\Database\DatabaseManager;
use Lukman\Database\Exception\ConnectionException;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseManagerTest extends TestCase
{
    public function testAddConnectionDoesNotCreateConnectionImmediately(): void
    {
        $factory = new SpyConnectionFactory();
        $manager = new DatabaseManager($factory);

        $manager->addConnection('default', $this->sqliteConfig());

        $this->assertSame(0, $factory->created);
    }

    public function testConnectionUsesDefaultWhenNameIsNull(): void
    {
        $factory = new SpyConnectionFactory();
        $manager = new DatabaseManager($factory);
        $manager->addConnection('default', $this->sqliteConfig());

        $this->assertInstanceOf(Connection::class, $manager->connection(null));
        $this->assertSame(1, $factory->created);
    }

    public function testFirstConnectionBecomesDefaultAndCanBeChanged(): void
    {
        $manager = new DatabaseManager(new SpyConnectionFactory());
        $manager->addConnection('first', $this->sqliteConfig());
        $manager->addConnection('second', $this->sqliteConfig(), true);

        $this->assertSame('second', $manager->getDefaultConnection());
    }

    public function testConnectionIsCached(): void
    {
        $factory = new SpyConnectionFactory();
        $manager = new DatabaseManager($factory);
        $manager->addConnection('default', $this->sqliteConfig());

        $first = $manager->connection();
        $second = $manager->connection();

        $this->assertSame($first, $second);
        $this->assertSame(1, $factory->created);
    }

    public function testPurgeRemovesInstanceOnly(): void
    {
        $factory = new SpyConnectionFactory();
        $manager = new DatabaseManager($factory);
        $manager->addConnection('default', $this->sqliteConfig());

        $first = $manager->connection();
        $manager->purge();
        $second = $manager->connection();

        $this->assertNotSame($first, $second);
        $this->assertSame(2, $factory->created);
    }

    public function testDisconnectKeepsConfig(): void
    {
        $factory = new SpyConnectionFactory();
        $manager = new DatabaseManager($factory);
        $manager->addConnection('default', $this->sqliteConfig());

        $manager->connection();
        $manager->disconnect();
        $manager->connection();

        $this->assertSame(2, $factory->created);
    }

    public function testReconnectCreatesNewInstance(): void
    {
        $factory = new SpyConnectionFactory();
        $manager = new DatabaseManager($factory);
        $manager->addConnection('default', $this->sqliteConfig());

        $first = $manager->connection();
        $second = $manager->reconnect();

        $this->assertNotSame($first, $second);
        $this->assertSame(2, $factory->created);
    }

    public function testMissingConnectionThrowsConnectionException(): void
    {
        $this->expectException(ConnectionException::class);

        (new DatabaseManager())->connection('missing');
    }

    /**
     * @return array<string, string>
     */
    private function sqliteConfig(): array
    {
        return [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];
    }
}

final class SpyConnectionFactory extends ConnectionFactory
{
    public int $created = 0;

    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): Connection
    {
        $this->created++;

        return new Connection(new PDO('sqlite::memory:'));
    }
}
