<?php

declare(strict_types=1);

namespace Plastarc\Ubid;

/**
 * The area an Open Location Code stands for: its south-west and north-east corners, its center
 * and the number of significant digits the code carried. Latitudes and longitudes are decimal degrees.
 */
class CodeArea
{
    public readonly float $latitudeCenter;

    public readonly float $longitudeCenter;

    public function __construct(
        public readonly float $latitudeLo,
        public readonly float $longitudeLo,
        public readonly float $latitudeHi,
        public readonly float $longitudeHi,
        public readonly int $codeLength,
    ) {
        $this->latitudeCenter = min($latitudeLo + ($latitudeHi - $latitudeLo) / 2, OpenLocationCode::LATITUDE_MAX);
        $this->longitudeCenter = min($longitudeLo + ($longitudeHi - $longitudeLo) / 2, OpenLocationCode::LONGITUDE_MAX);
    }

    public function height(): float
    {
        return $this->latitudeHi - $this->latitudeLo;
    }

    public function width(): float
    {
        return $this->longitudeHi - $this->longitudeLo;
    }

    /** Area in square degrees. */
    public function area(): float
    {
        return $this->height() * $this->width();
    }

    /** @return array{0: float, 1: float} latitude, longitude of the center */
    public function latLng(): array
    {
        return [$this->latitudeCenter, $this->longitudeCenter];
    }
}
