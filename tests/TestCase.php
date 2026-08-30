<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * A seatbelt against running the suite on a real database.
     *
     * why this exists: inside Docker, docker-compose injects .env through
     * `env_file`, so APP_ENV and DB_DATABASE are REAL process environment
     * variables. Laravel's Env reader consults $_SERVER first, and PHPUnit's
     * <env force="true"> only writes $_ENV - so the container's value wins and
     * phpunit.xml is silently ignored.
     *
     * The consequence is nasty: RefreshDatabase would wipe the development
     * database. `make test` passes the right variables explicitly; this check
     * makes sure nobody can skip that by accident.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with((string) $database, '_test') && $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against the [{$database}] database. ".
                'Run the suite with `make test`, which sets APP_ENV=testing and '.
                'DB_DATABASE=blog_test inside the container.',
            );
        }
    }
}
