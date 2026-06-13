<?php

declare(strict_types=1);

namespace Lukman\Database\Tests;

use Lukman\Database\Connection;
use Lukman\Database\ConnectionFactory;
use Lukman\Database\DatabaseManager;
use Lukman\Database\Expression;
use Lukman\Database\QueryBuilder;
use Lukman\Database\Schema\Blueprint;
use Lukman\Database\Schema\SchemaBuilder;
use Lukman\Database\Exception\ConnectionException;
use Lukman\Database\Exception\QueryException;
use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase
{
    public function testClassesCanBeAutoloaded(): void
    {
        $this->assertTrue(class_exists(Connection::class));
        $this->assertTrue(class_exists(ConnectionFactory::class));
        $this->assertTrue(class_exists(DatabaseManager::class));
        $this->assertTrue(class_exists(Expression::class));
        $this->assertTrue(class_exists(QueryBuilder::class));
        $this->assertTrue(class_exists(Blueprint::class));
        $this->assertTrue(class_exists(SchemaBuilder::class));
        $this->assertTrue(class_exists(ConnectionException::class));
        $this->assertTrue(class_exists(QueryException::class));
    }
}
