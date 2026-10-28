<?php

declare(strict_types=1);

namespace Plastarc\Ubid\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class BackfillUbid extends Command
{
    protected $signature = 'ubid:backfill {model : The model class using HasUbid, e.g. "App\\Models\\Building"} {--chunk=500} {--dry-run : Count what would change without saving}';

    protected $description = 'Compute or refresh the UBID on every row of a model that uses HasUbid';

    public function handle(): int
    {
        $class = ltrim(str_replace('/', '\\', $this->argument('model')), '\\');
        if (! class_exists($class) || ! is_subclass_of($class, Model::class) || ! method_exists($class, 'refreshUbid')) {
            $this->error("{$class} is not an Eloquent model using Plastarc\\Ubid\\Laravel\\HasUbid.");

            return self::FAILURE;
        }
        $changed = 0;
        $seen = 0;
        $dry = (bool) $this->option('dry-run');
        $class::query()->chunkById((int) $this->option('chunk'), function ($models) use (&$changed, &$seen, $dry): void {
            foreach ($models as $model) {
                $seen++;
                if ($model->refreshUbid()) {
                    $changed++;
                    if (! $dry) {
                        $model->saveQuietly();
                    }
                }
            }
        });
        $this->info(sprintf('%d rows seen, %d %s.', $seen, $changed, $dry ? 'would change' : 'updated'));

        return self::SUCCESS;
    }
}
