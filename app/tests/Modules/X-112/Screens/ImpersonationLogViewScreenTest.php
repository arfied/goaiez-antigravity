<?php

namespace Tests\Modules\X112\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ImpersonationLogViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-112.impersonation-log'))->assertOk();

        Livewire::test(\App\Modules\X112\Ui\ImpersonationLogView::class)->assertOk();
    }
}
