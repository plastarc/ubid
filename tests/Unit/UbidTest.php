<?php

declare(strict_types=1);

use Plastarc\Ubid\CodeArea;
use Plastarc\Ubid\Exceptions\InvalidCode;
use Plastarc\Ubid\Ubid;
use Plastarc\Ubid\UbidArea;

// The cases below are PNNL's own (tests/buildingid/test_code.py and test_code_area.py in pnnl/buildingid-py).

test('encodes a point as the reference does', function () {
    expect(Ubid::encode(0, 0, 0, 0, 0, 0))->toBe('6FG22222+22-0-0-0-0')
        ->and(Ubid::fromPoint(0, 0))->toBe('6FG22222+22-0-0-0-0');
});

test('encodes a polygon as the reference does', function () {
    expect(Ubid::encode(0, 0, 10, 10, 5, 5))->toBe('6FQ72222+22-40000-40000-40000-40000')
        ->and(Ubid::fromBounds(0, 0, 10, 10))->toBe('6FQ72222+22-40000-40000-40000-40000');
});

test('a decoded area resized and re-encoded gives the same code', function () {
    $code = Ubid::fromPoint(0, 0);
    expect(Ubid::decode($code)->resize()->encode())->toBe($code);
});

test('validity', function () {
    expect(Ubid::isValid(null))->toBeFalse()
        ->and(Ubid::isValid(''))->toBeFalse()
        ->and(Ubid::isValid('6FG22222+22-0-0-0-0'))->toBeTrue()
        ->and(Ubid::isValid('6FQ72222+22-40000-40000-40000-40000'))->toBeTrue()
        ->and(Ubid::isValid('6FG22222+22-0-0-0'))->toBeFalse()
        ->and(Ubid::isValid('6FG22222+22-01-0-0-0'))->toBeFalse()
        ->and(Ubid::isValid('00000000+-0-0-0-0'))->toBeFalse()
        ->and(Ubid::isValid('22000000+-0-0-0-0'))->toBeTrue()
        ->and(Ubid::isValid('22000000+0-0-0-0-0'))->toBeFalse()
        ->and(Ubid::isValid('22222222+2-0-0-0-0'))->toBeFalse()
        ->and(Ubid::isValid('22222222+22-0-0-0-0'))->toBeTrue()
        ->and(Ubid::isValid('22222222+222-0-0-0-0'))->toBeTrue()
        ->and(Ubid::isValid('22222222+2202-0-0-0-0'))->toBeFalse()
        ->and(Ubid::isValid('6fg22222+22-0-0-0-0'))->toBeTrue();
});

test('decode rejects bad input', function () {
    expect(fn () => Ubid::decode(''))->toThrow(InvalidCode::class)
        ->and(fn () => Ubid::decode('not a code'))->toThrow(InvalidCode::class);
});

test('encode rejects a center outside the box and a bad length', function () {
    expect(fn () => Ubid::encode(0, 0, 1, 1, 2, 0.5))->toThrow(InvalidCode::class)
        ->and(fn () => Ubid::encode(0, 0, 1, 1, 0.5, 0.5, 3))->toThrow(InvalidCode::class);
});

test('decode returns the box and center cell, and jaccard scores overlap', function () {
    $area = Ubid::decode('6FQ72222+22-40000-40000-40000-40000');
    expect($area)->toBeInstanceOf(UbidArea::class)
        ->and($area->centroid->codeLength)->toBe(10)
        ->and($area->latitudeLo)->toEqualWithDelta(0.0, 1e-9)
        ->and($area->latitudeHi)->toEqualWithDelta(10.000125, 1e-9)
        ->and($area->code)->toBe('6FQ72222+22-40000-40000-40000-40000')
        ->and($area->resize()->encode())->toBe('6FQ72222+22-40000-40000-40000-40000')
        ->and(Ubid::jaccard('6FQ72222+22-40000-40000-40000-40000', '6FQ72222+22-40000-40000-40000-40000'))->toEqualWithDelta(1.0, 1e-12)
        ->and(Ubid::jaccard('6FG22222+22-0-0-0-0', '6FQ72222+22-40000-40000-40000-40000'))->toBeGreaterThan(0)
        ->and(Ubid::centerCode('6FQ72222+22-40000-40000-40000-40000'))->toBe('6FQ72222+22');
});

test('code area arithmetic matches the reference', function () {
    $centroid = new CodeArea(-1.0, -1.0, 1.0, 1.0, 10);
    $area = new UbidArea($centroid, -10.0, -10.0, 10.0, 10.0, 10);
    expect($area->area())->toBe(400.0);

    $other = new UbidArea($centroid, -5.0, -5.0, 15.0, 15.0, 10);
    expect($area->intersection($other))->toBe([-5.0, -5.0, 10.0, 10.0])
        ->and($area->jaccard($other))->toBe(225.0 / (400.0 + 400.0 - 225.0))
        ->and($area->jaccard(new UbidArea($centroid, -20.0, -20.0, -10.0, -10.0, 10)))->toBe(0.0)
        ->and($area->jaccard(new UbidArea($centroid, -30.0, -30.0, -20.0, -20.0, 10)))->toBeNull()
        ->and($area->jaccard(null))->toBeNull();

    $resized = $area->resize();
    expect($resized->centroid)->toBe($centroid)
        ->and([$resized->latitudeLo, $resized->longitudeLo, $resized->latitudeHi, $resized->longitudeHi])->toBe([-9.0, -9.0, 9.0, 9.0])
        ->and($resized->codeLength)->toBe(10);
});

test('a real building: 97 Commerce Way, Dover, Delaware', function () {
    $point = Ubid::fromPoint(39.154446, -75.5467515);
    expect($point)->toBe('87F65F33+Q7-0-0-0-0')
        ->and(Ubid::decode($point)->centroid->latLng()[0])->toEqualWithDelta(39.154446, 0.0001);
    $footprint = Ubid::fromBounds(39.154176, -75.547102, 39.154716, -75.546402);
    expect(Ubid::isValid($footprint))->toBeTrue()
        ->and(Ubid::centerCode($footprint))->toBe('87F65F33+Q7')
        ->and(Ubid::jaccard($point, $footprint))->toBeGreaterThan(0);
});
