<?php

declare(strict_types=1);

namespace Tests\Modules\X184\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X184\Actions\PlanProposeAction;
use App\Modules\X184\Actions\PlanScheduleAction;
use App\Modules\X184\Models\PlanItem;
use App\Modules\X184\Ui\CalendarView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-184.calendar'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No content is planned yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::actingAs((int) $biz->id, function () use ($biz, &$plan) {
            $plan = (new PlanProposeAction)->proposePlan(
                (int) $biz->id,
                '2026-W38',
                3,
                [
                    ['channel' => 'facebook', 'topic_theme' => 'Winter Promo', 'source_event' => 'Promo Launch'],
                    ['channel' => 'email', 'topic_theme' => 'Furnace Tune-Up Reminder', 'source_event' => 'Seasonal'],
                ]
            );
            (new PlanScheduleAction)->scheduleItem(
                (int) $biz->id,
                (int) PlanItem::where('plan_id', $plan->id)->where('channel', 'email')->value('id')
            );
        });

        $this->get(route('x-184.calendar'))
            ->assertSee('Winter Promo')
            ->assertSee('Furnace Tune-Up Reminder')
            ->assertSee('proposed')
            ->assertSee('planned')
            ->assertDontSee('No content is planned yet');

        Livewire::test(CalendarView::class)->assertOk();
    }
}
