<?php

declare(strict_types=1);

use Plastarc\Ubid\Exceptions\InvalidCode;
use Plastarc\Ubid\OpenLocationCode;

/**
 * Google's own test vectors (test_data/encoding.csv and decoding.csv in google/open-location-code),
 * copied unchanged into tests/fixtures. Every row must round-trip exactly.
 */
function olcRows(string $file): array
{
    $rows = [];
    foreach (file(__DIR__."/../fixtures/{$file}", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (! str_starts_with($line, '#')) {
            $rows[] = explode(',', $line);
        }
    }

    return $rows;
}

test('encodes every vector in Google\'s encoding.csv', function () {
    $checked = 0;
    foreach (olcRows('olc-encoding.csv') as [$lat, $lng, $latInt, $lngInt, $length, $expected]) {
        // As in Google's own test: floating point may land one grid unit low on the degree-to-integer step.
        [$a, $b] = OpenLocationCode::locationToIntegers((float) $lat, (float) $lng);
        expect($a)->toBeGreaterThanOrEqual((int) $latInt - 1, "lat int for {$lat},{$lng}")->toBeLessThanOrEqual((int) $latInt)
            ->and($b)->toBeGreaterThanOrEqual((int) $lngInt - 1, "lng int for {$lat},{$lng}")->toBeLessThanOrEqual((int) $lngInt);
        if ($expected === '') {
            expect(fn () => OpenLocationCode::encodeIntegers((int) $latInt, (int) $lngInt, (int) $length))->toThrow(InvalidCode::class);
        } else {
            expect(OpenLocationCode::encodeIntegers((int) $latInt, (int) $lngInt, (int) $length))->toBe($expected);
        }
        $checked++;
    }
    expect($checked)->toBeGreaterThan(300);
});

test('encoding from degrees matches the vectors in all but a few floating-point edge cases', function () {
    $rows = olcRows('olc-encoding.csv');
    $errors = 0;
    foreach ($rows as [$lat, $lng, $latInt, $lngInt, $length, $expected]) {
        if ($expected !== '' && OpenLocationCode::encode((float) $lat, (float) $lng, (int) $length) !== $expected) {
            $errors++;
        }
    }
    expect($errors / count($rows))->toBeLessThanOrEqual(0.05);
});

test('decodes every vector in Google\'s decoding.csv', function () {
    foreach (olcRows('olc-decoding.csv') as [$code, $length, $latLo, $lngLo, $latHi, $lngHi]) {
        $area = OpenLocationCode::decode($code);
        expect($area->codeLength)->toBe((int) $length, $code)
            ->and($area->latitudeLo)->toEqualWithDelta((float) $latLo, 1e-10, $code)
            ->and($area->longitudeLo)->toEqualWithDelta((float) $lngLo, 1e-10, $code)
            ->and($area->latitudeHi)->toEqualWithDelta((float) $latHi, 1e-10, $code)
            ->and($area->longitudeHi)->toEqualWithDelta((float) $lngHi, 1e-10, $code);
    }
});

test('validity follows the reference rules', function () {
    expect(OpenLocationCode::isValid('8FVC2222+22'))->toBeTrue()
        ->and(OpenLocationCode::isFull('8FVC2222+22'))->toBeTrue()
        ->and(OpenLocationCode::isShort('2222+22'))->toBeTrue()
        ->and(OpenLocationCode::isFull('2222+22'))->toBeFalse()
        ->and(OpenLocationCode::isValid('8FVC2222+2'))->toBeFalse()
        ->and(OpenLocationCode::isValid('8FVC0000+'))->toBeTrue()
        ->and(OpenLocationCode::isValid('8FVC0000+22'))->toBeFalse()
        ->and(OpenLocationCode::isValid('00000000+'))->toBeFalse()
        ->and(OpenLocationCode::isValid('8FVC2222+22+'))->toBeFalse()
        ->and(OpenLocationCode::isFull('ZZZZ2222+22'))->toBeFalse();
});

test('rejects a short or malformed code on decode', function () {
    expect(fn () => OpenLocationCode::decode('2222+22'))->toThrow(InvalidCode::class);
});
