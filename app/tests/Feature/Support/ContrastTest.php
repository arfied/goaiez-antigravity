<?php

declare(strict_types=1);

use App\Models\IndustryStartingPoint;
use App\Support\Contrast;

test('ratio computes correctly for known pairs', function (): void {
    expect(Contrast::ratio('#000000', '#ffffff'))->toBeFloat()->toEqualWithDelta(21.0, 0.01)
        ->and(Contrast::ratio('#767676', '#ffffff'))->toEqualWithDelta(4.54, 0.01)
        ->and(Contrast::ratio('#ffffff', '#000000'))->toEqualWithDelta(21.0, 0.01)
        ->and(Contrast::ratio('#777777', '#ffffff'))->toBeLessThan(4.5)
        ->and(Contrast::ratio('#777777', '#ffffff'))->toEqualWithDelta(4.48, 0.01);
});

test('isHex validates format', function (): void {
    expect(Contrast::isHex('#abcdef'))->toBeTrue()
        ->and(Contrast::isHex('#ABCDEF'))->toBeTrue()
        ->and(Contrast::isHex('#123456'))->toBeTrue()
        ->and(Contrast::isHex('#abc'))->toBeFalse()
        ->and(Contrast::isHex('zzz'))->toBeFalse()
        ->and(Contrast::isHex('#12345g'))->toBeFalse();
});

test('ratio throws on invalid hex', function (): void {
    expect(fn () => Contrast::ratio('zzz', '#fff'))->toThrow(InvalidArgumentException::class, 'not a six-digit hex colour: zzz');
});

test('seeded palettes pass contrast gates', function (): void {
    $seeds = IndustryStartingPoint::all();

    foreach ($seeds as $seed) {
        $ink = $seed->palette['ink'];
        $surface = $seed->palette['surface'];
        $ratio = Contrast::ratio($ink, $surface);

        expect($ratio)->toBeGreaterThanOrEqual(Contrast::AA_TEXT, "Seed {$seed->family->value} ink {$ink} on surface {$surface} failed contrast: {$ratio}");
    }
});
