<?php

declare(strict_types=1);

namespace Tests\Modules\X181\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X181\Models\QaTicket;
use App\Modules\X181\Ui\Ticket;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-181.ticket'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Pick a ticket');

        $review = ReviewRequest::create([
            'business_id' => $biz->id,
            'rating' => 4,
            'platform' => 'google',
        ]);

        $ticket = QaTicket::create([
            'business_id' => $biz->id,
            'review_request_id' => $review->id,
            'subject' => 'Distinctive ticket 4511',
            'description' => 'Distinctive description 4511',
            'status' => 'open',
            'arrived_at' => now(),
            'sla_due_at' => now()->addHours(2),
        ]);

        Livewire::test(Ticket::class, ['ticketId' => $ticket->id])
            ->assertOk()
            ->assertSee('Distinctive ticket 4511')
            ->assertSee('Distinctive description 4511');
    }

    public function test_review_request_branch_is_reached(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $review = ReviewRequest::create([
            'business_id' => $biz->id,
            'rating' => 4,
            'platform' => 'google',
        ]);

        $ticket = QaTicket::create([
            'business_id' => $biz->id,
            'review_request_id' => $review->id,
            'subject' => 'Subject',
            'status' => 'open',
            'arrived_at' => now(),
            'sla_due_at' => now()->addHours(2),
        ]);

        Livewire::test(Ticket::class, ['ticketId' => $ticket->id])
            ->assertOk()
            ->assertSee('Rating: 4');
    }

    public function test_ticket_resolve_refuses_unowned_ticket(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $otherBiz = $this->provisionTenant(['name' => 'Other Biz']);
        $req = ReviewRequest::create(['business_id' => $otherBiz->id, 'rating' => 2]);
        app(QaTicketAction::class)->handle($otherBiz->id, $req->id);
        $ticket = QaTicket::where('business_id', $otherBiz->id)->first();

        try {
            Livewire::test(Ticket::class, ['businessId' => $biz->id, 'ticketId' => $ticket->id])
                ->call('resolve', 'fixed it')
                ->assertDontSee('No query results for model'); // leak shape
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
