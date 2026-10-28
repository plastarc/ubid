<?php

declare(strict_types=1);

namespace Plastarc\Ubid;

/**
 * The area a UBID stands for: the bounding box it encodes, with the Open Location Code cell at its center.
 */
final class UbidArea extends CodeArea
{
    public function __construct(
        public readonly CodeArea $centroid,
        float $latitudeLo,
        float $longitudeLo,
        float $latitudeHi,
        float $longitudeHi,
        int $codeLength,
        public readonly ?string $code = null,
    ) {
        parent::__construct($latitudeLo, $longitudeLo, $latitudeHi, $longitudeHi, $codeLength);
    }

    /** The code this area was decoded from; for an area built by hand, the code its box encodes to. */
    public function code(): string
    {
        return $this->code ?? $this->encode();
    }

    /**
     * Encode this box afresh with the same center cell. A box straight from decode() spans whole cells, so its
     * corners encode to the neighboring cells and the extents grow by one; call resize() first to round-trip.
     */
    public function encode(): string
    {
        return Ubid::encode($this->latitudeLo, $this->longitudeLo, $this->latitudeHi, $this->longitudeHi, $this->centroid->latitudeCenter, $this->centroid->longitudeCenter, $this->codeLength);
    }

    /**
     * The box shared with another area, as [longitudeLo, latitudeLo, longitudeHi, latitudeHi], or null when they do not touch.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public function intersection(?CodeArea $other): ?array
    {
        if ($other === null) {
            return null;
        }
        $latitudeLo = max($this->latitudeLo, $other->latitudeLo);
        $latitudeHi = min($this->latitudeHi, $other->latitudeHi);
        $longitudeLo = max($this->longitudeLo, $other->longitudeLo);
        $longitudeHi = min($this->longitudeHi, $other->longitudeHi);
        if ($latitudeLo > $latitudeHi || $longitudeLo > $longitudeHi) {
            return null;
        }

        return [$longitudeLo, $latitudeLo, $longitudeHi, $latitudeHi];
    }

    /**
     * Jaccard index with another area: shared area over combined area, 1.0 for the same box, 0.0 for
     * boxes that only touch, null when they do not intersect. The standard's measure of "same building".
     */
    public function jaccard(?CodeArea $other): ?float
    {
        $bbox = $this->intersection($other);
        if ($bbox === null) {
            return null;
        }
        $area = ($bbox[3] - $bbox[1]) * ($bbox[2] - $bbox[0]);

        return $area / ($this->area() + $other->area() - $area);
    }

    /** Shrink the box by half a center cell on every side: the tightest box that still encodes to the same code. */
    public function resize(): self
    {
        $halfHeight = $this->centroid->height() / 2;
        $halfWidth = $this->centroid->width() / 2;

        return new self($this->centroid, $this->latitudeLo + $halfHeight, $this->longitudeLo + $halfWidth, $this->latitudeHi - $halfHeight, $this->longitudeHi - $halfWidth, $this->codeLength);
    }
}
