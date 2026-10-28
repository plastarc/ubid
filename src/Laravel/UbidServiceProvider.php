<?php

declare(strict_types=1);

namespace Plastarc\Ubid\Laravel;

use Illuminate\Support\ServiceProvider;
use Plastarc\Ubid\Laravel\Console\BackfillUbid;

final class UbidServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BackfillUbid::class]);
        }
    }
}
