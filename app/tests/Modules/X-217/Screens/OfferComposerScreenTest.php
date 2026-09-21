<?php

declare(strict_types=1);

namespace Tests\Modules\X217\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X217\Domain\RecruitmentGuard;
use App\Modules\X217\Models\AffiliateProspect;
use App\Modules\X217\Models\RecruitmentOffer;
use App\Modules\X217\Ui\OfferComposer;
use Livewire\Livewire;
use Tests\TestCase;

class OfferComposerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-217.offer-composer'))->assertOk();

        Livewire::test(OfferComposer::class)->assertOk();
    }

    public function test_can_make_offer(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $prospect = AffiliateProspect::create([
            'business_id' => $biz->id,
            'partner_name' => 'Partner XYZ',
            'email' => 'partner@example.com',
            'stage' => 'prospecting',
        ]);

        Livewire::test(OfferComposer::class)
            ->set('prospectId', (string) $prospect->id)
            ->set('offeredRateBps', '1500')
            ->call('makeOffer')
            ->assertSet('success', 'Made terms offer to prospect '.$prospect->id.'. This feeds the pipeline; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new RecruitmentOffer)->getTable(), [
            'business_id' => $biz->id,
            'prospect_id' => $prospect->id,
            'offered_rate_bps' => 1500,
        ]);

        $this->get(route('x-217.offer-composer'))->assertSee('Partner XYZ');
        $this->get(route('x-217.recruit-pipeline'))->assertSee('negotiating');
    }

    public function test_refuses_empty_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(OfferComposer::class)
            ->call('makeOffer')
            ->assertSet('error', 'Prospect ID and offered rate are required.');
    }

    public function test_refuses_unknown_prospect(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(OfferComposer::class)
            ->set('prospectId', '999')
            ->set('offeredRateBps', '1500')
            ->call('makeOffer')
            ->assertSet('error', 'That prospect was not found in your pipeline.');

        $this->assertDatabaseMissing((new RecruitmentOffer)->getTable(), [
            'prospect_id' => 999,
        ]);
    }

    public function test_refuses_rate_above_ceiling(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $prospect = AffiliateProspect::create([
            'business_id' => $biz->id,
            'partner_name' => 'Partner XYZ',
            'email' => 'partner@example.com',
            'stage' => 'prospecting',
        ]);

        Livewire::test(OfferComposer::class)
            ->set('prospectId', (string) $prospect->id)
            ->set('offeredRateBps', '3000') // Above 2500 ceiling
            ->call('makeOffer')
            ->assertSet('error', 'That rate is above the '.RecruitmentGuard::DEFAULT_MAX_CEILING_BPS.' bps ceiling, so no offer was made.');

        $this->assertDatabaseMissing((new RecruitmentOffer)->getTable(), [
            'business_id' => $biz->id,
            'prospect_id' => $prospect->id,
            'offered_rate_bps' => 3000,
        ]);
    }
}
