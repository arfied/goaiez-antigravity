<?php

declare(strict_types=1);

namespace Tests\Modules\X125\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X125\Actions\FlowCreateAction;
use App\Modules\X125\Ui\Canvas;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CanvasScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-125.canvas'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No automations yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(FlowCreateAction::class)->handle(
            businessId: (int) $biz->id,
            name: 'Follow up after a missed call',
            triggerEvent: 'call.missed',
            nodes: [['type' => 'action', 'label' => 'Send a follow-up text']],
        );
        Tenancy::forget();

        $this->get(route('x-125.canvas'))
            ->assertOk()
            ->assertSee('Follow up after a missed call')
            ->assertDontSee('No automations yet');

        Livewire::test(Canvas::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-125.canvas.admin'))->assertOk();

        Livewire::test(Canvas::class)->assertOk();
    }
}
