<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Models\QaSetting;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use App\Modules\CReviews\Ui\ReviewsQaRequests;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewsQaRequestsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.reviews-qa-requests'))->assertOk();

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
        if (!\Illuminate\Support\Facades\Schema::connection('pgsql_migrate')->hasColumn('qa_settings', 'ticket_recipient_id')) { \Illuminate\Support\Facades\Schema::connection('pgsql_migrate')->table('qa_settings', function ($table) { $table->foreignId('ticket_recipient_id')->nullable(); }); }
        QaSetting::updateOrCreate(['business_id' => $biz->id], ['ticket_recipient_id' => $person->id]);
        Tenancy::forget();

        $this->get(route('c-reviews.reviews-qa-requests'))
            ->assertOk()
            ->assertSee('Distinctive Person4509');
    }

}
