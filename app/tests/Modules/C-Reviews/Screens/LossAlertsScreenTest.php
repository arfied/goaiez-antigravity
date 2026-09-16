<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Models\ReviewRemovalRequest;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Ui\LossAlerts;
use App\Modules\X181\Models\QaTicket;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class LossAlertsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.loss-alerts'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No customers at risk right now');

        Tenancy::setUser($owner->id);
        $reviewReq = ReviewRequest::create([
            'business_id' => $biz->id,
            'rating' => 1
        ]);
        ReviewRemovalRequest::create([
            'business_id' => $biz->id,
            'review_request_id' => $reviewReq->id,
            'tos_ground' => 'Distinctive ground 4506',
            'prepared_body' => 'Prepared explanation body',
            'google_review_id' => 'google_abc_123',
            'status' => 'prepared'
        ]);
        Tenancy::forget();

        $this->get(route('c-reviews.loss-alerts'))
            ->assertOk()
            ->assertSee('Distinctive ground 4506')
            ->assertSee('Google Review Removal Requests');

        Livewire::test(LossAlerts::class)->assertOk();
    }

    public function test_sample_mode_says_actions_are_off(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $component = Livewire::test(LossAlerts::class)
            ->assertDontSee('actions are off')
            ->call('toggleSample')
            ->assertSee('SAMPLE DATA')
            ->assertSee('actions are off')
            ->call('resolveAndAlert', 1, 'sample note')
            ->assertSee('actions are off');

        $this->assertSame(0, QaTicket::where('business_id', $biz->id)->count());

        $component->call('toggleSample')
            ->assertDontSee('actions are off');
    }
}
