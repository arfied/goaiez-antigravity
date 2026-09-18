<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Ui\QaReport;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class QaReportScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.qa-report'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing to report yet');

        Tenancy::setUser($owner->id);

        ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'status' => 'published_public',
            'rating' => 5,
            'review_text' => 'Distinctive review 4508',
            'gbp_suspended' => false,
        ]);

        $this->get(route('c-reviews.qa-report'))
            ->assertOk()
            ->assertSee('data-tile="requests_sent" data-value="1"', false)
            ->assertDontSee('Nothing to report yet');

        Livewire::test(QaReport::class)
            ->call('selectDrilldown', 'requests_sent')
            ->assertSee('Distinctive review 4508');

        Tenancy::forget();
    }
}
