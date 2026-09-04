<?php

declare(strict_types=1);

namespace Tests\Modules\X181;

use App\Modules\X181\Models\QaTicket;
use App\Modules\X181\Ui\QaQueueSlaDueAt;
use App\Modules\X181\Ui\Resolution;
use App\Modules\X181\Ui\Ticket;
use App\Support\Tenancy;
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
            ->assertSee('QA Queue and SLA Due Watch')
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
            ->assertSeeInOrder(['OnTime Ticket', 'Late Ticket']); // newest resolved_at first, they have same resolved_at here. Let's make one newer.
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
}
