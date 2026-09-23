<?php

declare(strict_types=1);

namespace Tests\Modules\X218\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X218\Actions\InfluencerDiscoverAction;
use App\Modules\X218\Models\Deliverable;
use App\Modules\X218\Models\InfluencerDeal;
use App\Modules\X218\Models\InfluencerProfile;
use App\Modules\X218\Ui\DealTracker;
use App\Modules\X218\Ui\DeliverableProof;
use App\Modules\X218\Ui\DiscoveryBoard;
use Livewire\Livewire;
use Tests\TestCase;

class DeliverableProofScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-218.deliverable-proof'))->assertOk();

        Livewire::test(DeliverableProof::class)->assertOk();
    }

    public function test_can_discover_and_create_deal_and_submit_proof(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // 1. DiscoveryBoard
        Livewire::test(DiscoveryBoard::class)
            ->set('handle', 'test_influencer')
            ->call('discover')
            ->assertSet('error', null);

        $influencer = InfluencerProfile::where('handle', 'test_influencer')->firstOrFail();

        // 2. DealTracker
        Livewire::test(DealTracker::class)
            ->set('influencerId', (string) $influencer->id)
            ->set('dealAmountCents', 5000)
            ->call('createDeal')
            ->assertSet('error', null);

        $deal = InfluencerDeal::where('influencer_id', $influencer->id)->firstOrFail();

        // 3. DeliverableProof
        Livewire::test(DeliverableProof::class)
            ->set('dealId', (string) $deal->id)
            ->set('liveUrl', 'https://example.com/proof')
            ->set('httpStatus', 200)
            ->set('artifactHash', 'abc123hash')
            ->call('submitProof')
            ->assertSet('success', function ($value) use ($deal) {
                return str_contains((string) $value, 'Recorded deliverable for deal '.$deal->id) && str_contains((string) $value, 'status was recorded as 200');
            });

        $this->assertDatabaseHas((new Deliverable)->getTable(), [
            'business_id' => $biz->id,
            'deal_id' => $deal->id,
            'live_url' => 'https://example.com/proof',
            'http_status' => 200,
            'artifact_hash' => 'abc123hash',
            'is_verified' => true,
        ]);

        $this->get(route('x-218.deliverable-proof'))
            ->assertOk()
            ->assertSee('https://example.com/proof')
            ->assertSee('abc123hash');

        $this->get(route('x-218.deal-tracker'))
            ->assertOk()
            ->assertSee('Status: delivered');
    }

    public function test_refuses_unknown_deal(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(DeliverableProof::class)
            ->set('dealId', '999999')
            ->set('liveUrl', 'https://example.com/proof')
            ->set('httpStatus', 200)
            ->set('artifactHash', 'abc123hash')
            ->call('submitProof')
            ->assertSet('error', 'Unknown deal.');

        $this->assertDatabaseMissing((new Deliverable)->getTable(), [
            'business_id' => $biz->id,
            'live_url' => 'https://example.com/proof',
        ]);
    }

    public function test_refuses_missing_artifact(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $influencer = app(InfluencerDiscoverAction::class)->discoverInfluencer($biz->id, 'test_handle');

        $deal = InfluencerDeal::create([
            'business_id' => $biz->id,
            'influencer_id' => $influencer->id,
            'deal_amount_cents' => 5000,
            'status' => 'active',
            'is_paid' => false,
        ]);

        Livewire::test(DeliverableProof::class)
            ->set('dealId', (string) $deal->id)
            ->set('liveUrl', 'https://example.com/proof')
            ->set('httpStatus', 200)
            ->set('artifactHash', '')
            ->call('submitProof')
            ->assertSet('error', 'A deliverable needs its proof hash and live URL before it can be verified.');

        $this->assertDatabaseMissing((new Deliverable)->getTable(), [
            'business_id' => $biz->id,
            'deal_id' => $deal->id,
        ]);
    }

    public function test_refuses_non_200_status(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $influencer = app(InfluencerDiscoverAction::class)->discoverInfluencer($biz->id, 'test_handle');

        $deal = InfluencerDeal::create([
            'business_id' => $biz->id,
            'influencer_id' => $influencer->id,
            'deal_amount_cents' => 5000,
            'status' => 'active',
            'is_paid' => false,
        ]);

        Livewire::test(DeliverableProof::class)
            ->set('dealId', (string) $deal->id)
            ->set('liveUrl', 'https://example.com/proof')
            ->set('httpStatus', 404)
            ->set('artifactHash', 'hash123')
            ->call('submitProof')
            ->assertSet('error', 'The live URL must be recorded as HTTP 200; you entered 404.');

        $this->assertDatabaseMissing((new Deliverable)->getTable(), [
            'business_id' => $biz->id,
            'deal_id' => $deal->id,
        ]);
    }
}
