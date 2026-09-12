<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling;

use App\Models\Subscription;
use App\Models\User;
use App\Modules\CBilling\Domain\BillingLedgerEngine;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\DunningState;
use App\Modules\CBilling\Ui\RevenueRecovery;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RevenueRecoveryScreenTest extends TestCase
{
    public function test_revenue_recovery_counts_the_credit_added_since_the_ladder_started(): void
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizB->id);
        DunningState::create(['business_id' => $bizB->id, 'day_in_cycle' => 15, 'status' => 'banner', 'ai_enabled' => true, 'phone_answering' => true, 'voicemail_only' => false]);

        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Subscription::where('business_id', $biz->id)->firstOrFail()->forceFill([
            'plan' => 'base', 'term' => 'monthly',
            'price_cents' => 34900, 'additional_location_cents' => 4900, 'additional_locations' => 1, 'price_currency' => 'USD',
        ])->save();

        // A grant from before the ladder — it must not count as recovery.
        CreditLedgerEntry::create([
            'business_id' => $biz->id, 'entry_type' => 'grant',
            'amount_hundredths_cents' => 100000, 'balance_after_hundredths_cents' => 1100000,
            'reference_id' => 'grant_old', 'description' => 'Old grant',
            'created_at' => now()->subDays(3),
        ]);

        $state = DunningState::create(['business_id' => $biz->id, 'day_in_cycle' => 8, 'status' => 'warning', 'ai_enabled' => true, 'phone_answering' => true, 'voicemail_only' => false]);

        $topup = app(BillingLedgerEngine::class)->topup($biz->id, 2500);
        $this->assertSame('charged', $topup['status']);

        Tenancy::forgetUser();
        Livewire::test(RevenueRecovery::class)->assertForbidden();
        Tenancy::setUser($owner->id);

        $screen = Livewire::actingAs($owner)->test(RevenueRecovery::class)
            ->assertOk()
            ->assertSee('One account at a time')
            ->assertSee('no cross-account read path is built in this checkout yet')
            ->assertDontSee('OWNER ACTION')
            ->assertSee('Day 8 of 21')
            ->assertSee('Ladder setting: phone answers')
            ->assertSee('not applied anywhere yet')
            ->assertSee('AI on')
            ->assertSee('398.00 a month')
            ->assertSee('credit of 25.00 added since the ladder started')
            ->assertDontSee('35.00')
            ->assertDontSee('Day 15 of 21')
            ->assertSeeHtml('wire:click="topupNow('.$state->id.')"')
            ->assertSeeHtml('wire:click="advance('.$state->id.')"')
            ->call('topupNow', $state->id)
            ->assertSee('50.00 of credit added to your balance')
            ->assertSee('Nothing was charged: this button grants credit')
            ->assertSee('credit of 75.00 added since the ladder started')
            ->call('advance', $state->id)
            ->assertSee('Day 9 of 21');

        $state->refresh();
        $this->assertSame(9, $state->day_in_cycle);
        $this->assertSame(2, CreditLedgerEntry::where('business_id', $biz->id)->where('entry_type', 'topup')->count());

        $screen->call('advance', 999999)
            ->assertSee("isn't in this account");
    }

    public function test_revenue_recovery_for_tenant_with_no_ledger_entries(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Subscription::where('business_id', $biz->id)->firstOrFail()->forceFill([
            'plan' => 'base', 'term' => 'monthly',
            'price_cents' => 34900, 'additional_location_cents' => 4900, 'additional_locations' => 1, 'price_currency' => 'USD',
        ])->save();

        DunningState::create(['business_id' => $biz->id, 'day_in_cycle' => 8, 'status' => 'warning', 'ai_enabled' => true, 'phone_answering' => true, 'voicemail_only' => false]);

        Livewire::actingAs($owner)->test(RevenueRecovery::class)
            ->assertOk()
            ->assertSee('Credit added')
            ->assertSee('credit of 0.00 added since the ladder started')
            ->assertSee('no payment against the arrears is recorded')
            ->assertDontSee('Came back');
    }

    public function test_revenue_recovery_says_when_no_agreed_price_is_recorded(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        DunningState::create(['business_id' => $biz->id, 'day_in_cycle' => 8, 'status' => 'warning', 'ai_enabled' => true, 'phone_answering' => true, 'voicemail_only' => false]);

        Livewire::actingAs($owner)->test(RevenueRecovery::class)
            ->assertOk()
            ->assertSee('no agreed price recorded on the row')
            ->assertDontSee('3443');
    }

    public function test_the_recovery_screen_says_it_reads_only_its_own_ladder(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(RevenueRecovery::class)
            ->assertOk()
            ->assertSee('retried on a separate schedule that this screen does not read')
            ->assertSee('No ladder on this screen yet.')
            ->assertDontSee('Nothing in this checkout puts one there')
            ->assertDontSee('Nobody is in dunning')
            ->assertDontSee('This account is current');
    }

    public function test_revenue_recovery_names_the_final_stage_and_never_its_raw_token(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        DunningState::create([
            'business_id' => $biz->id,
            'day_in_cycle' => 21,
            'status' => 'ai_off_voicemail_only',
            'ai_enabled' => false,
            'phone_answering' => true,
            'voicemail_only' => true,
        ]);

        Livewire::actingAs($owner)->test(RevenueRecovery::class)
            ->assertOk()
            ->assertSee('final stage')
            ->assertDontSee('ai_off_voicemail_only')
            ->assertSee('Day 21 of 21');
    }
}
