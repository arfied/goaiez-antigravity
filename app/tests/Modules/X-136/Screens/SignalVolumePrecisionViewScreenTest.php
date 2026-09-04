<?php

declare(strict_types=1);

namespace Tests\Modules\X136\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class SignalVolumePrecisionViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-136.signal-volume-precision'))->assertOk();

        Livewire::test(\App\Modules\X136\Ui\SignalVolumePrecisionView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-136.signal-volume-precision.admin'))->assertOk();

        Livewire::test(\App\Modules\X136\Ui\SignalVolumePrecisionView::class)->assertOk();
    }
}
