<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->forceTestingDatabaseEnvironment();

        parent::setUp();

        $database = DB::connection()->getDatabaseName();

        if ($database !== 'testing') {
            $this->fail("Refusing to run tests against database [{$database}]; expected [testing].");
        }
    }

    /**
     * Collision's `artisan test --env=…` skips clearing parent .env vars into the PHPUnit
     * process. Without this guard, RefreshDatabase migrates/wipes the local app database.
     */
    private function forceTestingDatabaseEnvironment(): void
    {
        putenv('DB_CONNECTION=mysql');
        putenv('DB_DATABASE=testing');
        putenv('DB_URL');

        $_ENV['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_DATABASE'] = 'testing';
        $_SERVER['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_DATABASE'] = 'testing';

        unset($_ENV['DB_URL'], $_SERVER['DB_URL']);

        Env::enablePutenv();
    }
}
