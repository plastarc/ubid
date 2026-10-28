<?php

declare(strict_types=1);

namespace Plastarc\Ubid;

use Plastarc\Ubid\Exceptions\InvalidCode;

/**
 * Open Location Code ("plus codes"), ported from Google's reference implementation
 * (https://github.com/google/open-location-code, Apache 2.0) and checked against its test vectors.
 * Only what a UBID needs: validity, encoding and decoding of full codes.
 */
final class OpenLocationCode
{
    public const SEPARATOR = '+';

    public const SEPARATOR_POSITION = 8;

    public const PADDING_CHARACTER = '0';

    public const CODE_ALPHABET = '23456789CFGHJMPQRVWX';

    public const ENCODING_BASE = 20;

    public const LATITUDE_MAX = 90;

    public const LONGITUDE_MAX = 180;

    public const MIN_DIGIT_COUNT = 2;

    public const MAX_DIGIT_COUNT = 15;

    public const PAIR_CODE_LENGTH = 10;

    /** 20 ** (PAIR_CODE_LENGTH / 2 - 1) */
    private const PAIR_FIRST_PLACE_VALUE = 160000;

    /** 20 ** 3 */
    private const PAIR_PRECISION = 8000;

    private const GRID_CODE_LENGTH = self::MAX_DIGIT_COUNT - self::PAIR_CODE_LENGTH;

    private const GRID_COLUMNS = 4;

    private const GRID_ROWS = 5;

    /** GRID_ROWS ** (GRID_CODE_LENGTH - 1) */
    private const GRID_LAT_FIRST_PLACE_VALUE = 625;

    /** GRID_COLUMNS ** (GRID_CODE_LENGTH - 1) */
    private const GRID_LNG_FIRST_PLACE_VALUE = 256;

    /** PAIR_PRECISION * GRID_ROWS ** GRID_CODE_LENGTH */
    private const FINAL_LAT_PRECISION = 25000000;

    /** PAIR_PRECISION * GRID_COLUMNS ** GRID_CODE_LENGTH */
    private const FINAL_LNG_PRECISION = 8192000;

    /**
     * A valid code uses only the alphabet, has one separator at an even position no later than the
     * eighth digit, and pads (if at all) in an even run that ends at the separator.
     */
    public static function isValid(string $code): bool
    {
        if (substr_count($code, self::SEPARATOR) !== 1 || strlen($code) === 1) {
            return false;
        }
        $sep = strpos($code, self::SEPARATOR);
        if ($sep > self::SEPARATOR_POSITION || $sep % 2 === 1) {
            return false;
        }
        $pad = strpos($code, self::PADDING_CHARACTER);
        if ($pad !== false) {
            if ($sep < self::SEPARATOR_POSITION || $pad === 0) {
                return false;
            }
            $pads = substr($code, $pad, strrpos($code, self::PADDING_CHARACTER) + 1 - $pad);
            if (strlen($pads) % 2 === 1 || substr_count($pads, self::PADDING_CHARACTER) !== strlen($pads)) {
                return false;
            }
            if (! str_ends_with($code, self::SEPARATOR)) {
                return false;
            }
        }
        if (strlen($code) - $sep - 1 === 1) {
            return false;
        }
        foreach (str_split($code) as $ch) {
            if (! str_contains(self::CODE_ALPHABET, strtoupper($ch)) && $ch !== self::SEPARATOR && $ch !== self::PADDING_CHARACTER) {
                return false;
            }
        }

        return true;
    }

    /** A short code has had four or more leading digits removed; it needs a reference location to decode. */
    public static function isShort(string $code): bool
    {
        if (! self::isValid($code)) {
            return false;
        }
        $sep = strpos($code, self::SEPARATOR);

        return $sep !== false && $sep < self::SEPARATOR_POSITION;
    }

    /** A full code is valid, not short, and its first pair lies inside the latitude and longitude ranges. */
    public static function isFull(string $code): bool
    {
        if (! self::isValid($code) || self::isShort($code)) {
            return false;
        }
        $firstLatValue = strpos(self::CODE_ALPHABET, strtoupper($code[0])) * self::ENCODING_BASE;
        if ($firstLatValue >= self::LATITUDE_MAX * 2) {
            return false;
        }
        if (strlen($code) > 1) {
            $firstLngValue = strpos(self::CODE_ALPHABET, strtoupper($code[1])) * self::ENCODING_BASE;
            if ($firstLngValue >= self::LONGITUDE_MAX * 2) {
                return false;
            }
        }

        return true;
    }

    /**
     * Encode a location as a full code of the given length. Ten digits (the default) is a cell of about
     * 13.5 by 13.5 meters at the equator; latitude is clipped to ±90 and longitude wrapped into ±180.
     */
    public static function encode(float $latitude, float $longitude, int $codeLength = self::PAIR_CODE_LENGTH): string
    {
        [$latVal, $lngVal] = self::locationToIntegers($latitude, $longitude);

        return self::encodeIntegers($latVal, $lngVal, $codeLength);
    }

    /** @return array{0: int, 1: int} the location as integers in the final-precision grid; exposed for tests. */
    public static function locationToIntegers(float $latitude, float $longitude): array
    {
        $latVal = (int) floor($latitude * self::FINAL_LAT_PRECISION);
        $latVal += self::LATITUDE_MAX * self::FINAL_LAT_PRECISION;
        if ($latVal < 0) {
            $latVal = 0;
        } elseif ($latVal >= 2 * self::LATITUDE_MAX * self::FINAL_LAT_PRECISION) {
            $latVal = 2 * self::LATITUDE_MAX * self::FINAL_LAT_PRECISION - 1;
        }
        $lngVal = (int) floor($longitude * self::FINAL_LNG_PRECISION);
        $lngVal += self::LONGITUDE_MAX * self::FINAL_LNG_PRECISION;
        $wrap = 2 * self::LONGITUDE_MAX * self::FINAL_LNG_PRECISION;
        if ($lngVal < 0 || $lngVal >= $wrap) {
            $lngVal = (($lngVal % $wrap) + $wrap) % $wrap;
        }

        return [$latVal, $lngVal];
    }

    /** Encode the integer form; exposed for tests. */
    public static function encodeIntegers(int $latVal, int $lngVal, int $codeLength): string
    {
        if ($codeLength < self::MIN_DIGIT_COUNT || ($codeLength < self::PAIR_CODE_LENGTH && $codeLength % 2 === 1)) {
            throw new InvalidCode('Invalid Open Location Code length - '.$codeLength);
        }
        $codeLength = min($codeLength, self::MAX_DIGIT_COUNT);
        $code = '';
        if ($codeLength > self::PAIR_CODE_LENGTH) {
            for ($i = 0; $i < self::GRID_CODE_LENGTH; $i++) {
                $latDigit = $latVal % self::GRID_ROWS;
                $lngDigit = $lngVal % self::GRID_COLUMNS;
                $code = self::CODE_ALPHABET[$latDigit * self::GRID_COLUMNS + $lngDigit].$code;
                $latVal = intdiv($latVal, self::GRID_ROWS);
                $lngVal = intdiv($lngVal, self::GRID_COLUMNS);
            }
        } else {
            $latVal = intdiv($latVal, self::GRID_ROWS ** self::GRID_CODE_LENGTH);
            $lngVal = intdiv($lngVal, self::GRID_COLUMNS ** self::GRID_CODE_LENGTH);
        }
        for ($i = 0; $i < self::PAIR_CODE_LENGTH / 2; $i++) {
            $code = self::CODE_ALPHABET[$lngVal % self::ENCODING_BASE].$code;
            $code = self::CODE_ALPHABET[$latVal % self::ENCODING_BASE].$code;
            $latVal = intdiv($latVal, self::ENCODING_BASE);
            $lngVal = intdiv($lngVal, self::ENCODING_BASE);
        }
        $code = substr($code, 0, self::SEPARATOR_POSITION).self::SEPARATOR.substr($code, self::SEPARATOR_POSITION);
        if ($codeLength >= self::SEPARATOR_POSITION) {
            return substr($code, 0, $codeLength + 1);
        }

        return substr($code, 0, $codeLength).str_repeat(self::PADDING_CHARACTER, self::SEPARATOR_POSITION - $codeLength).self::SEPARATOR;
    }

    /** Decode a full code into the area it stands for. */
    public static function decode(string $code): CodeArea
    {
        if (! self::isFull($code)) {
            throw new InvalidCode('Passed Open Location Code is not a valid full code - '.$code);
        }
        $code = strtoupper(str_replace([self::SEPARATOR, self::PADDING_CHARACTER], '', $code));
        $code = substr($code, 0, self::MAX_DIGIT_COUNT);
        $normalLat = -self::LATITUDE_MAX * self::PAIR_PRECISION;
        $normalLng = -self::LONGITUDE_MAX * self::PAIR_PRECISION;
        $gridLat = 0;
        $gridLng = 0;
        $digits = min(strlen($code), self::PAIR_CODE_LENGTH);
        $pv = self::PAIR_FIRST_PLACE_VALUE;
        for ($i = 0; $i < $digits; $i += 2) {
            $normalLat += strpos(self::CODE_ALPHABET, $code[$i]) * $pv;
            $normalLng += strpos(self::CODE_ALPHABET, $code[$i + 1]) * $pv;
            if ($i < $digits - 2) {
                $pv = intdiv($pv, self::ENCODING_BASE);
            }
        }
        $latPrecision = $pv / self::PAIR_PRECISION;
        $lngPrecision = $pv / self::PAIR_PRECISION;
        if (strlen($code) > self::PAIR_CODE_LENGTH) {
            $rowpv = self::GRID_LAT_FIRST_PLACE_VALUE;
            $colpv = self::GRID_LNG_FIRST_PLACE_VALUE;
            $digits = min(strlen($code), self::MAX_DIGIT_COUNT);
            for ($i = self::PAIR_CODE_LENGTH; $i < $digits; $i++) {
                $digitVal = strpos(self::CODE_ALPHABET, $code[$i]);
                $gridLat += intdiv($digitVal, self::GRID_COLUMNS) * $rowpv;
                $gridLng += ($digitVal % self::GRID_COLUMNS) * $colpv;
                if ($i < $digits - 1) {
                    $rowpv = intdiv($rowpv, self::GRID_ROWS);
                    $colpv = intdiv($colpv, self::GRID_COLUMNS);
                }
            }
            $latPrecision = $rowpv / self::FINAL_LAT_PRECISION;
            $lngPrecision = $colpv / self::FINAL_LNG_PRECISION;
        }
        $lat = $normalLat / self::PAIR_PRECISION + $gridLat / self::FINAL_LAT_PRECISION;
        $lng = $normalLng / self::PAIR_PRECISION + $gridLng / self::FINAL_LNG_PRECISION;

        return new CodeArea(round($lat, 14), round($lng, 14), round($lat + $latPrecision, 14), round($lng + $lngPrecision, 14), min(strlen($code), self::MAX_DIGIT_COUNT));
    }
}
