<?php

namespace Tests\Modules\X121\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class EntityHistoryViewerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-121.entity-history-viewer'))->assertOk();

        Livewire::test(\App\Modules\X121\Ui\EntityHistoryViewer::class)->assertOk();
    }
}
