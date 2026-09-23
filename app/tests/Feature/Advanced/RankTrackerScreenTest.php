<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\AutomationRunStatus;
use App\Enums\ConnectionStatus;
use App\Enums\OauthProvider;
use App\Enums\UserRole;
use App\Models\GscSiteProperty;
use App\Models\Location;
use App\Models\OauthConnection;
use App\Models\User;
use App\Services\Visibility\VisibilityReadings;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class RankTrackerScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant([
            'owner_user_id' => $user->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        if ($advanced) {
            $business->update(['advanced_dashboard_enabled' => true]);
        }

        Tenancy::setUser((int) $user->id);
        Tenancy::set((int) $business->id);

        return [$user, $business];
    }

    public function test_get_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.rank-tracker'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $business->locations()->delete();

        $response = $this->actingAs($user)->get(route('advanced.rank-tracker'));
        $response->assertSee('Add a location to track its search visibility.');
    }

    public function test_a_location_with_readings_renders_its_totals(): void
    {
        [$userA, $businessA] = $this->createTenant(advanced: true);
        $location = $businessA->locations()->first();

        [$userB, $businessB] = $this->createTenant(advanced: true);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $businessA->id);

        OauthConnection::factory()->create([
            'business_id' => $businessA->id,
            'provider' => OauthProvider::Gsc,
            'status' => ConnectionStatus::Active,
        ]);

        GscSiteProperty::factory()->create([
            'business_id' => $businessA->id,
            'location_id' => $location->id,
        ]);

        $now = CarbonImmutable::today('America/Los_Angeles')->subDay();
        $start = $now->subDays(27);
        $earlierEnd = $start->subDay();
        $earlierStart = $earlierEnd->subDays(27);

        DB::table('automation_runs')->insert([
            'business_id' => $businessA->id,
            'location_id' => $location->id,
            'automation_key' => 'visibility.search_console_sync',
            'status' => AutomationRunStatus::Succeeded->value,
            'input' => json_encode([]),
            'output' => json_encode(['window_start' => $earlierStart->toDateString(), 'outcome' => 'synced']),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        app(VisibilityReadings::class)->record($location, \measurementSearchResult(
            clicksPerDay: 13,
            from: $earlierStart,
            to: $now
        ));

        $response = $this->actingAs($userA)->get(route('advanced.rank-tracker'));
        $response->assertSee('7,280');
        $response->assertSee($location->name);

        Tenancy::set((int) $businessB->id);
        $locationB = Location::factory()->create(['business_id' => $businessB->id, 'name' => 'Dental B Location']);
        Tenancy::set((int) $businessA->id);

        $response->assertDontSee($locationB->name);
    }

    public function test_no_scan_control_and_the_honest_footer(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);

        $response = $this->actingAs($user)->get(route('advanced.rank-tracker'));

        $response->assertDontSee('runScan');
        $response->assertSee('Map-pack rank tracking is not available yet.');
    }
}
