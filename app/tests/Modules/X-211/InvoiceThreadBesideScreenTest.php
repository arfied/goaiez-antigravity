<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\Conversation;
use App\Models\User;
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
        $this->assertSame((int) $bizB->id, (int) $convB->business_id);
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
        $this->assertSame((int) $biz->id, (int) $conv->business_id);
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
            ->assertSee('no reminder sequence and no way to contact anyone')
            ->assertDontSee('will not enter')
            ->assertDontSee('The 50 most recent messages are shown');

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

    public function test_the_thread_keeps_the_newest_messages_and_says_when_it_is_cut(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);
        $conv = Conversation::create(['business_id' => $biz->id, 'person_id' => $customer->id, 'channel' => 'sms', 'status' => 'open']);

        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A1',
            'total_cents' => 45000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(12),
        ]);

        DB::table('messages')->insert(['business_id' => $biz->id, 'conversation_id' => $conv->id, 'sender_type' => 'person', 'body' => 'THE OLDEST MESSAGE IN THIS THREAD', 'direction' => 'inbound', 'created_at' => now()->subMinutes(51)]);
        for ($i = 2; $i <= 50; $i++) {
            DB::table('messages')->insert(['business_id' => $biz->id, 'conversation_id' => $conv->id, 'sender_type' => 'person', 'body' => "Message $i", 'direction' => 'inbound', 'created_at' => now()->subMinutes(52 - $i)]);
        }
        DB::table('messages')->insert(['business_id' => $biz->id, 'conversation_id' => $conv->id, 'sender_type' => 'person', 'body' => 'THE NEWEST MESSAGE IN THIS THREAD', 'direction' => 'inbound', 'created_at' => now()->subMinutes(1)]);

        Livewire::actingAs($owner)->test(InvoiceThreadBeside::class, ['invoiceId' => $invoice->id])
            ->assertOk()
            ->assertSee('THE NEWEST MESSAGE IN THIS THREAD')
            ->assertDontSee('THE OLDEST MESSAGE IN THIS THREAD')
            ->assertSee('The 50 most recent messages are shown');
    }

    public function test_the_thread_screen_says_no_invoice_is_open_and_what_that_waits_on(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(InvoiceThreadBeside::class)
            ->assertOk()
            ->assertSee('Nothing in this checkout raises one from a completed job')
            ->assertSee('and a draft is never issued')
            ->assertDontSee('Every issued invoice is paid');
    }

    public function test_the_thread_names_its_dunning_actions_in_the_owners_words(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A1',
            'total_cents' => 45000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(12),
        ]);

        Livewire::actingAs($owner)->test(InvoiceThreadBeside::class, ['invoiceId' => $invoice->id])
            ->assertOk()
            ->set('reason.'.$invoice->id, 'complaint')
            ->call('recordReason', $invoice->id)
            ->assertSee('flagged for a human')
            ->assertSee('reason recorded')
            ->assertDontSee('escalate_to_human')
            ->assertDontSee('reason_recorded');
    }

    public function test_the_thread_screen_opens_on_a_determined_invoice_when_due_dates_tie(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $invA = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TIE-A',
            'total_cents' => 111100,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(15),
        ]);

        $invB = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TIE-B',
            'total_cents' => 222200,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => $invA->due_date,
        ]);

        Livewire::actingAs($owner)->test(InvoiceThreadBeside::class)
            ->assertOk()
            ->assertSee('1,111.00')
            ->assertDontSee('2,222.00');
    }
}
