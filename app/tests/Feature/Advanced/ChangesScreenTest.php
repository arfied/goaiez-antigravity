<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\SiteChange;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class ChangesScreenTest extends TestCase
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
        $response = $this->actingAs($user)->get(route('advanced.changes'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.changes'));
        $response->assertSee('No automated changes yet. Changes appear here after the first site change runs.');
    }

    public function test_one_site_change_row_renders_its_distinctive_value(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $location = $business->locations()->first();
        $location->forceFill(['website_url' => 'https://example.test', 'website_confirmed_at' => now()])->save();

        SiteChange::factory()->applied()->create([
            'location_id' => $location->id,
            'url' => 'https://example.test/distinctive-value-7719',
        ]);

        $response = $this->actingAs($user)->get(route('advanced.changes'));
        $response->assertSee('example.test/distinctive-value-7719');
        $response->assertDontSee('No automated changes yet. Changes appear here after the first site change runs.');
    }
}
