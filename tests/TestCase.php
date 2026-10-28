<?php

declare(strict_types=1);

namespace Plastarc\Ubid\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Plastarc\Ubid\Laravel\UbidServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [UbidServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }
}
