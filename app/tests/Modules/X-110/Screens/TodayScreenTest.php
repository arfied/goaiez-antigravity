<?php

declare(strict_types=1);

namespace Tests\Modules\X110\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X110\Ui\Today;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class TodayScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-110.today'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('<h1 class="sr-only">Worth a minute</h1>', false);

        Livewire::test(Today::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-110.today.admin'))->assertOk();

        Livewire::test(Today::class)->assertOk();
    }

    public function test_can_record_test_visit(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        Livewire::test(Today::class)
            ->set('visitorId', 'vis-999')
            ->call('recordTestVisit')
            ->assertSet('success', 'Recorded visit for visitor vis-999. This feeds the live visitor list; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('visits', [
            'business_id' => $biz->id,
            'visitor_id' => 'vis-999',
        ]);

        $this->assertDatabaseHas('visitor_sessions', [
            'business_id' => $biz->id,
        ]);

        $this->assertDatabaseHas('pixel_events', [
            'business_id' => $biz->id,
            'event_name' => 'page_view',
        ]);

        Tenancy::forget();

        $this->get(route('x-110.today'))
            ->assertOk()
            ->assertDontSee('Pixel not verified');

        Livewire::test(Today::class)->assertViewHas('todayVisitsCount', 1);

        $this->get(route('x-110.visitors-live'))
            ->assertOk()
            ->assertDontSee('Nobody on the site right now');
    }

    public function test_refuses_empty_visitor_id(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        Livewire::test(Today::class)
            ->set('visitorId', '  ')
            ->call('recordTestVisit')
            ->assertSet('error', 'Visitor ID is required.');

        $this->assertDatabaseMissing('visits', [
            'business_id' => $biz->id,
        ]);

        Tenancy::forget();
    }
}
