<?php

declare(strict_types=1);

namespace Tests\Modules\X168\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Ui\OwnHoursView;
use Livewire\Livewire;
use Tests\TestCase;

class OwnHoursViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-168.own-hours'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No hours yet.');

        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $now = \Carbon\Carbon::parse('2024-01-01 08:00:00');
        app(\App\Modules\X168\Actions\TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz->id,
            personId: $owner->id,
            jobId: 7701,
            stateWindow: 'en_route',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(125),
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        $biz2 = $this->provisionTenant();
        $tech2 = User::factory()->create(['role' => UserRole::Staff, 'name' => 'Bob Tech']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz2->id}'");
        app(\App\Modules\X168\Actions\TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz2->id,
            personId: $tech2->id,
            jobId: 9999,
            stateWindow: 'en_route',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(60),
            locationLat: 0,
            locationLng: 0
        );
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $this->get(route('x-168.own-hours'))
            ->assertOk()
            ->assertSee('2:05')
            ->assertSee('2024-01-01')
            ->assertDontSee('No hours yet.');

        Livewire::test(OwnHoursView::class)->assertOk();
    }
}
