<?php

declare(strict_types=1);

namespace Tests\Modules\X181\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X181\Ui\Ticket;
use App\Modules\X181\Models\QaTicket;
use App\Modules\CReviews\Models\ReviewRequest;
use Livewire\Livewire;
use Tests\TestCase;

class TicketScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-181.ticket'))->assertOk();

        Livewire::test(Ticket::class)->assertOk();
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
        ]);

        Livewire::test(Ticket::class, ['ticketId' => $ticket->id])
            ->assertOk()
            ->assertSee('Rating: 4');
    }
}
