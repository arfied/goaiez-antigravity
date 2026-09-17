<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\Meter;
use App\Modules\CBilling\Ui\Mrr;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class MrrScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-billing.mrr'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing on this list yet.')
            ->assertSee('No ledger entries this month.');

        Tenancy::setUser($owner->id);
        Meter::create([
            'business_id' => $biz->id,
            'meter_type' => 'sms_segments',
            'units_used' => 4602,
            'cost_hundredths_cents' => 12340000,
        ]);
        CreditLedgerEntry::create([
            'business_id' => $biz->id,
            'entry_type' => 'topup',
            'amount_hundredths_cents' => 45500000,
            'balance_after_hundredths_cents' => 45500000,
            'reference_id' => 'ref-4602',
            'description' => 'Distinctive grant 4602',
            'created_at' => now(),
        ]);
        Tenancy::forget();

        $this->get(route('c-billing.mrr'))
            ->assertOk()
            ->assertSee('4,602 units')
            ->assertSee('Distinctive grant 4602')
            ->assertSee('4,550.0000')
            ->assertDontSee('No ledger entries this month.');

        Livewire::test(Mrr::class)->assertOk();
    }
}
