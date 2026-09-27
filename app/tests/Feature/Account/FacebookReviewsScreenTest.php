<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\ReviewSource;
use App\Enums\UserRole;
use App\Livewire\Account\FacebookReviews;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class FacebookReviewsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    private User $owner;

    private $biz;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);

        $this->location = Location::factory()->forBusiness($this->biz->id)->create();
    }

    public function test_empty_state(): void
    {
        $this->get(route('account.facebook-reviews'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertSee('No Facebook reviews yet.', false);
    }

    public function test_renders_reviews_with_stars_and_recommendation(): void
    {
        Review::factory()->create([
            'location_id' => $this->location->id,
            'source' => ReviewSource::Facebook,
            'rating' => 4,
            'comment' => 'Distinctive fb review 5701',
        ]);

        Review::factory()->create([
            'location_id' => $this->location->id,
            'source' => ReviewSource::Facebook,
            'rating' => 5,
            'recommendation' => 'positive',
        ]);

        $this->get(route('account.facebook-reviews'))
            ->assertOk()
            ->assertSee('Distinctive fb review 5701')
            ->assertSee('Recommends')
            ->assertDontSee('No Facebook reviews yet.');
    }

    public function test_skips_google_reviews(): void
    {
        Review::factory()->create([
            'location_id' => $this->location->id,
            'source' => ReviewSource::Google,
            'comment' => 'Distinctive google review 5702',
        ]);

        $this->get(route('account.facebook-reviews'))
            ->assertOk()
            ->assertDontSee('Distinctive google review 5702');
    }

    public function test_skips_other_tenant_rows(): void
    {
        $otherBiz = TestCase::provisionTenant();
        $otherLoc = Location::factory()->forBusiness($otherBiz->id)->create();

        Review::factory()->create([
            'location_id' => $otherLoc->id,
            'source' => ReviewSource::Facebook,
            'comment' => 'Distinctive other tenant 5703',
        ]);

        $this->get(route('account.facebook-reviews'))
            ->assertOk()
            ->assertDontSee('Distinctive other tenant 5703');
    }

    public function test_staff_role_forbidden(): void
    {
        Tenancy::forgetAll();
        Livewire::test(FacebookReviews::class)->assertForbidden();
    }
}
