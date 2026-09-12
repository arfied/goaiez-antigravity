<?php

declare(strict_types=1);

namespace Tests\Modules\X184\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X184\Actions\PlanProposeAction;
use App\Modules\X184\Ui\ContentWeek;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ContentWeekScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-184.content-week'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No content week planned yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(PlanProposeAction::class)->proposePlan(
            businessId: (int) $biz->id,
            weekLabel: 'Week of September 14',
            postsCadence: 3,
            items: [
                ['channel' => 'facebook', 'topic_theme' => 'Furnace check before the cold', 'source_event' => 'season.turned', 'scheduled_date' => '2026-09-14'],
            ],
        );
        Tenancy::forget();

        $this->get(route('x-184.content-week'))
            ->assertOk()
            ->assertSee('Week of September 14')
            ->assertSee('Furnace check before the cold')
            ->assertSee('Sep 14, 2026')
            ->assertDontSee('No content week planned yet');

        Livewire::test(ContentWeek::class)->assertOk();
    }
}
