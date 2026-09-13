<?php

declare(strict_types=1);

namespace Tests\Modules\X181\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X181\Models\QaTicket;
use App\Modules\X181\Ui\QaQueueSlaDueAt;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class QaQueueSlaDueAtScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-181.qa-queue-sladueat'))->assertOk();

        Livewire::test(QaQueueSlaDueAt::class)->assertOk();
    }

    public function test_qa_queue_resolve_refuses_unowned_ticket(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $otherBiz = $this->provisionTenant(['name' => 'Other Biz']);
        $req = ReviewRequest::create(['business_id' => $otherBiz->id, 'rating' => 2]);
        app(QaTicketAction::class)->handle($otherBiz->id, $req->id);
        $ticket = QaTicket::where('business_id', $otherBiz->id)->first();

        try {
            Livewire::test(QaQueueSlaDueAt::class, ['businessId' => $biz->id])
                ->call('resolve', $ticket->id, 'fixed it')
                ->assertDontSee('No query results for model'); // ensure it doesn't just return an error text
            $this->fail('resolve accepted an id this business cannot see.');
        } catch (ModelNotFoundException $e) {
            // propagating renders a 404
        }

        Tenancy::set($otherBiz->id);
        $ticket->refresh();
        $this->assertNull($ticket->resolved_at);
        $this->assertNotEquals('resolved', $ticket->status);
    }
}
