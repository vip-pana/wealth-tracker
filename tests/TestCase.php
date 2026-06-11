<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests don't build frontend assets, so don't require the Vite manifest.
        $this->withoutVite();
    }

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        // The container exports CACHE_STORE=database as a real env var (like
        // DB_*), overriding phpunit.xml. Force the array store so tests that use
        // the cache don't need the cache table migrated.
        $app['config']->set('cache.default', 'array');

        // The container exports SCALABLE_* as real env vars (like DB_*), which
        // override phpunit.xml. Force the broker sync off so tests never reach
        // the host proxy or run the CLI; tests that exercise either set the
        // config explicitly.
        $app['config']->set('services.scalable.balance_url', '');
        $app['config']->set('services.scalable.token', '');
        $app['config']->set('services.scalable.cash_category_id', 0);
        $app['config']->set('services.scalable.source', 'auto');
        $app['config']->set('services.scalable.cli.enabled', false);

        return $app;
    }
}
