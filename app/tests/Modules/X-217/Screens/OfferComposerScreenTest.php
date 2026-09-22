<?php

declare(strict_types=1);

namespace Tests\Modules\X217\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X217\Domain\RecruitmentGuard;
use App\Modules\X217\Models\AffiliateProspect;
use App\Modules\X217\Models\RecruitmentOffer;
use App\Modules\X217\Ui\OfferComposer;
use App\Modules\X217\Ui\RecruitPipeline;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class OfferComposerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-217.offer-composer'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

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

    public function test_can_accept_an_offer(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(RecruitPipeline::class)
            ->set('partnerName', 'Kestrel Fabrication 4217')
            ->set('email', 'kestrel4217@example.test')
            ->call('recruitProspect');

        $prospect = AffiliateProspect::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(OfferComposer::class)
            ->set('prospectId', (string) $prospect->id)
            ->set('offeredRateBps', '1737')
            ->call('makeOffer');

        $offer = RecruitmentOffer::where('business_id', $biz->id)->firstOrFail();

        $success = Livewire::test(OfferComposer::class)
            ->call('acceptOffer', $offer->id)
            ->assertSet('error', '')
            ->get('success');

        $this->assertStringContainsString('is now an affiliate under code PARTNER-', $success);
        $this->assertStringContainsString('at 1737 bps — the exact rate that was offered', $success);

        $this->assertDatabaseHas('recruitment_offers', ['id' => $offer->id, 'is_accepted' => true]);
        $this->assertDatabaseHas('affiliate_prospects', ['id' => $prospect->id, 'stage' => 'accepted']);
    }

    public function test_the_accepted_partner_appears_in_the_x_205_affiliate_list_at_the_offered_rate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(RecruitPipeline::class)
            ->set('partnerName', 'Kestrel Fabrication 4217')
            ->set('email', 'kestrel4217@example.test')
            ->call('recruitProspect');

        $prospect = AffiliateProspect::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(OfferComposer::class)
            ->set('prospectId', (string) $prospect->id)
            ->set('offeredRateBps', '1737')
            ->call('makeOffer');

        $offer = RecruitmentOffer::where('business_id', $biz->id)->firstOrFail();

        Tenancy::forget();

        $this->get(route('x-205.portal'))->assertOk()
            ->assertSee('No affiliates yet.')
            ->assertDontSee('Kestrel Fabrication 4217');

        Tenancy::set((int) $biz->id);
        Livewire::test(OfferComposer::class)->call('acceptOffer', $offer->id);
        Tenancy::forget();

        $this->get(route('x-205.portal'))->assertOk()
            ->assertSee('Kestrel Fabrication 4217')
            ->assertSee('1737 bps')
            ->assertDontSee('No affiliates yet.');
    }

    public function test_the_offer_reads_accepted_and_the_button_goes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(RecruitPipeline::class)
            ->set('partnerName', 'Kestrel Fabrication 4217')
            ->set('email', 'kestrel4217@example.test')
            ->call('recruitProspect');

        $prospect = AffiliateProspect::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(OfferComposer::class)
            ->set('prospectId', (string) $prospect->id)
            ->set('offeredRateBps', '1737')
            ->call('makeOffer');

        $offer = RecruitmentOffer::where('business_id', $biz->id)->firstOrFail();

        Tenancy::forget();

        $this->get(route('x-217.offer-composer'))
            ->assertSee('- open')
            ->assertDontSee('- accepted')
            ->assertSee('acceptOffer(');

        Tenancy::set((int) $biz->id);
        Livewire::test(OfferComposer::class)->call('acceptOffer', $offer->id);
        Tenancy::forget();

        $this->get(route('x-217.offer-composer'))
            ->assertSee('- accepted')
            ->assertDontSee('- open')
            ->assertDontSee('acceptOffer(');

        $this->get(route('x-217.recruit-pipeline'))
            ->assertSee('Kestrel Fabrication 4217 - kestrel4217@example.test - accepted');
    }

    public function test_accepting_the_same_offer_twice_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(RecruitPipeline::class)
            ->set('partnerName', 'Kestrel Fabrication 4217')
            ->set('email', 'kestrel4217@example.test')
            ->call('recruitProspect');

        $prospect = AffiliateProspect::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(OfferComposer::class)
            ->set('prospectId', (string) $prospect->id)
            ->set('offeredRateBps', '1737')
            ->call('makeOffer');

        $offer = RecruitmentOffer::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(OfferComposer::class)->call('acceptOffer', $offer->id);

        Livewire::test(OfferComposer::class)
            ->call('acceptOffer', $offer->id)
            ->assertSet('error', 'That offer was already accepted, so nothing was done. Accepting it twice would create a second affiliate for the same partner.');

        $this->assertSame(1, Affiliate::where('business_id', $biz->id)->count());
    }

    public function test_accepting_another_tenants_offer_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);

        Livewire::test(RecruitPipeline::class)
            ->set('partnerName', 'Kestrel Fabrication 4217')
            ->set('email', 'kestrel4217@example.test')
            ->call('recruitProspect');

        $prospect = AffiliateProspect::where('business_id', $bizB->id)->firstOrFail();

        Livewire::test(OfferComposer::class)
            ->set('prospectId', (string) $prospect->id)
            ->set('offeredRateBps', '1737')
            ->call('makeOffer');

        $offerB = RecruitmentOffer::where('business_id', $bizB->id)->firstOrFail();

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);
        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(OfferComposer::class)->call('acceptOffer', $offerB->id);
    }
}
