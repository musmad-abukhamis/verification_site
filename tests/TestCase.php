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
            // inertia.ssr.enabled defaults to TRUE and this project publishes no
            // config/inertia.php, so once `npm run build` has produced
            // bootstrap/ssr/ssr.js the gateway POSTs every rendered page to the
            // SSR server. Under Http::fake() that POST is answered by whatever
            // catch-all stub the test set up, and the gateway then dies on the
            // missing 'head' key -- turning any page assertion into a 500 that
            // has nothing to do with the code under test.
            'inertia.ssr.enabled' => false,
        ]);

        // Purge any pgsql connection opened during bootstrap so the pinned
        // sqlite connection is used from here on.
        $app->make('db')->purge();

        return $app;
    }
}
