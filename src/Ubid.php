<?php

declare(strict_types=1);

namespace Plastarc\Ubid;

use Plastarc\Ubid\Exceptions\InvalidCode;

/**
 * Unique Building Identifier (UBID), ported from PNNL's reference implementation
 * (https://github.com/pnnl/buildingid-py, Battelle Memorial Institute, 2019).
 *
 * A UBID is the Open Location Code of a building's center cell followed by how many cells of that size
 * the building's bounding box extends north, east, south and west of it: "87F65F33+Q7-3-4-2-2".
 * A point with no footprint encodes as "…-0-0-0-0". The pattern and the arithmetic follow the reference
 * exactly, so a code made here matches one made with the Python, JavaScript or PostgreSQL libraries.
 */
final class Ubid
{
    public const SEPARATOR = '-';

    private const PATTERN = '/^([23456789C][23456789CFGHJMPQRV](?:0{6}\+(?:0{2,})?|[23456789CFGHJMPQRVWX]{2}(?:0{4}\+(?:0{2,})?|[23456789CFGHJMPQRVWX]{2}(?:0{2}\+(?:0{2,})?|[23456789CFGHJMPQRVWX]{2}\+(?:0{2,}|[23456789CFGHJMPQRVWX]{2,}0*)?))))-(0|[1-9][0-9]*)-(0|[1-9][0-9]*)-(0|[1-9][0-9]*)-(0|[1-9][0-9]*)$/i';

    /**
     * Encode a bounding box and its center as a UBID. Coordinates are decimal degrees; the center must lie
     * inside the box. The default code length (10) gives the 13.5 m cells the standard uses.
     */
    public static function encode(float $latitudeLo, float $longitudeLo, float $latitudeHi, float $longitudeHi, float $latitudeCenter, float $longitudeCenter, int $codeLength = OpenLocationCode::PAIR_CODE_LENGTH): string
    {
        if (! self::isValidCodeLength($codeLength)) {
            throw new InvalidCode('Invalid code length');
        }
        if (! self::isValidLatitudeCenter($latitudeLo, $latitudeHi, $latitudeCenter)) {
            throw new InvalidCode('Invalid latitude coordinates');
        }
        if (! self::isValidLongitudeCenter($longitudeLo, $longitudeHi, $longitudeCenter)) {
            throw new InvalidCode('Invalid longitude coordinates');
        }

        $northeast = OpenLocationCode::decode(OpenLocationCode::encode($latitudeHi, $longitudeHi, $codeLength));
        $southwest = OpenLocationCode::decode(OpenLocationCode::encode($latitudeLo, $longitudeLo, $codeLength));
        $centerCode = OpenLocationCode::encode($latitudeCenter, $longitudeCenter, $codeLength);
        $center = OpenLocationCode::decode($centerCode);

        $height = $center->height();
        $width = $center->width();
        if ($height <= 0 || $width <= 0) {
            throw new InvalidCode('Invalid code area (center)');
        }

        $north = ($northeast->latitudeHi - $center->latitudeHi) / $height;
        $south = ($center->latitudeLo - $southwest->latitudeLo) / $height;
        $east = ($northeast->longitudeHi - $center->longitudeHi) / $width;
        $west = ($center->longitudeLo - $southwest->longitudeLo) / $width;
        if (min($north, $south, $east, $west) < 0) {
            throw new InvalidCode('Negative extent');
        }

        return sprintf('%s-%.0f-%.0f-%.0f-%.0f', $centerCode, $north, $east, $south, $west);
    }

    /** Encode a single point (no footprint): the center cell with zero extents. */
    public static function fromPoint(float $latitude, float $longitude, int $codeLength = OpenLocationCode::PAIR_CODE_LENGTH): string
    {
        return self::encode($latitude, $longitude, $latitude, $longitude, $latitude, $longitude, $codeLength);
    }

    /**
     * Encode a bounding box, taking its geometric center as the centroid. Pass the real centroid to
     * encode() when you have it: the two can land in different cells for an irregular footprint.
     */
    public static function fromBounds(float $latitudeLo, float $longitudeLo, float $latitudeHi, float $longitudeHi, int $codeLength = OpenLocationCode::PAIR_CODE_LENGTH): string
    {
        return self::encode($latitudeLo, $longitudeLo, $latitudeHi, $longitudeHi, ($latitudeLo + $latitudeHi) / 2, ($longitudeLo + $longitudeHi) / 2, $codeLength);
    }

    /** Decode a UBID into the box it stands for and the cell at its center. */
    public static function decode(string $code): UbidArea
    {
        $match = self::match($code);
        if ($match === null) {
            throw new InvalidCode('Invalid code');
        }
        $center = OpenLocationCode::decode($match[1]);
        $height = $center->height();
        $width = $center->width();
        if ($height < 0 || $width < 0) {
            throw new InvalidCode('Invalid code area');
        }
        $latitudeLo = $center->latitudeLo - ((int) $match[4]) * $height;
        $latitudeHi = $center->latitudeHi + ((int) $match[2]) * $height;
        $longitudeLo = $center->longitudeLo - ((int) $match[5]) * $width;
        $longitudeHi = $center->longitudeHi + ((int) $match[3]) * $width;
        if (! self::isValidLatitude($latitudeLo, $latitudeHi) || ! self::isValidLongitude($longitudeLo, $longitudeHi)) {
            throw new InvalidCode('Invalid code - coordinates out of range');
        }

        return new UbidArea($center, $latitudeLo, $longitudeLo, $latitudeHi, $longitudeHi, $center->codeLength, $code);
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && self::match($code) !== null;
    }

    /** The Open Location Code part of a UBID (its center cell), or null when the code is invalid. */
    public static function centerCode(string $code): ?string
    {
        return self::match($code)[1] ?? null;
    }

    /** How alike two UBIDs are: the Jaccard index of their boxes, or null when they do not intersect. */
    public static function jaccard(string $a, string $b): ?float
    {
        return self::decode($a)->jaccard(self::decode($b));
    }

    /** @return array<int, string>|null */
    private static function match(string $code): ?array
    {
        if (! preg_match(self::PATTERN, $code, $m) || ! OpenLocationCode::isValid($m[1])) {
            return null;
        }

        return $m;
    }

    public static function isValidCodeLength(int $codeLength): bool
    {
        return $codeLength >= 2 && ($codeLength >= OpenLocationCode::PAIR_CODE_LENGTH || $codeLength % 2 === 0);
    }

    public static function isValidLatitude(float $lo, float $hi): bool
    {
        return -OpenLocationCode::LATITUDE_MAX <= $lo && $lo <= $hi && $hi <= OpenLocationCode::LATITUDE_MAX;
    }

    public static function isValidLatitudeCenter(float $lo, float $hi, float $center): bool
    {
        return -OpenLocationCode::LATITUDE_MAX <= $lo && $lo <= $center && $center <= $hi && $hi <= OpenLocationCode::LATITUDE_MAX;
    }

    public static function isValidLongitude(float $lo, float $hi): bool
    {
        return -OpenLocationCode::LONGITUDE_MAX <= $lo && $lo <= $hi && $hi <= OpenLocationCode::LONGITUDE_MAX;
    }

    public static function isValidLongitudeCenter(float $lo, float $hi, float $center): bool
    {
        return -OpenLocationCode::LONGITUDE_MAX <= $lo && $lo <= $center && $center <= $hi && $hi <= OpenLocationCode::LONGITUDE_MAX;
    }
}
