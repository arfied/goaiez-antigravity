<?php

declare(strict_types=1);

namespace Tests\Modules\X173\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X173\Ui\SyncErrorRateView;
use Livewire\Livewire;
use Tests\TestCase;

class SyncErrorRateViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-173.sync-error-rate'))->assertOk();

        Livewire::test(SyncErrorRateView::class)->assertOk();
    }
}
