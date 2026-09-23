<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\ReviewSource;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class DefenseScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = Business::provision([
            'owner_user_id' => $user->id,
            'name' => 'Acme Defense '.rand(100, 999),
        ]);

        if ($advanced) {
            $business->update(['advanced_dashboard_enabled' => true]);
        }

        Tenancy::setUser((int) $user->id);
        Tenancy::set((int) $business->id);

        DB::statement("SET app.business_id = '{$business->id}'");

        return [$user, $business];
    }

    public function test_get_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.defense'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_a_fresh_tenant_states_the_threshold_and_zero_counts(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.defense'));
        $response->assertOk();

        $response->assertSee('below 4 stars');
        $response->assertSee('Feedback received');
        $response->assertSee('Kept private');
        $response->assertSee('Invited to Google');
        $response->assertSee('Removal requests');
        $response->assertDontSee('Shield');
        $response->assertDontSee('Protected Public Average');
        $response->assertDontSee('Empathetic');
    }

    public function test_the_counts_split_this_tenants_feedback_by_the_threshold(): void
    {
        // Tenant A
        [$userA, $bizA] = $this->createTenant(advanced: true);

        Review::factory()->create(['source' => ReviewSource::FirstParty, 'rating' => 2]);

        $review2 = Review::factory()->make(['source' => ReviewSource::FirstParty, 'rating' => 5]);
        $review2->forceFill(['google_invite_sent_at' => now()])->save();

        Review::factory()->create(['source' => ReviewSource::FirstParty, 'rating' => 5, 'created_at' => now()->subDays(120)]);

        // Tenant B
        [$userB, $bizB] = $this->createTenant(advanced: true);

        Tenancy::setUser((int) $userB->id);
        Tenancy::set((int) $bizB->id);
        DB::statement("SET app.business_id = '{$bizB->id}'");

        Review::factory()->create(['source' => ReviewSource::FirstParty, 'rating' => 1]);

        // Back to A
        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);
        DB::statement("SET app.business_id = '{$bizA->id}'");

        $response = $this->actingAs($userA)->get(route('advanced.defense'));
        $response->assertOk();

        $response->assertSeeInOrder(['Feedback received', '2']);
        $response->assertSeeInOrder(['Kept private', '1']);
        $response->assertSeeInOrder(['Invited to Google', '1']);
    }
}
