<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Plastarc\Ubid\Laravel\Casts\AsUbidArea;
use Plastarc\Ubid\Laravel\HasUbid;
use Plastarc\Ubid\Laravel\Rules\ValidUbid;
use Plastarc\Ubid\UbidArea;

class Building extends Model
{
    use HasUbid;

    protected $guarded = [];

    public $timestamps = false;

    /** @return array{0: float, 1: float, 2: float, 3: float}|null */
    public function ubidBounds(): ?array
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        return [(float) $this->lat, (float) $this->lng, (float) $this->lat, (float) $this->lng];
    }
}

class DecodedBuilding extends Model
{
    protected $table = 'buildings';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['ubid' => AsUbidArea::class];
}

beforeEach(function () {
    Schema::create('buildings', function (Blueprint $table) {
        $table->id();
        $table->decimal('lat', 10, 7)->nullable();
        $table->decimal('lng', 10, 7)->nullable();
        $table->string('ubid', 40)->nullable()->index();
    });
});

test('HasUbid sets the code on save and clears it when the geometry goes', function () {
    $b = Building::create(['lat' => 39.154446, 'lng' => -75.5467515]);
    expect($b->ubid)->toBe('87F65F33+Q7-0-0-0-0');

    $b->update(['lat' => 39.16, 'lng' => -75.52]);
    expect($b->fresh()->ubid)->not->toBe('87F65F33+Q7-0-0-0-0')->and($b->fresh()->ubid)->toStartWith('87F6');

    $b->update(['lat' => null]);
    expect($b->fresh()->ubid)->toBeNull();
});

test('the cast decodes to an area and accepts an area or a string', function () {
    Building::create(['lat' => 39.154446, 'lng' => -75.5467515]);
    $d = DecodedBuilding::first();
    expect($d->ubid)->toBeInstanceOf(UbidArea::class)->and($d->ubid->centroid->codeLength)->toBe(10);
    $d->ubid = '6FG22222+22-0-0-0-0';
    $d->save();
    expect(DecodedBuilding::first()->ubid->code())->toBe('6FG22222+22-0-0-0-0');
    $d->ubid = $d->ubid;
    $d->save();
    expect(DecodedBuilding::first()->ubid->code())->toBe('6FG22222+22-0-0-0-0');
});

test('the validation rule', function () {
    expect(Validator::make(['ubid' => '87F65F33+Q7-0-0-0-0'], ['ubid' => [new ValidUbid]])->passes())->toBeTrue()
        ->and(Validator::make(['ubid' => '87F65F33+Q7'], ['ubid' => [new ValidUbid]])->passes())->toBeFalse();
});

test('ubid:backfill fills rows that were inserted without a code', function () {
    Building::query()->insert([['lat' => 39.154446, 'lng' => -75.5467515, 'ubid' => null], ['lat' => null, 'lng' => null, 'ubid' => null]]);
    $this->artisan('ubid:backfill', ['model' => Building::class, '--dry-run' => true])->expectsOutputToContain('2 rows seen, 1 would change')->assertSuccessful();
    expect(Building::whereNotNull('ubid')->count())->toBe(0);
    $this->artisan('ubid:backfill', ['model' => Building::class])->expectsOutputToContain('1 updated')->assertSuccessful();
    expect(Building::first()->ubid)->toBe('87F65F33+Q7-0-0-0-0');
    $this->artisan('ubid:backfill', ['model' => 'Nope'])->assertFailed();
});
