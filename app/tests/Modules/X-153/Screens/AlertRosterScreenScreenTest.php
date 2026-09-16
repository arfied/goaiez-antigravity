<?php

declare(strict_types=1);

namespace Tests\Modules\X153\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Ui\AlertRosterScreen;
use Livewire\Livewire;
use Tests\TestCase;

class AlertRosterScreenScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-153.alert-roster-screen'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No alerts broadcasted.');

        Livewire::test(AlertRosterScreen::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-153.alert-roster-screen.admin'))->assertOk();

        Livewire::test(AlertRosterScreen::class)->assertOk();
    }

    /** Prove it shows the tenant's alerts on real route */
    public function test_shows_tenant_alerts(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Alert::create([
            'business_id' => $biz->id,
            'alert_class' => 'urgent',
            'title' => 'Test Roster Alert',
            'body' => 'Body text',
            'status' => 'pending',
        ]);

        $this->actingAs($owner);

        $this->get(route('x-153.alert-roster-screen'))
            ->assertOk()
            ->assertSee('Test Roster Alert');

        Livewire::test(AlertRosterScreen::class)
            ->assertOk()
            ->assertSee('Test Roster Alert');
    }
}
