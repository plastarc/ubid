<?php

declare(strict_types=1);

namespace Plastarc\Ubid\Laravel\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Plastarc\Ubid\Ubid;
use Plastarc\Ubid\UbidArea;

/**
 * Reads a stored UBID as a UbidArea (its box and center cell) and writes a UbidArea or a code string back.
 *
 * @implements CastsAttributes<UbidArea|null, string|null>
 */
final class AsUbidArea implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?UbidArea
    {
        return is_string($value) && $value !== '' ? Ubid::decode($value) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof UbidArea ? $value->code() : $value;
    }
}
