<?php

declare(strict_types=1);

namespace Tests\Modules\X181\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Models\CsatAnswer;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X121\Models\Person;
use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Modules\X181\Models\QaTicket;
use App\Modules\X181\Ui\Resolution;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class ResolutionScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-181.resolution'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing resolved yet');

        QaTicket::create([
            'business_id' => $biz->id,
            'subject' => 'Distinctive resolved 4504',
            'arrived_at' => now(),
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => 'Distinctive notes 4504',
            'sla_due_at' => now()->addHours(1),
        ]);

        $this->get(route('x-181.resolution'))
            ->assertOk()
            ->assertSee('Distinctive resolved 4504')
            ->assertSee('Distinctive notes 4504')
            ->assertDontSee('Nothing resolved yet');

        Livewire::test(Resolution::class)->assertOk();
    }

    public function test_screen_renders_awaiting_csat_pill(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        QaTicket::create([
            'business_id' => $biz->id,
            'subject' => 'HTTP Pill Test',
            'arrived_at' => now(),
            'status' => 'resolved',
            'resolved_at' => now(),
            'csat_requested_at' => now(),
            'sla_due_at' => now()->addHours(1),
        ]);

        $this->get(route('x-181.resolution'))->assertOk()->assertSee('Awaiting CSAT');
    }

    public function test_resolution_reopen_refuses_unowned_ticket(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $otherBiz = $this->provisionTenant(['name' => 'Other Biz']);
        $req = ReviewRequest::create(['business_id' => $otherBiz->id, 'rating' => 2]);
        app(QaTicketAction::class)->handle($otherBiz->id, $req->id);

        $ticket = QaTicket::where('business_id', $otherBiz->id)->first();
        app(QaTicketResolveAction::class)->handle($otherBiz->id, $ticket->id, 'notes');

        try {
            Livewire::test(Resolution::class, ['businessId' => $biz->id])
                ->call('reopen', $ticket->id)
                ->assertDontSee('No query results for model'); // leak shape
            $this->fail('reopen accepted an id this business cannot see.');
        } catch (ModelNotFoundException $e) {
            // propagating renders a 404
        }

        Tenancy::set($otherBiz->id);
        $ticket->refresh();
        $this->assertNotNull($ticket->resolved_at); // Should remain resolved
        $this->assertEquals('resolved', $ticket->status);
    }

    public function test_the_awaiting_csat_pill_turns_off_once_the_customer_answered(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $p1 = Person::create(['business_id' => $biz->id, 'phone' => '+15555555551']);
        $p2 = Person::create(['business_id' => $biz->id, 'phone' => '+15555555552']);

        $req1 = ReviewRequest::create(['business_id' => $biz->id, 'customer_id' => $p1->id, 'rating' => 2]);
        $req2 = ReviewRequest::create(['business_id' => $biz->id, 'customer_id' => $p2->id, 'rating' => 2]);
        app(QaTicketAction::class)->handle($biz->id, $req1->id);
        app(QaTicketAction::class)->handle($biz->id, $req2->id);

        $t1 = QaTicket::where('business_id', $biz->id)->where('review_request_id', $req1->id)->first();
        $t2 = QaTicket::where('business_id', $biz->id)->where('review_request_id', $req2->id)->first();
        $t1->update(['subject' => 'Distinctive ticket A 4661']);
        $t2->update(['subject' => 'Distinctive ticket B 4662']);

        app(QaTicketResolveAction::class)->handle($biz->id, $t1->id, 'notes');
        app(QaTicketResolveAction::class)->handle($biz->id, $t2->id, 'notes');

        $t1->refresh();
        $t2->refresh();
        $this->assertNotNull($t1->csat_requested_at);
        $this->assertNotNull($t2->csat_requested_at);

        CsatAnswer::query()->create([
            'business_id' => $biz->id,
            'person_id' => $p1->id,
            'qa_ticket_id' => $t1->id,
            'score' => 5,
            'body' => '5',
            'is_valid' => true,
            'received_at' => now(),
        ]);

        Livewire::test(Resolution::class)
            ->assertSeeInOrder(['Distinctive ticket B 4662', 'Awaiting CSAT']);

        $html = Livewire::test(Resolution::class)->html();
        $this->assertEquals(1, substr_count($html, 'Awaiting CSAT'));
    }
}
