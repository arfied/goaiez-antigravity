<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\CampaignAudience;
use App\Enums\CampaignKind;
use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Livewire\Advanced\BroadcastComposer;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class BroadcastComposerScreenTest extends TestCase
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

    public function test_get_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.broadcasts.compose'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_the_composers_default_state(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.broadcasts.compose'));
        $response->assertSee('Campaign Title');
        $response->assertDontSee('340 contacts');
        $response->assertDontSee('Summer Customer Check-in');
    }

    public function test_a_staff_user_gets_the_status_measured(): void
    {
        [$owner, $business] = $this->createTenant(advanced: true);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $response = $this->actingAs($staff)->get(route('advanced.broadcasts.compose'));
        $response->assertStatus(403);
    }

    public function test_saving_creates_a_draft_and_sends_nothing(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Livewire::actingAs($user)
            ->test(BroadcastComposer::class)
            ->set('title', 'Distinctive broadcast 7731')
            ->set('body', 'Hi {name}, we have not seen you in a while.')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertRedirect(route('advanced.broadcasts'));

        $c = Campaign::query()->where('name', 'Distinctive broadcast 7731')->firstOrFail();
        $this->assertTrue($c->status === CampaignStatus::Draft);
        $this->assertTrue($c->kind === CampaignKind::Broadcast);
        $this->assertTrue($c->audience === CampaignAudience::Dormant);
        $this->assertTrue($c->body_template === 'Hi {name}, we have not seen you in a while.');
        $this->assertTrue($c->confirmed_at === null);
        $this->assertTrue(CampaignRecipient::query()->count() === 0);
    }

    public function test_an_empty_body_is_refused_and_writes_nothing(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Livewire::actingAs($user)
            ->test(BroadcastComposer::class)
            ->set('title', 'x')
            ->set('body', '')
            ->call('saveDraft')
            ->assertHasErrors(['body']);

        $this->assertTrue(Campaign::query()->count() === 0);
    }

    public function test_the_dormant_count_is_the_real_segment(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Customer::factory()->create(['first_seen_at' => now()->subDays(400)]);
        Customer::factory()->create(['first_seen_at' => now()->subDays(400)]);
        Customer::factory()->create(['first_seen_at' => now()->subDay()]);

        $response = $this->actingAs($user)->get(route('advanced.broadcasts.compose'));
        $response->assertSeeInOrder(['Dormant customers', '2']);
    }

    public function test_another_tenants_draft_is_not_counted_here(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Livewire::actingAs($user)
            ->test(BroadcastComposer::class)
            ->set('title', 'Distinctive broadcast 7731')
            ->set('body', 'Hi {name}, we have not seen you in a while.')
            ->call('saveDraft');

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = Business::provision([
            'owner_user_id' => $ownerB->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        Tenancy::setUser((int) $ownerB->id);
        Tenancy::set((int) $bizB->id);

        $this->assertTrue(Campaign::query()->count() === 0);
    }
}
