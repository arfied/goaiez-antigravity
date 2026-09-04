<?php

declare(strict_types=1);

namespace Tests\Modules\X173\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X173\Ui\ConnectionMappingView;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectionMappingViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-173.connection-mapping'))->assertOk();

        Livewire::test(ConnectionMappingView::class)->assertOk();
    }
}
