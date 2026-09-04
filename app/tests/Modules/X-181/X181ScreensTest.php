<?php

declare(strict_types=1);

namespace Tests\Modules\X181;

use App\Modules\X181\Models\QaTicket;
use App\Modules\X181\Ui\QaQueueSlaDueAt;
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
            ->assertSee('Currently no tickets in the QA queue.');
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
            ->assertSee('(BREACHED)');
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
}
