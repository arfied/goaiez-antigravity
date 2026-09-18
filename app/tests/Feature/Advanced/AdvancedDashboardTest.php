<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Livewire\Account\Settings;
use App\Livewire\Advanced\Citations as CitationsComponent;
use App\Models\Business;
use App\Models\Citation;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class AdvancedDashboardTest extends TestCase
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

        return [$user, $business];
    }

    public function test_unauthorized_when_advanced_dashboard_is_disabled(): void
    {
        [$user, $business] = $this->createTenant(advanced: false);

        $response = $this->actingAs($user)->get(route('advanced.home'));
        $response->assertRedirect(route('account.settings'));

        $responseCitations = $this->actingAs($user)->get(route('advanced.citations'));
        $responseCitations->assertRedirect(route('account.settings'));
    }

    public function test_accessible_when_advanced_dashboard_is_enabled(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Google Business Profile',
            'nap_status' => 'consistent',
            'url' => 'https://google.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Yelp',
            'nap_status' => 'consistent',
            'url' => 'https://yelp.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Bing',
            'nap_status' => 'consistent',
            'url' => 'https://bing.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'YellowPages',
            'nap_status' => 'mismatch',
            'url' => 'https://yp.com',
        ]);

        $response = $this->actingAs($user)->get(route('advanced.home'));
        $response->assertOk();

        $responseCitations = $this->actingAs($user)->get(route('advanced.citations'));
        $responseCitations->assertOk();
        $responseCitations->assertSee('NAP Consistency');
    }

    public function test_citations_livewire_component_manages_directories(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Google Business Profile',
            'nap_status' => 'consistent',
            'url' => 'https://google.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Yelp',
            'nap_status' => 'consistent',
            'url' => 'https://yelp.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Bing',
            'nap_status' => 'consistent',
            'url' => 'https://bing.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'YellowPages',
            'nap_status' => 'mismatch',
            'url' => 'https://yp.com',
        ]);

        Livewire::actingAs($user)
            ->test(CitationsComponent::class)
            ->assertSet('healthPercentage', 75)
            ->call('runScan');

        $this->assertDatabaseHas('citations', [
            'business_id' => $business->id,
            'directory' => 'Google Business Profile',
            'nap_status' => 'consistent',
        ]);

        $mismatch = Citation::where('business_id', $business->id)->where('directory', 'Yelp')->first();
        $this->assertNotNull($mismatch);

        Livewire::actingAs($user)
            ->test(CitationsComponent::class)
            ->call('markResolved', $mismatch->id);

        $this->assertEquals('consistent', $mismatch->fresh()->nap_status);
    }

    public function test_settings_toggle_enables_and_disables_advanced_tools(): void
    {
        [$user, $business] = $this->createTenant(advanced: false);

        $this->assertFalse($business->fresh()->hasAdvancedDashboard());

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('toggleAdvanced')
            ->assertSet('advancedEnabled', true);

        $this->assertTrue($business->fresh()->hasAdvancedDashboard());

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('toggleAdvanced')
            ->assertSet('advancedEnabled', false);

        $this->assertFalse($business->fresh()->hasAdvancedDashboard());
    }

    public function test_all_twelve_advanced_screens_render_successfully(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Google Business Profile',
            'nap_status' => 'consistent',
            'url' => 'https://google.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Yelp',
            'nap_status' => 'consistent',
            'url' => 'https://yelp.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'Bing',
            'nap_status' => 'consistent',
            'url' => 'https://bing.com',
        ]);
        Citation::create([
            'business_id' => $business->id,
            'directory' => 'YellowPages',
            'nap_status' => 'mismatch',
            'url' => 'https://yp.com',
        ]);

        $screens = [
            'advanced.home',
            'advanced.citations',
            'advanced.credits',
            'advanced.broadcasts',
            'advanced.broadcasts.new',
            'advanced.competitors',
            'advanced.reports',
            'advanced.visibility',
            'advanced.segments',
            'advanced.defense',
            'advanced.changes',
            'advanced.settings',
        ];

        foreach ($screens as $screen) {
            $response = $this->actingAs($user)->get(route($screen));
            $response->assertOk();
        }
    }
}
