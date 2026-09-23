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

    public function test_can_propose_plan(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        Livewire::test(ContentWeek::class)
            ->set('weekLabel', 'Week of October 1')
            ->set('itemSourceEvent', 'season.turned')
            ->set('itemTopicTheme', 'Winter prep')
            ->call('proposePlan')
            ->assertSet('error', '')
            ->assertSet('success', 'Added 1 item to the plan for Week of October 1. This feeds the calendar view; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('content_plans', ['week_label' => 'Week of October 1']);
        $this->assertDatabaseHas('plan_items', ['source_event' => 'season.turned', 'topic_theme' => 'Winter prep']);
        Tenancy::forget();

        $this->get(route('x-184.content-week'))
            ->assertOk()
            ->assertSee('Week of October 1')
            ->assertDontSee('No content week planned yet');
    }

    public function test_fan_out_to_calendar_view(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        Livewire::test(ContentWeek::class)
            ->set('weekLabel', 'Week of October 1')
            ->set('itemSourceEvent', 'season.turned')
            ->set('itemTopicTheme', 'Unique Fanout Topic')
            ->call('proposePlan');
        Tenancy::forget();

        $this->get(route('x-184.calendar'))
            ->assertOk()
            ->assertSee('Unique Fanout Topic')
            ->assertDontSee('No content is planned yet');
    }

    public function test_refuses_empty_source_event_and_prevents_orphan(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        Livewire::test(ContentWeek::class)
            ->set('weekLabel', 'Orphan test plan')
            ->set('itemSourceEvent', '')
            ->call('proposePlan')
            ->assertSet('error', 'Every plan item must name its source event so we know what generated it.');

        $this->assertDatabaseMissing('content_plans', ['week_label' => 'Orphan test plan']);
        Tenancy::forget();
    }

    public function test_refuses_empty_week_label(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        Livewire::test(ContentWeek::class)
            ->set('weekLabel', '  ')
            ->set('itemSourceEvent', 'valid.event')
            ->call('proposePlan')
            ->assertSet('error', 'A week label is required to propose a plan.');

        $this->assertDatabaseMissing('plan_items', ['source_event' => 'valid.event']);
        Tenancy::forget();
    }
}
