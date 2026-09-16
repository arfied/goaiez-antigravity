<?php

declare(strict_types=1);

namespace Tests\Modules\X181\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Models\ReviewRequest;
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
}
