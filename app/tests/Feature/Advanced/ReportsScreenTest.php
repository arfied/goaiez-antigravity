<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Review;
use App\Models\ReviewAsk;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class ReportsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = Business::provision([
            'owner_user_id' => $user->id,
            'name' => 'Acme Dental '.rand(100, 999),
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
        $response = $this->actingAs($user)->get(route('advanced.reports'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_a_fresh_tenant_shows_zeros_and_promises_nothing(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.reports'));
        $response->assertOk();

        $response->assertSee('Reviews received');
        $response->assertSee('Review asks sent');
        $response->assertSee('Calls');
        $response->assertSee('Broadcasts drafted');
        $response->assertSee('Ad conversions');
        $response->assertDontSee('sentiment');
        $response->assertDontSee('downloadable');
        $response->assertDontSee('Preview — not live');
    }

    public function test_counts_are_this_months_rows_of_this_tenant(): void
    {
        // Tenant A
        [$userA, $bizA] = $this->createTenant(advanced: true);

        Review::factory()->create(['created_at' => now()]);
        Review::factory()->create(['created_at' => now()->subMonths(2)]);
        ReviewAsk::factory()->create(['created_at' => now()]);
        Call::factory()->create(['started_at' => now()]);
        Campaign::factory()->create(['created_at' => now()]);

        // Tenant B
        [$userB, $bizB] = $this->createTenant(advanced: true);

        Review::factory()->create(['created_at' => now()]);
        ReviewAsk::factory()->create(['created_at' => now()]);
        Call::factory()->create(['started_at' => now()]);
        Campaign::factory()->create(['created_at' => now()]);

        // Switch back to A
        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);
        DB::statement("SET app.business_id = '{$bizA->id}'");

        $response = $this->actingAs($userA)->get(route('advanced.reports'));
        $response->assertOk();

        $response->assertSeeInOrder(['Reviews received', '1']);
        $response->assertSeeInOrder(['Review asks sent', '1']);
        $response->assertSeeInOrder(['Calls', '1']);
        $response->assertSeeInOrder(['Broadcasts drafted', '1']);
    }
}
