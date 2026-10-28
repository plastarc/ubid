<?php

declare(strict_types=1);

namespace Plastarc\Ubid\Laravel;

use Plastarc\Ubid\Ubid;

/**
 * Keeps a model's UBID in step with its geometry. The model supplies its bounding box and center
 * through ubidBounds(); the trait recomputes the code on every save, so a building placed by hand,
 * re-geocoded or given a footprint carries the right code without a batch step.
 *
 * Implement on the model:
 *
 *     /** @return array{0: float, 1: float, 2: float, 3: float, 4?: float, 5?: float}|null *\/
 *     public function ubidBounds(): ?array   // [latLo, lngLo, latHi, lngHi, latCenter?, lngCenter?] or null when unplaced
 *
 * Override ubidColumn() if the column is not `ubid`.
 */
trait HasUbid
{
    public static function bootHasUbid(): void
    {
        static::saving(function ($model): void {
            $model->refreshUbid();
        });
    }

    public function ubidColumn(): string
    {
        return 'ubid';
    }

    /** Recompute the code from ubidBounds() and set it on the model (null when the model has no geometry). Returns true when it changed. */
    public function refreshUbid(): bool
    {
        $bounds = $this->ubidBounds();
        $code = $bounds === null ? null : Ubid::encode(
            (float) $bounds[0], (float) $bounds[1], (float) $bounds[2], (float) $bounds[3],
            (float) ($bounds[4] ?? ($bounds[0] + $bounds[2]) / 2), (float) ($bounds[5] ?? ($bounds[1] + $bounds[3]) / 2),
        );
        $column = $this->ubidColumn();
        if ($this->getAttribute($column) === $code) {
            return false;
        }
        $this->setAttribute($column, $code);

        return true;
    }
}
