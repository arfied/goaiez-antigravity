<?php

declare(strict_types=1);

namespace Tests\Modules\X211\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Ui\AgeingByReason;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AgeingByReasonScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-211.ageing-by-reason'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing is overdue.');

        Tenancy::setUser($owner->id);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => null,
            'invoice_number' => 'Distinctive INV-4611',
            'total_cents' => 42000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => today()->subDays(14),
        ]);
        ArDunningAction::create([
            'business_id' => $biz->id,
            'invoice_id' => $invoice->id,
            'action' => 'reason_recorded',
            'reason' => 'Customer promised to pay',
        ]);
        Tenancy::forget();

        $this->get(route('x-211.ageing-by-reason'))
            ->assertOk()
            ->assertSee('Distinctive INV-4611')
            ->assertSee('Customer promised to pay')
            ->assertSee('14 days overdue')
            ->assertSee('420.00')
            ->assertDontSee('Nothing is overdue.');

        Livewire::test(AgeingByReason::class)->assertOk();
    }
}
