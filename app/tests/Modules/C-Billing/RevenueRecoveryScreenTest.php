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
    public function test_revenue_recovery_counts_what_came_back_since_the_ladder_started(): void
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
            ->assertSee('Day 8 of 21')
            ->assertSee('phone answers')
            ->assertSee('AI on')
            ->assertSee('398.00 a month')
            ->assertSee('recovered 25.00 since the ladder started')
            ->assertDontSee('35.00')
            ->assertDontSee('Day 15 of 21')
            ->assertSeeHtml('wire:click="topupNow('.$state->id.')"')
            ->assertSeeHtml('wire:click="advance('.$state->id.')"')
            ->call('topupNow', $state->id)
            ->assertSee('Topped up 50.00.')
            ->assertSee('recovered 75.00 since the ladder started')
            ->call('advance', $state->id)
            ->assertSee('Day 9 of 21');

        $state->refresh();
        $this->assertSame(9, $state->day_in_cycle);
        $this->assertSame(2, CreditLedgerEntry::where('business_id', $biz->id)->where('entry_type', 'topup')->count());

        $screen->call('advance', 999999)
            ->assertSee("isn't in this account");
    }
}
