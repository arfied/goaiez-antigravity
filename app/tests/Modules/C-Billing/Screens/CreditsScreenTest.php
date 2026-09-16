<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Ui\Credits;
use Livewire\Livewire;
use Tests\TestCase;

class CreditsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-billing.credits'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No ledger entries yet.');

        CreditLedgerEntry::create([
            'business_id' => $biz->id,
            'entry_type' => 'topup',
            'amount_hundredths_cents' => 45500000,
            'balance_after_hundredths_cents' => 45500000,
            'reference_id' => 'ref-123', 'description' => 'Initial grant',
            'created_at' => now()->subDay(),
        ]);

        $this->get(route('c-billing.credits'))
            ->assertOk()
            ->assertSee('Ledger')
            ->assertSee('4,550.0000')
            ->assertDontSee('No ledger entries yet.');

        Livewire::test(Credits::class)->assertOk();
    }
}
