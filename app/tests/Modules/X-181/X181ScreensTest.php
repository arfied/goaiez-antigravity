<?php

declare(strict_types=1);

namespace Tests\Modules\X181;

use App\Modules\X181\Models\QaTicket;
use App\Modules\X181\Ui\QaQueueSlaDueAt;
use App\Modules\X181\Ui\Resolution;
use App\Modules\X181\Ui\Ticket;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class X181ScreensTest extends TestCase
{
    protected int $bizId;

    protected function setUp(): void
    {
        parent::setUp();
        $biz = TestCase::provisionTenant(['name' => 'QA Tenant', 'currency' => 'USD']);
        $this->bizId = $biz->id;
        Tenancy::set($this->bizId);
        QaTicket::where('business_id', $this->bizId)->delete();
    }

    public function test_qa_queue_mount_and_empty(): void
    {
        Livewire::test(QaQueueSlaDueAt::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('QA queue')
            ->assertSee('The QA queue is clear');
    }

    public function test_qa_queue_ordering_and_breach(): void
    {
        $ticket1 = QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'Later ticket',
            'arrived_at' => now(),
            'status' => 'open',
            'sla_due_at' => now()->addHours(2),
        ]);
        $ticket2 = QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'Breached ticket',
            'arrived_at' => now(),
            'status' => 'open',
            'sla_due_at' => now()->subHours(2),
        ]);

        Livewire::test(QaQueueSlaDueAt::class, ['businessId' => $this->bizId])
            ->assertSeeInOrder(['Ticket #'.$ticket2->id, 'Ticket #'.$ticket1->id])
            ->assertSee('SLA breached');
    }

    public function test_qa_queue_resolve_action(): void
    {
        $ticket = QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'To resolve',
            'arrived_at' => now(),
            'status' => 'open',
            'sla_due_at' => now()->addHours(1),
        ]);

        Livewire::test(QaQueueSlaDueAt::class, ['businessId' => $this->bizId])
            ->call('resolve', $ticket->id, 'Resolved successfully');

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
    }

    public function test_ticket_mount_and_empty(): void
    {
        Livewire::test(Ticket::class, ['businessId' => $this->bizId, 'ticketId' => 0])
            ->assertOk()
            ->assertSee('Pick a ticket');
    }

    public function test_ticket_shows_seeded_ticket_and_breach(): void
    {
        $ticket = QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'The Seeded Ticket',
            'arrived_at' => now(),
            'status' => 'open',
            'sla_due_at' => now()->subHours(2),
        ]);

        Livewire::test(Ticket::class, ['businessId' => $this->bizId, 'ticketId' => $ticket->id])
            ->assertOk()
            ->assertSee('The Seeded Ticket')
            ->assertSee('SLA breached');
    }

    public function test_ticket_resolve_action(): void
    {
        $ticket = QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'Ticket to Resolve',
            'arrived_at' => now(),
            'status' => 'open',
            'sla_due_at' => now()->addHours(1),
        ]);

        Livewire::test(Ticket::class, ['businessId' => $this->bizId, 'ticketId' => $ticket->id])
            ->set('resolutionNotes', 'fixed')
            ->call('resolve', 'fixed');

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
        $this->assertEquals('fixed', $ticket->resolution_notes);
    }

    public function test_qa_queue_resolve_on_a_resolved_ticket_saves_nothing(): void
    {
        $ticket = QaTicket::create(['business_id' => $this->bizId, 'subject' => 'Already resolved', 'arrived_at' => now(), 'status' => 'resolved', 'sla_due_at' => now()->addHours(1), 'resolved_at' => now()->subHour(), 'resolution_notes' => 'first']);

        Livewire::test(QaQueueSlaDueAt::class, ['businessId' => $this->bizId])
            ->call('resolve', $ticket->id, 'second')
            ->assertSee('Ticket #'.$ticket->id.' is already resolved; nothing was saved.');

        $ticket->refresh();
        $this->assertEquals('first', $ticket->resolution_notes);
    }

    public function test_ticket_resolve_on_a_resolved_ticket_saves_nothing(): void
    {
        $ticket = QaTicket::create(['business_id' => $this->bizId, 'subject' => 'Already resolved', 'arrived_at' => now(), 'status' => 'resolved', 'sla_due_at' => now()->addHours(1), 'resolved_at' => now()->subHour(), 'resolution_notes' => 'first']);

        Livewire::test(Ticket::class, ['businessId' => $this->bizId, 'ticketId' => $ticket->id])
            ->call('resolve', 'second')
            ->assertSee('Ticket #'.$ticket->id.' is already resolved; nothing was saved.');

        $ticket->refresh();
        $this->assertEquals('first', $ticket->resolution_notes);
    }

    public function test_resolution_mount_and_empty(): void
    {
        Livewire::test(Resolution::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('Nothing resolved yet');
    }

    public function test_resolution_lists_resolved_with_sla_pill(): void
    {
        $ticketLate = QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'Late Ticket',
            'arrived_at' => now()->subDays(2),
            'status' => 'resolved',
            'sla_due_at' => now()->subHours(5),
            'resolved_at' => now()->subHours(2),
        ]);

        $ticketOnTime = QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'OnTime Ticket',
            'arrived_at' => now()->subDays(2),
            'status' => 'resolved',
            'sla_due_at' => now()->subHours(1),
            'resolved_at' => now()->subHours(1),
        ]);

        Livewire::test(Resolution::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('SLA met')
            ->assertSee('SLA missed')
            ->assertSeeInOrder(['OnTime Ticket', 'Late Ticket']);
    }

    public function test_resolution_reopen_action(): void
    {
        $ticket = QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'Ticket to Reopen',
            'arrived_at' => now(),
            'status' => 'resolved',
            'sla_due_at' => now()->subHours(1),
            'resolved_at' => now(),
        ]);

        Livewire::test(Resolution::class, ['businessId' => $this->bizId])
            ->call('reopen', $ticket->id);

        $ticket->refresh();
        $this->assertEquals('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_ticket_resolve_refuses_an_absent_ticket_without_disclosing(): void
    {
        try {
            Livewire::test(Ticket::class, ['businessId' => $this->bizId, 'ticketId' => 0])
                ->call('resolve', 'x')
                ->assertDontSee('No query results for model');
            $this->fail('resolve accepted an absent ticket.');
        } catch (ModelNotFoundException $e) {
            // propagating renders a 404
        }

        $this->assertSame(QaTicket::class, $e->getModel());
    }

    public function test_resolution_reopen_refuses_an_absent_ticket_without_disclosing(): void
    {
        try {
            Livewire::test(Resolution::class, ['businessId' => $this->bizId])
                ->call('reopen', 999999)
                ->assertDontSee('No query results for model');
            $this->fail('reopen accepted an absent ticket.');
        } catch (ModelNotFoundException $e) {
            // propagating renders a 404
        }

        $this->assertSame(QaTicket::class, $e->getModel());
    }

    public function test_ticket_sample_state(): void
    {
        Livewire::test(Ticket::class, ['businessId' => $this->bizId, 'ticketId' => 0])
            ->call('toggleSample')
            ->assertOk()
            ->assertSee('Sample poor rating')
            ->assertSee('SLA breached')
            ->assertSee('Rating: 2');
    }

    public function test_resolution_sample_state(): void
    {
        Livewire::test(Resolution::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertOk()
            ->assertSee('SLA met')
            ->assertSee('SLA missed')
            ->assertSee('Awaiting CSAT')
            ->assertDontSee('CSAT 8/10');
    }

    public function test_resolution_shows_awaiting_csat_when_requested(): void
    {
        QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'CSAT Requested Ticket',
            'arrived_at' => now()->subDays(2),
            'status' => 'resolved',
            'resolved_at' => now()->subHours(1),
            'csat_requested_at' => now()->subHours(1),
            'sla_due_at' => now()->addHours(1),
        ]);

        Livewire::test(Resolution::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('Awaiting CSAT');
    }

    public function test_resolution_hides_csat_pill_when_never_requested(): void
    {
        QaTicket::create([
            'business_id' => $this->bizId,
            'subject' => 'No CSAT Ticket',
            'arrived_at' => now()->subDays(2),
            'status' => 'resolved',
            'resolved_at' => now()->subHours(1),
            'csat_requested_at' => null,
            'sla_due_at' => now()->addHours(1),
        ]);

        Livewire::test(Resolution::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertDontSee('Awaiting CSAT');
    }
}
