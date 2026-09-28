<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\CampaignKind;
use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Livewire\Advanced\Broadcasts;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class BroadcastsScreenTest extends TestCase
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

    public function test_real_get_assert_ok_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.broadcasts'));
        $response->assertOk();
        $response->assertSee('Skip to content');
        $response->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.broadcasts'));
        $response->assertOk();
        $response->assertSee('No broadcasts yet. Your first campaign appears here after it is drafted.');
    }

    public function test_one_broadcast_row_of_this_tenant_renders_its_distinctive_name(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);
        Campaign::factory()->create([
            'business_id' => $bizA->id,
            'name' => 'Distinctive Broadcast 7719',
        ]);

        [$userB, $bizB] = $this->createTenant(advanced: true);
        Campaign::factory()->create([
            'business_id' => $bizB->id,
            'name' => 'Distinctive Broadcast 7720',
        ]);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        $response = $this->actingAs($userA)->get(route('advanced.broadcasts'));
        $response->assertOk();
        $response->assertSee('Distinctive Broadcast 7719');
        $response->assertDontSee('Distinctive Broadcast 7720');
    }

    public function test_confirming_a_draft_schedules_it_and_enrols_the_dormant_audience(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Customer::factory()->create(['first_seen_at' => now()->subDays(400)]);
        Customer::factory()->create(['first_seen_at' => now()->subDays(400)]);
        Customer::factory()->create(['first_seen_at' => now()->subDay()]);

        $c = Campaign::factory()->create([
            'name' => 'Distinctive Broadcast 7731',
            'body_template' => 'Hi {name}, we have not seen you in a while.',
            'kind' => CampaignKind::Broadcast,
        ]);

        Livewire::actingAs($user)->test(Broadcasts::class)
            ->call('confirm', $c->id)
            ->assertHasNoErrors();

        $c->refresh();
        $this->assertTrue($c->status === CampaignStatus::Scheduled);
        $this->assertNotNull($c->confirmed_at);
        $this->assertSame(2, CampaignRecipient::query()->where('campaign_id', $c->id)->count());
    }

    public function test_a_template_that_cannot_compose_is_refused_and_stays_a_draft(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $c = Campaign::factory()->create([
            'body_template' => 'Book here {link}',
            'kind' => CampaignKind::Broadcast,
        ]);

        Livewire::actingAs($user)->test(Broadcasts::class)
            ->call('confirm', $c->id)
            ->assertHasErrors(['confirm']);

        $c->refresh();
        $this->assertTrue($c->status === CampaignStatus::Draft);
        $this->assertNull($c->confirmed_at);
        $this->assertSame(0, CampaignRecipient::query()->where('campaign_id', $c->id)->count());
    }

    public function test_a_draft_row_shows_the_control_and_a_scheduled_row_does_not(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Campaign::factory()->create(['status' => CampaignStatus::Draft]);
        Campaign::factory()->confirmed()->create();

        $response = $this->actingAs($user)->get(route('advanced.broadcasts'));
        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'Confirm and send'));
    }

    public function test_another_tenants_draft_cannot_be_confirmed_from_here(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);

        [$userB, $bizB] = $this->createTenant(advanced: true);
        $bDraft = Campaign::factory()->create([
            'business_id' => $bizB->id,
            'status' => CampaignStatus::Draft,
        ]);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        try {
            Livewire::actingAs($userA)->test(Broadcasts::class)
                ->call('confirm', $bDraft->id)
                ->assertHasErrors(['confirm']);
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }

        Tenancy::setUser((int) $userB->id);
        Tenancy::set((int) $bizB->id);

        $bDraft->refresh();
        $this->assertTrue($bDraft->status === CampaignStatus::Draft);
        $this->assertSame(0, CampaignRecipient::query()->where('campaign_id', $bDraft->id)->count());
    }
}
