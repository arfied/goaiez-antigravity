<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\UserRole;
use App\Models\Plugin;
use App\Models\Review;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class WidgetReviewFeedTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_unknown_embed_key_returns_not_found(): void
    {
        Tenancy::forgetAll();
        
        $this->getJson(route('api.widget.reviews', ['embed_key' => Str::uuid()->toString()]))
            ->assertNotFound();
    }

    public function test_known_key_missing_origin_returns_forbidden(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);
        
        $plugin = Plugin::factory()->create([
            'allowed_domains' => ['example.com'],
        ]);
        
        Tenancy::forgetAll();
        
        $this->getJson(route('api.widget.reviews', ['embed_key' => $plugin->embed_key]))
            ->assertForbidden()
            ->assertExactJson(['message' => 'This widget is not enabled for that domain.']);
    }

    public function test_allowed_origin_returns_correct_reviews(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);
        
        $plugin = Plugin::factory()->create([
            'allowed_domains' => ['example.com'],
            'min_stars_to_show' => 4,
        ]);
        
        $includedReview = Review::factory()->fromGoogle()->approved()->create([
            'display_on_website' => true,
            'rating' => 4,
        ]);
        
        $excludedLowRating = Review::factory()->fromGoogle()->approved()->create([
            'display_on_website' => true,
            'rating' => 3,
        ]);
        
        $excludedNotDisplayed = Review::factory()->fromGoogle()->approved()->create([
            'display_on_website' => false,
            'rating' => 5,
        ]);
        
        // Tenant B
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        
        Tenancy::set((int) $bizB->id);
        
        $excludedOtherTenant = Review::factory()->fromGoogle()->approved()->create([
            'display_on_website' => true,
            'rating' => 5,
        ]);
        
        Tenancy::forgetAll();
        
        $response = $this->withHeaders(['HTTP_ORIGIN' => 'https://example.com'])
            ->getJson(route('api.widget.reviews', ['embed_key' => $plugin->embed_key]))
            ->assertOk();
            
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals($includedReview->id, $data[0]['id']);
    }
}