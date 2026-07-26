<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests don't build frontend assets, so don't require the Vite manifest.
        $this->withoutVite();

        // Laravel 13 renamed the CSRF middleware to PreventRequestForgery
        // (ValidateCsrfToken now only extends it) and dropped the automatic
        // test-environment exemption, so POST/PUT/DELETE requests in tests get
        // a 419. Disable CSRF for the whole suite here — no test asserts on it.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    #[\Override]
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        // The container exports CACHE_STORE=database as a real env var (like
        // DB_*), overriding phpunit.xml. Force the array store so tests that use
        // the cache don't need the cache table migrated.
        $app['config']->set('cache.default', 'array');

        // The container also exports QUEUE_CONNECTION=database, which overrides
        // phpunit.xml's `sync`. Left as-is, tests would use the separate
        // sqlite_queue file connection, and a synchronously-dispatched job that
        // runs VACUUM INTO (the backup) would break the RefreshDatabase
        // transaction and drop the session (CSRF failures on the next request).
        // Force sync here so jobs run inline against the in-memory app DB.
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('database.connections.sqlite_queue.database', ':memory:');

        // The container exports SCALABLE_* as real env vars (like DB_*), which
        // override phpunit.xml. Force the broker sync off so tests never run the
        // CLI; tests that exercise it set the config explicitly.
        $app['config']->set('services.scalable.cash_category_id', 0);
        $app['config']->set('services.scalable.cli.enabled', false);

        // Inertia v3 enables SSR by default, so rendering a page in a test would
        // fire an HTTP call to the SSR server (localhost:5173/__inertia_ssr) —
        // which doesn't exist under test and, with Http::preventStrayRequests(),
        // 500s. Force SSR off here so it wins over both .env (CI copies
        // .env.example, which has no such key) and phpunit.xml.
        $app['config']->set('inertia.ssr.enabled', false);

        return $app;
    }
}
