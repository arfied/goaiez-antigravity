<?php

declare(strict_types=1);

namespace Tests\Modules\X170\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X170\Models\Scorecard;
use App\Modules\X170\Ui\ScorecardUi;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ScorecardUiScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-170.scorecard'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No scorecard records.');

        Tenancy::setUser($owner->id);
        Scorecard::create([
            'business_id' => $biz->id,
            'staff_id' => 10,
            'period_key' => '2026-Q3-distinctive-4480',
            'revenue_collected_cents' => 500000,
            'commissions_earned_cents' => 123456,
            'average_rating' => 4.80,
        ]);
        Tenancy::forget();

        $this->get(route('x-170.scorecard'))
            ->assertOk()
            ->assertSee('Staff 10')
            ->assertSee('2026-Q3-distinctive-4480')
            ->assertSee('1,234.56')
            ->assertDontSee('No scorecard records.');

        Livewire::test(ScorecardUi::class)->assertOk();
    }
}
