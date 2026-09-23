<?php

declare(strict_types=1);

namespace Tests\Modules\X109\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X109\Ui\ManualQueue;
use Livewire\Livewire;
use Tests\TestCase;

class ManualQueueScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-109.manual-queue'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSeeText('No parked submissions requiring manual review.');

        Livewire::test(ManualQueue::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-109.manual-queue.admin'))->assertOk();

        Livewire::test(ManualQueue::class)->assertOk();
    }
}
