<?php

namespace Tests\Modules\X173\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SyncErrorRateViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-173.sync-error-rate'))->assertOk();

        Livewire::test(\App\Modules\X173\Ui\SyncErrorRateView::class)->assertOk();
    }
}
