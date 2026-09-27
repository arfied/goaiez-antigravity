<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Models\QaSetting;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Ui\ReviewsQaRequests;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X121\Models\Person;
use App\Modules\X181\Actions\QaTicketCreateAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewsQaRequestsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.reviews-qa-requests'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No review requests yet');

        Tenancy::setUser($owner->id);
        ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'status' => 'published_public',
            'rating' => 5,
            'review_text' => 'Distinctive review 4510',
            'gbp_suspended' => false,
        ]);
        Tenancy::forget();

        $this->get(route('c-reviews.reviews-qa-requests'))
            ->assertOk()
            ->assertSee('Distinctive review 4510')
            ->assertDontSee('No review requests yet');

        Livewire::test(ReviewsQaRequests::class)->assertOk();
    }

    public function test_a_request_with_a_customer_shows_the_customer_name(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'last_name' => 'Person4509', 'phone' => '+15125554509']);
        ReviewRequest::create([
            'business_id' => $biz->id,
            'customer_id' => $person->id,
            'rating' => 5,
            'review_text' => 'Distinctive review 4509',
            'status' => 'published_public',
        ]);
        Tenancy::forget();

        $this->get(route('c-reviews.reviews-qa-requests'))
            ->assertOk()
            ->assertSee('Distinctive Person4509');
    }

    public function test_the_ticket_recipient_shows_by_name(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'last_name' => 'Person4509', 'phone' => '+15125554509']);
        if (! Schema::connection('pgsql_migrate')->hasColumn('qa_settings', 'ticket_recipient_id')) {
            Schema::connection('pgsql_migrate')->table('qa_settings', function ($table) {
                $table->foreignId('ticket_recipient_id')->nullable();
            });
        }
        QaSetting::updateOrCreate(['business_id' => $biz->id], ['ticket_recipient_id' => $person->id]);
        Tenancy::forget();

        $this->get(route('c-reviews.reviews-qa-requests'))
            ->assertOk()
            ->assertSee('Distinctive Person4509');
    }

    public function test_resend_ask_under_qa_suppression_says_nothing_was_sent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'last_name' => 'Person4510', 'phone' => '+15125554510']);
        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'customer_id' => $person->id,
            'status' => 'sent',
            'rating' => null,
            'platform' => 'google',
        ]);

        app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Triage');
        Tenancy::forget();

        Event::fake([SendRequested::class]);

        $component = Livewire::actingAs($owner)->test(ReviewsQaRequests::class, ['businessId' => $biz->id])->call('resendAsk', $req->id);

        $component->assertSet('noticeType', 'warning');
        $actionNotice = $component->get('actionNotice');
        $this->assertStringContainsString('Not sent', $actionNotice);
        $this->assertStringNotContainsString('resent', $actionNotice);

        Event::assertNotDispatched(SendRequested::class);
        $this->assertTrue(ReviewRequest::where('customer_id', $person->id)->where('status', 'suppressed')->exists());
    }
}
