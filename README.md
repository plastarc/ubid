# UBID for PHP

A PHP port of the **Unique Building Identifier (UBID)**, the open building identifier from the US Department of Energy and Pacific Northwest National Laboratory, with an optional Laravel layer that keeps a model's code in step with its geometry.

A UBID is the [Open Location Code](https://github.com/google/open-location-code) (plus code) of a building's center cell, followed by how many cells of that size its bounding box extends north, east, south and west:

```
87F65F33+Q7-3-4-2-2
```

Anyone with the same footprint gets the same code, with no registry and no sign-up. A point with no footprint encodes as `…-0-0-0-0`. Two codes are compared by how much their boxes overlap. The arithmetic here follows PNNL's reference implementation line by line and is tested against its cases and Google's plus-code vectors, so a code made with this package matches one made with the [Python](https://github.com/pnnl/buildingid-py), [JavaScript](https://github.com/pnnl/buildingid-js) or [PostgreSQL](https://github.com/pnnl/buildingid-plpgsql) libraries.

## Install

```bash
composer require plastarc/ubid
```

PHP 8.3 or later. The core has no dependencies. The Laravel pieces load when Laravel is present (11, 12 or 13).

## Encode, decode, compare

```php
use Plastarc\Ubid\Ubid;

// A footprint's bounding box (south-west corner, north-east corner) and its centroid, in decimal degrees.
Ubid::encode(39.154176, -75.547102, 39.154716, -75.546402, 39.154446, -75.546752);
// => "87F65F33+Q7-3-4-2-2"

// A bounding box alone, taking its geometric center as the centroid.
Ubid::fromBounds(39.154176, -75.547102, 39.154716, -75.546402);

// A single point, no footprint.
Ubid::fromPoint(39.154446, -75.5467515);
// => "87F65F33+Q7-0-0-0-0"

Ubid::isValid('87F65F33+Q7-0-0-0-0');   // true
Ubid::centerCode('87F65F33+Q7-3-4-2-2'); // "87F65F33+Q7"

$area = Ubid::decode('87F65F33+Q7-3-4-2-2');
$area->latitudeLo; $area->longitudeLo; $area->latitudeHi; $area->longitudeHi; // the box
$area->centroid;                                                                // the center cell (a CodeArea)
$area->code();                                                                  // the code it came from

Ubid::jaccard('87F65F33+Q7-3-4-2-2', '87F65F33+Q7-0-0-0-0'); // 0.0 .. 1.0, or null when the boxes do not meet
```

`Plastarc\Ubid\OpenLocationCode` is also public, for plus codes on their own: `encode`, `decode`, `isValid`, `isShort`, `isFull`.

Invalid input throws `Plastarc\Ubid\Exceptions\InvalidCode`.

## Laravel

The service provider is auto-discovered. Three pieces, use any of them.

**`HasUbid`** recomputes the code on every save, so a building placed by hand, re-geocoded or given a footprint carries the right code without a batch step. The model says where its geometry is:

```php
use Plastarc\Ubid\Laravel\HasUbid;

class Building extends Model
{
    use HasUbid;

    /** [latLo, lngLo, latHi, lngHi, latCenter?, lngCenter?] or null while the building is unplaced. */
    public function ubidBounds(): ?array
    {
        if ($this->footprint) {
            return [$this->footprint->south, $this->footprint->west, $this->footprint->north, $this->footprint->east, $this->lat, $this->lng];
        }

        return $this->lat === null ? null : [$this->lat, $this->lng, $this->lat, $this->lng];
    }

    // public function ubidColumn(): string { return 'ubid'; }  // the default
}
```

Add the column in a migration: `$table->string('ubid', 40)->nullable()->index();`. Not unique on purpose: two buildings can share a footprint.

**`ubid:backfill`** fills or refreshes every row of such a model:

```bash
php artisan ubid:backfill "App\Models\Building" --dry-run
php artisan ubid:backfill "App\Models\Building"
```

**`ValidUbid`** is a validation rule, and **`AsUbidArea`** is a cast that reads the stored string as a `UbidArea`:

```php
use Plastarc\Ubid\Laravel\Rules\ValidUbid;
use Plastarc\Ubid\Laravel\Casts\AsUbidArea;

$request->validate(['ubid' => ['required', new ValidUbid]]);

protected $casts = ['ubid' => AsUbidArea::class];
```

## Tests

```bash
composer install
vendor/bin/pest
```

The suite runs PNNL's reference cases, all of Google's plus-code encoding and decoding vectors, and the Laravel layer on an in-memory SQLite database through Testbench.

## Credits and license

MIT, see `LICENSE`. The UBID specification and reference implementation are by Battelle Memorial Institute for the US Department of Energy ([pnnl/buildingid](https://github.com/pnnl/buildingid)); Open Location Code is by Google (Apache 2.0). Both notices are in `NOTICE`. Paper: Wang, N. et al. (2019), *Unique Building Identifier: a natural key for building data matching and its energy applications*, Energy and Buildings, https://www.osti.gov/biblio/1508514.

Built by [PLASTARC](https://plastarc.com).
