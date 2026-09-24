<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Enums\IndustryFamily;
use App\Models\BusinessFact;
use App\Models\User;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;
use App\Services\Industry\IndustryResolver;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class IndustryResolverTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_resolves_industry_source_and_family(): void
    {
        $owner = User::factory()->create();
        $business = self::provisionTenant(['owner_user_id' => $owner->id]);
        $business->update(['industry' => 'trades']);
        Tenancy::setUser((int) $owner->id);
        Tenancy::set((int) $business->id);

        $resolver = app(IndustryResolver::class);

        $this->assertEquals(['family' => IndustryFamily::Trades, 'source' => 'places'], $resolver->for($business->id));

        app(BusinessFacts::class)->set($business->id, BusinessFactKey::INDUSTRY, 'food');
        $this->assertEquals(['family' => IndustryFamily::Food, 'source' => 'owner'], $resolver->for($business->id));

        BusinessFact::query()->where('business_id', $business->id)->where('key', BusinessFactKey::INDUSTRY)->update(['value' => 'plumbing']);
        $this->assertEquals(['family' => IndustryFamily::Trades, 'source' => 'places'], $resolver->for($business->id));

        BusinessFact::query()->where('business_id', $business->id)->where('key', BusinessFactKey::INDUSTRY)->delete();
        $business->update(['industry' => null]);
        $this->assertEquals(['family' => null, 'source' => 'none'], $resolver->for($business->id));
    }
}
