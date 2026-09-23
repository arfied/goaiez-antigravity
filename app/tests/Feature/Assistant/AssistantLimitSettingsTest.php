<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Assistant\PriceBook;
use App\Services\Assistant\UrgentTerms;
use App\Services\Knowledge\DocumentChunker;
use App\Support\Tenancy;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('a written label length of 3 refuses a 4-char label in PriceBook', function () {
    PlatformSetting::write('assistant.pricebook.max_label_length', 3, 'test');

    $book = app(PriceBook::class);

    $this->expectException(InvalidArgumentException::class);
    $book->set('abcd', 1000, null);
});

it('a written max_terms of 1 refuses a second urgent term', function () {
    PlatformSetting::write('assistant.urgent_terms.max_terms', 1, 'test');

    $terms = app(UrgentTerms::class);
    $terms->add('first');

    $this->expectException(InvalidArgumentException::class);
    $terms->add('second');
});

it('chunker minimum written to 5 drops a 4-char chunk', function () {
    PlatformSetting::write('knowledge.chunker.minimum_characters', 5, 'test');

    $chunker = app(DocumentChunker::class);
    $chunks = $chunker->chunk("12345\n\nabcd");

    expect($chunks)->not->toContain('abcd');
});
