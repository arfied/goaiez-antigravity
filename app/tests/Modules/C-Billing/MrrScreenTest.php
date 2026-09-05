<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling;

use App\Models\Subscription;
use App\Models\User;
use App\Modules\CBilling\Domain\BillingLedgerEngine;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\Meter;
use App\Modules\CBilling\Ui\Mrr;
use App\Support\Tenancy;
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
            ->assertSee('398.00 a month')
            ->assertSee('billed yearly, shown as a twelfth')
            ->assertSee('includes 1 extra location')
            ->assertDontSee('199.00')
            ->assertSee('sms_segments')
            ->assertSee('42 units')
            ->assertSee('Welcome credit')
            ->assertSee('AI call, 3 minutes')
            ->assertSeeHtml('wire:click="explain('.$grant->id.')"')
            ->call('explain', $grant->id)
            ->assertSet('explainedEntryId', $grant->id)
            ->assertSee('ref grant_welcome')
            ->call('topup')
            ->assertSee('Topped up 50.00.');

        $this->assertSame(1, CreditLedgerEntry::where('business_id', $biz->id)->where('entry_type', 'topup')->count());
        $this->assertSame(0, CreditLedgerEntry::where('business_id', $bizB->id)->count());

        $screen->call('explain', 999999)
            ->assertSee("isn't in this account");
    }
}
