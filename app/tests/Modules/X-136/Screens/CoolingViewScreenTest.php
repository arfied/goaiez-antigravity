<?php

declare(strict_types=1);

namespace Tests\Modules\X136\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X136\Ui\CoolingView;
use Livewire\Livewire;
use Tests\TestCase;

class CoolingViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-136.cooling'))->assertOk();

        Livewire::test(CoolingView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-136.cooling.admin'))->assertOk();

        Livewire::test(CoolingView::class)->assertOk();
    }
}
