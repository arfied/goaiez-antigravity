<?php

declare(strict_types=1);

namespace Tests\Modules\X128\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X128\Ui\MatrixView;
use Livewire\Livewire;
use Tests\TestCase;

class MatrixViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-128.matrix-view'))->assertOk();

        Livewire::test(MatrixView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-128.matrix-view.admin'))->assertOk();

        Livewire::test(MatrixView::class)->assertOk();
    }
}
