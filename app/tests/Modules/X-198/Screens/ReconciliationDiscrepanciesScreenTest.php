<?php

declare(strict_types=1);

namespace Tests\Modules\X198\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;
use App\Modules\X198\Ui\ReconciliationDiscrepancies;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ReconciliationDiscrepanciesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-198.reconciliation-discrepancies'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No payouts have been imported yet.');

        Tenancy::setUser($owner->id);

        $payout = Payout::create([
            'business_id' => $biz->id,
            'gateway_payout_id' => 'po_distinctive_4643',
            'amount_cents' => 5000,
            'payout_date' => now()->toDateString(),
        ]);

        ReconciliationRun::create([
            'business_id' => $biz->id,
            'payout_id' => $payout->id,
            'expected_cents' => 5000,
            'actual_cents' => 4900,
            'discrepancy_cents' => -100,
            'discrepancy_reason' => 'Distinctive mismatch 4643',
            'status' => 'discrepancy_logged',
        ]);

        Tenancy::forget();

        $this->get(route('x-198.reconciliation-discrepancies'))
            ->assertOk()
            ->assertSee('po_distinctive_4643')
            ->assertSee('Distinctive mismatch 4643')
            ->assertDontSee('Nothing to review');

        Livewire::test(ReconciliationDiscrepancies::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-198.reconciliation-discrepancies.admin'))->assertOk();

        Livewire::test(ReconciliationDiscrepancies::class)->assertOk();
    }
}
