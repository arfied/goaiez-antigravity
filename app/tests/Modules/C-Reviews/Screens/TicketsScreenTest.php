<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Ui\Tickets;
use App\Modules\X121\Models\Person;
use App\Modules\X181\Actions\QaTicketCreateAction;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class TicketsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.tickets'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No open tickets');

        Tenancy::setUser($owner->id);
        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'status' => 'triaged_internal',
            'rating' => 2,
            'review_text' => 'Distinctive review 4505',
            'gbp_suspended' => false,
        ]);
        app(QaTicketAction::class)->handle($biz->id, $req->id);
        Tenancy::forget();

        $this->get(route('c-reviews.tickets'))
            ->assertOk()
            ->assertSee('Ticket #')
            ->assertSee('Distinctive review 4505')
            ->assertDontSee('No open tickets');

        Livewire::test(Tickets::class)->assertOk();
    }

    public function test_a_ticket_with_a_person_shows_the_customer_name(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'last_name' => 'Person4509', 'phone' => '+15125554509']);
        app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Triage');
        Tenancy::forget();

        $this->get(route('c-reviews.tickets'))
            ->assertOk()
            ->assertSee('Distinctive Person4509');
    }
}
