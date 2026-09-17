<?php

declare(strict_types=1);

namespace Tests\Modules\X211\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Ui\InvoiceThreadBeside;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceThreadBesideScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-211.invoice-thread-beside'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing unpaid.');

        Tenancy::setUser($owner->id);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => null,
            'invoice_number' => 'Distinctive INV-4614',
            'total_cents' => 55000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => today()->addDays(7),
        ]);
        ArDunningAction::create([
            'business_id' => $biz->id,
            'invoice_id' => $invoice->id,
            'action' => 'reason_recorded',
            'reason' => 'No reply yet',
        ]);
        Tenancy::forget();

        $this->get(route('x-211.invoice-thread-beside'))
            ->assertOk()
            ->assertSee('Distinctive INV-4614')
            ->assertSee('not yet due')
            ->assertSee('550.00 owed of 550.00')
            ->assertSee('No messages yet.')
            ->assertSee('Why is it unpaid?')
            ->assertSee('No reply yet')
            ->assertDontSee('Nothing unpaid.');

        Livewire::test(InvoiceThreadBeside::class)->assertOk();
    }
}
