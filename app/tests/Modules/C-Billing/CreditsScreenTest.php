<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling;

use App\Models\User;
use App\Modules\CBilling\Domain\BillingLedgerEngine;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\Meter;
use App\Modules\CBilling\Models\TrialLimit;
use App\Modules\CBilling\Ui\Credits;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CreditsScreenTest extends TestCase
{
    public function test_credits_screen(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        $otherBiz = self::provisionTenant();

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $types = ['sms', 'voice', 'ai', 'email', 'lead'];
        foreach ($types as $i => $type) {
            Meter::create([
                'business_id' => $biz->id,
                'meter_type' => $type,
                'units_used' => 100 + $i,
                'cost_hundredths_cents' => 50000 + ($i * 1000),
            ]);
        }

        $entryA1 = CreditLedgerEntry::create([
            'business_id' => $biz->id,
            'entry_type' => 'grant',
            'amount_hundredths_cents' => 500000,
            'balance_after_hundredths_cents' => 500000,
            'reference_id' => 'ref-123', 'description' => 'Initial grant',
            'created_at' => now()->subDay(),
        ]);

        $entryA2 = CreditLedgerEntry::create([
            'business_id' => $biz->id,
            'entry_type' => 'debit',
            'amount_hundredths_cents' => 10000,
            'balance_after_hundredths_cents' => 490000,
            'reference_id' => 'ref-123', 'description' => 'Used AI tokens',
            'created_at' => now(),
        ]);

        Tenancy::set($otherBiz->id);
        Meter::create([
            'business_id' => $otherBiz->id,
            'meter_type' => 'sms',
            'units_used' => 999,
            'cost_hundredths_cents' => 999000,
        ]);

        $entryB = CreditLedgerEntry::create([
            'business_id' => $otherBiz->id,
            'entry_type' => 'grant',
            'amount_hundredths_cents' => 8880000,
            'balance_after_hundredths_cents' => 8880000,
            'reference_id' => 'ref-123', 'description' => 'Other biz grant',
            'created_at' => now(),
        ]);

        Tenancy::set($biz->id);
        Tenancy::forgetUser();
        Livewire::test(Credits::class)
            ->assertForbidden();

        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(Credits::class)
            ->assertOk()
            ->assertSee('49.0000') // credit balance
            ->assertSeeInOrder(['Credit balance', '49.0000', 'Ledger'])
            ->assertDontSee('AI Credits')
            ->assertSee('1.0000')
            ->assertDontSee('-1.0000')
            ->assertSeeInOrder(['SMS Segments', '100', 'Cost: 5.0000'])
            ->assertDontSee('888.0000') // entry B
            ->assertDontSee('99.9000') // other tenant's meter cost
            ->call('explain', $entryA2->id)
            ->assertSee('Used AI tokens')
            ->assertSee('Explanation')
            ->call('explain', 999999)
            ->assertSee("isn't in this account");
    }

    public function test_credits_screen_says_no_usage_is_metered_when_no_meter_row_exists(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        CreditLedgerEntry::create([
            'business_id' => $biz->id,
            'entry_type' => 'grant',
            'amount_hundredths_cents' => 500000,
            'balance_after_hundredths_cents' => 500000,
            'reference_id' => 'ref-123', 'description' => 'Initial grant',
            'created_at' => now()->subDay(),
        ]);

        Livewire::actingAs($owner)->test(Credits::class)
            ->assertSee('Nothing in this checkout writes a usage meter')
            ->assertDontSee('SMS Segments');
    }

    public function test_the_credits_ledger_empty_state_names_what_it_waits_on(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(Credits::class)
            ->assertOk()
            ->assertSee('Nothing in this checkout raises a debit or a grant, so usage charges and plan credits appear once they are built.')
            ->assertDontSee('A grant, a top-up or a debit writes a line here');
    }

    public function test_credits_topup_refuses_at_the_daily_ceiling(): void
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

        $lw = Livewire::actingAs($owner)->test(Credits::class)
            ->call('topup')
            ->assertSee('Nothing was charged: this button grants credit');

        $entry = CreditLedgerEntry::where('business_id', $biz->id)
            ->where('entry_type', 'topup')->first();

        $lw->call('explain', $entry->id)
            ->assertSee('Automatic balance top-up')
            ->call('topup')
            ->assertSee('would pass the daily top-up ceiling on this account')
            ->assertDontSee('Nothing was charged: this button grants credit');

        $this->assertEquals(
            1,
            CreditLedgerEntry::where('business_id', $biz->id)
                ->where('entry_type', 'topup')
                ->count()
        );
    }

    public function test_a_debit_written_by_the_engine_is_flagged_on_the_credits_ledger()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $engine = app(BillingLedgerEngine::class);
        $engine->grant($biz->id, 250000, 'grant_welcome', 'Welcome credit');
        $engine->debit($biz->id, 12000, 'call_1', 'AI call, 3 minutes');

        Livewire::actingAs($owner)->test(Credits::class)
            ->assertOk()
            ->assertSee('debit')
            ->assertSeeHtml('bg-attention-bg');
    }
}
