<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $database = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE');
        if ($database !== 'songguo_next_test') {
            throw new \RuntimeException('Tests may only run against songguo_next_test.');
        }

        parent::setUp();

        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'songguo_next_test') {
            throw new \RuntimeException('Resolved test database is not songguo_next_test.');
        }
    }
}
