<?php

declare(strict_types=1);

use App\Enums\IndustryFamily;
use App\Models\Business;
use App\Services\Facts\BusinessFacts;
use App\Services\Industry\IndustryQuestions;

it('returns empty for null family', function () {
    expect(app(IndustryQuestions::class)->for(null))->toBe([]);
});

it('seeds 4 questions with industry prefix and exactly one hero per family', function () {
    $q = app(IndustryQuestions::class);
    foreach (IndustryFamily::cases() as $family) {
        $questions = $q->for($family);
        expect($questions)->toHaveCount(4);

        $heroCount = 0;
        foreach ($questions as $key => $def) {
            expect($key)->toStartWith('industry.');
            if ($def['hero']) {
                $heroCount++;
            }
        }
        expect($heroCount)->toBe(1);
    }
});

it('follows the resolver for business', function () {
    $biz = Business::factory()->create();
    app(BusinessFacts::class)->set($biz->id, 'industry', 'trades');

    $questions = app(IndustryQuestions::class)->forBusiness($biz->id);
    expect($questions)->toHaveKey('industry.emergency_callouts');
});

it('finds hero key for trades', function () {
    $biz = Business::factory()->create();
    app(BusinessFacts::class)->set($biz->id, 'industry', 'trades');

    expect(app(IndustryQuestions::class)->heroKeyFor($biz->id))->toBe('industry.emergency_callouts');
});
