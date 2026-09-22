<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\UserRole;
use App\Models\AutopilotSettings;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\ReviewHubPage;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class ReviewHubPublicPageTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_published_hub_page_returns_ok_and_shows_business_name(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);
        
        $location = Location::factory()->create();
        AutopilotSettings::factory()->create(['location_id' => $location->id, 'update_review_hub' => true]);
        
        $slug = 'test-slug-pub';
        FeedbackPage::factory()->forLocation($location)->create(['slug' => $slug]);
        ReviewHubPage::factory()->create([
            'location_id' => $location->id,
            'slug' => $slug,
            'is_published' => true,
        ]);
        
        Tenancy::forgetAll();
        
        $this->get(route('review-hub.show', ['slug' => $slug]))
            ->assertOk()
            ->assertSee($location->businessName());
    }

    public function test_unknown_slug_returns_not_found(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);
        
        $location = Location::factory()->create();
        AutopilotSettings::factory()->create(['location_id' => $location->id, 'update_review_hub' => true]);
        
        $slug = 'test-slug-unk';
        FeedbackPage::factory()->forLocation($location)->create(['slug' => $slug]);
        ReviewHubPage::factory()->create([
            'location_id' => $location->id,
            'slug' => $slug,
            'is_published' => true,
        ]);
        
        Tenancy::forgetAll();
        
        $this->get(route('review-hub.show', ['slug' => 'other-slug']))
            ->assertNotFound();
    }

    public function test_unpublished_hub_returns_not_found_with_same_body_as_unknown(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);
        
        $location = Location::factory()->create();
        AutopilotSettings::factory()->create(['location_id' => $location->id, 'update_review_hub' => true]);
        
        $slug = 'test-slug-unpub';
        FeedbackPage::factory()->forLocation($location)->create(['slug' => $slug]);
        ReviewHubPage::factory()->create([
            'location_id' => $location->id,
            'slug' => $slug,
            'is_published' => false,
        ]);
        
        Tenancy::forgetAll();
        
        $notFoundResponse = $this->get(route('review-hub.show', ['slug' => 'unknown-slug']));
        $notFoundResponse->assertNotFound();
        
        $response = $this->get(route('review-hub.show', ['slug' => $slug]))
            ->assertNotFound();
            
        $body1 = str_replace('unknown-slug', 'normalized', $notFoundResponse->getContent());
        $body2 = str_replace($slug, 'normalized', $response->getContent());
        
        $this->assertSame(preg_replace('/\s+/', '', strip_tags($body1)), preg_replace('/\s+/', '', strip_tags($body2)));
    }

    public function test_cross_tenant_hub_page_slug_requested_returns_not_found(): void
    {
        // Tenant A
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);
        
        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        
        $locationA = Location::factory()->create();
        AutopilotSettings::factory()->create(['location_id' => $locationA->id, 'update_review_hub' => true]);
        
        $slugA = 'shared-slug';
        FeedbackPage::factory()->forLocation($locationA)->create(['slug' => $slugA]);
        
        // Tenant B
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        
        $locationB = Location::factory()->create();
        AutopilotSettings::factory()->create(['location_id' => $locationB->id, 'update_review_hub' => true]);
        
        ReviewHubPage::factory()->create([
            'location_id' => $locationB->id,
            'slug' => 'shared-slug', // Tenant B has the hub page slug
            'is_published' => true,
        ]);
        
        Tenancy::forgetAll();
        
        $this->get(route('review-hub.show', ['slug' => 'shared-slug']))
            ->assertNotFound();
    }
}