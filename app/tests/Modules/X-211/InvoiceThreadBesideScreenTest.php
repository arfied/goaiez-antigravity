<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X121\Models\Conversation;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Modules\X211\Events\ArEscalatedToHuman;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\ReceivableState;
use App\Modules\X211\Ui\InvoiceThreadBeside;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceThreadBesideScreenTest extends TestCase
{
    public function test_invoice_thread_beside_shows_the_thread_and_escalates_a_complaint(): void
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizB->id);
        $personB = Person::create(['business_id' => $bizB->id, 'first_name' => 'B', 'last_name' => 'B']);
        $convB = Conversation::create(['business_id' => $bizB->id, 'person_id' => $personB->id, 'channel' => 'sms', 'status' => 'open']);
        DB::table('messages')->insertGetId(['business_id' => $bizB->id, 'conversation_id' => $convB->id, 'sender_type' => 'person', 'body' => 'B says hello', 'direction' => 'inbound', 'created_at' => now()]);
        Invoice::create([
            'business_id' => $bizB->id,
            'customer_id' => $personB->id,
            'invoice_number' => 'INV-B1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(30),
        ]);

        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);
        $conv = Conversation::create(['business_id' => $biz->id, 'person_id' => $customer->id, 'channel' => 'sms', 'status' => 'open']);
        DB::table('messages')->insertGetId(['business_id' => $biz->id, 'conversation_id' => $conv->id, 'sender_type' => 'person', 'body' => 'The install left a scratch on the floor and nobody called back', 'direction' => 'inbound', 'created_at' => now()]);
        DB::table('messages')->insertGetId(['business_id' => $biz->id, 'conversation_id' => $conv->id, 'sender_type' => 'user', 'body' => 'Sorry about that, we will call today', 'direction' => 'outbound', 'created_at' => now()]);

        $inv1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A1',
            'total_cents' => 45000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(12),
        ]);
        InvoiceLine::create(['business_id' => $biz->id, 'invoice_id' => $inv1->id, 'description' => 'Floor install', 'quantity' => 1, 'unit_price_cents' => 45000, 'subtotal_cents' => 45000]);

        $inv2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A2',
            'total_cents' => 5000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(2),
        ]);

        Event::fake([ArEscalatedToHuman::class]);

        $screen = Livewire::actingAs($owner)->test(InvoiceThreadBeside::class, ['invoiceId' => $inv1->id])
            ->assertOk()
            ->assertSee('INV-A1')
            ->assertSee('Floor install')
            ->assertSee('John Doe')
            ->assertSee('scratch on the floor')
            ->assertSee('12 days overdue')
            ->assertDontSee('B says hello')
            ->assertDontSee('INV-B1')
            ->assertDontSee('needs a human')
            ->assertSeeHtml('wire:submit="recordReason('.$inv1->id.')"')
            ->set('reason.'.$inv1->id, 'complaint')
            ->call('recordReason', $inv1->id)
            ->assertSee('Recorded: Complaint on the thread')
            ->assertSee('needs a human')
            ->assertSee('will not enter a reminder sequence');

        $this->assertSame(1, ArDunningAction::where('business_id', $biz->id)->where('invoice_id', $inv1->id)->where('action', 'reason_recorded')->count());
        $this->assertSame(1, ArDunningAction::where('business_id', $biz->id)->where('invoice_id', $inv1->id)->where('action', 'escalate_to_human')->count());
        $this->assertSame('escalated', ReceivableState::where('business_id', $biz->id)->where('invoice_id', $inv1->id)->value('status'));
        Event::assertDispatchedTimes(ArEscalatedToHuman::class, 1);

        $screen->call('pick', $inv2->id)
            ->assertSee('INV-A2')
            ->assertDontSee('needs a human')
            ->set('reason.'.$inv2->id, 'silence')
            ->call('recordReason', $inv2->id)
            ->assertSee('Recorded: No reply yet')
            ->assertDontSee('needs a human');

        $this->assertSame(0, ArDunningAction::where('business_id', $biz->id)->where('invoice_id', $inv2->id)->where('action', 'escalate_to_human')->count());
        Event::assertDispatchedTimes(ArEscalatedToHuman::class, 1);

        $screen->set('reason.'.$inv2->id, 'bogus')
            ->call('recordReason', $inv2->id)
            ->assertSee('Pick one of the reasons');

        Livewire::actingAs($owner)->test(InvoiceThreadBeside::class)
            ->assertSee('INV-A1')
            ->set('reason.999999', 'silence')
            ->call('recordReason', 999999)
            ->assertSee("isn't in this account");
    }
}
