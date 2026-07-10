<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the application for testing.
     *
     * This machine exports real OS environment variables (DB_CONNECTION=pgsql,
     * QUEUE_CONNECTION=database, DB_DATABASE=abcweb, APP_ENV=local) which
     * override phpunit.xml's <env> block. Left unchecked, the suite would run
     * against — and RefreshDatabase would wipe — the live database. We therefore
     * pin the test connection/queue/cache in code so tests are always isolated.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // OS exports APP_ENV=local, which would otherwise leave the app thinking
        // it is not under test (enforcing CSRF, etc.). Pin it to testing.
        $app->instance('env', 'testing');

        $app->make('config')->set([
            'app.env' => 'testing',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'mail.default' => 'array',
        ]);

        // Purge any pgsql connection opened during bootstrap so the pinned
        // sqlite connection is used from here on.
        $app->make('db')->purge();

        return $app;
    }
}
