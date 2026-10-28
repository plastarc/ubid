<?php

declare(strict_types=1);

namespace Plastarc\Ubid\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Plastarc\Ubid\Ubid;

final class ValidUbid implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Ubid::isValid($value)) {
            $fail('The :attribute is not a valid Unique Building Identifier.');
        }
    }
}
