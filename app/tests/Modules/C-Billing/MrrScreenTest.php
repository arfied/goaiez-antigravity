<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\CBilling\Domain\BillingLedgerEngine;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\Meter;
use App\Modules\CBilling\Models\TrialLimit;
use App\Modules\CBilling\Ui\Mrr;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MrrScreenTest extends TestCase
{
    public function test_mrr_shows_the_agreed_row_this_account_contributes_meters_and_this_months_ledger(): void
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizB->id);
        Subscription::where('business_id', $bizB->id)->firstOrFail()->forceFill([
            'plan' => 'base', 'term' => 'annual',
            'price_cents' => 238800, 'additional_location_cents' => 0, 'additional_locations' => 0, 'price_currency' => 'USD',
        ])->save();

        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Subscription::where('business_id', $biz->id)->firstOrFail()->forceFill([
            'plan' => 'base', 'term' => 'annual',
            'price_cents' => 418800, 'additional_location_cents' => 58800, 'additional_locations' => 1, 'price_currency' => 'USD',
            'current_period_end' => now()->addMonths(3),
        ])->save();

        Meter::create(['business_id' => $biz->id, 'meter_type' => 'sms_segments', 'units_used' => 42, 'cost_hundredths_cents' => 8400]);

        $engine = app(BillingLedgerEngine::class);
        $grant = $engine->grant($biz->id, 250000, 'grant_welcome', 'Welcome credit');
        $engine->debit($biz->id, 12000, 'call_1', 'AI call, 3 minutes');

        Tenancy::forgetUser();
        Livewire::test(Mrr::class)->assertForbidden();
        Tenancy::setUser($owner->id);

        $screen = Livewire::actingAs($owner)->test(Mrr::class)
            ->assertOk()
            ->assertSee('One account at a time')
            ->assertSee('no cross-account read path is built in this checkout yet')
            ->assertDontSee('OWNER ACTION')
            ->assertSee('398.00 a month')
            ->assertSee('billed yearly, shown as a twelfth')
            ->assertSee('includes 1 extra location')
            ->assertDontSee('199.00')
            ->assertSee('SMS Segments')
            ->assertDontSee('sms_segments')
            ->assertSee('42 units')
            ->assertSee('Welcome credit')
            ->assertSee('AI call, 3 minutes')
            ->assertSeeHtml('wire:click="explain('.$grant->id.')"')
            ->call('explain', $grant->id)
            ->assertSet('explainedEntryId', $grant->id)
            ->assertSee('ref grant_welcome')
            ->assertSee('Add 50.00 credit')
            ->call('topup')
            ->assertSee('50.00 added to the top-up ledger. It is separate from the credit your plan includes and the credit you buy.')
            ->assertDontSee('added to your balance')
            ->assertSee('Nothing was charged: this button grants credit');

        $this->assertSame(1, CreditLedgerEntry::where('business_id', $biz->id)->where('entry_type', 'topup')->count());
        $this->assertSame(0, CreditLedgerEntry::where('business_id', $bizB->id)->count());

        $screen->call('explain', 999999)
            ->assertSee("isn't in this account");
    }

    public function test_the_mrr_empty_states_name_what_they_wait_on(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(Mrr::class)
            ->assertOk()
            ->assertSee('Calls and messages are recorded elsewhere in this app, and this list does not read that record.')
            ->assertSee('Nothing on this list yet.')
            ->assertSee('Usage charges and plan credits are recorded on a separate credit ledger elsewhere in this app, and this list does not read it.')
            ->assertDontSee('writes a usage meter')
            ->assertDontSee('raises a debit or a grant')
            ->assertDontSee('Nothing metered yet')
            ->assertDontSee('The five meters fill')
            ->assertSee('No agreed price is recorded on this subscription row')
            ->assertDontSee('3443');
    }

    public function test_the_mrr_top_up_refuses_over_the_daily_ceiling(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);
        TrialLimit::create([
            'business_id' => $biz->id,
            'daily_topup_ceiling_cents' => 5000,
            'topups_today_cents' => 0,
            'current_balance_hundredths_cents' => 0,
        ]);

        Livewire::actingAs($owner)->test(Mrr::class)
            ->call('topup')
            ->call('topup')
            ->assertSee('would pass the daily top-up ceiling on this account');
    }

    public function test_mrr_names_the_subscription_state_in_the_owners_words_and_never_the_enum_token()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Subscription::where('business_id', $biz->id)->firstOrFail()->forceFill([
            'status' => SubscriptionStatus::PendingCheckout,
        ])->save();

        Livewire::actingAs($owner)->test(Mrr::class)
            ->assertOk()
            ->assertSee('checkout not completed')
            ->assertDontSee('pending_checkout');
    }

    public function test_ledger_entries_take_the_app_clock_so_a_month_reads_its_own_entries(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        // A month this suite always runs after, so the database clock can never land inside it.
        $pin = Carbon::parse('2026-03-15 10:00:00');
        Carbon::setTestNow($pin);

        $engine = app(BillingLedgerEngine::class);
        $grant = $engine->grant($biz->id, 250000, 'grant_march', 'March plan credit');
        $debit = $engine->debit($biz->id, 12000, 'call_march', 'March AI call');
        $topup = $engine->topup($biz->id, 5000);

        Livewire::actingAs($owner)->test(Mrr::class)
            ->assertOk()
            ->assertSee('March plan credit')
            ->assertSee('March AI call')
            ->assertSee('Automatic balance top-up');

        Carbon::setTestNow($pin->copy()->addMonth());

        Livewire::actingAs($owner)->test(Mrr::class)
            ->assertOk()
            ->assertDontSee('March plan credit')
            ->assertDontSee('March AI call')
            ->assertDontSee('Automatic balance top-up');

        foreach ([$grant->id, $debit->id, $topup['entry_id']] as $entryId) {
            $this->assertSame('2026-03-15 10:00:00', CreditLedgerEntry::findOrFail($entryId)->created_at->toDateTimeString());
        }

        Carbon::setTestNow();
    }
}
