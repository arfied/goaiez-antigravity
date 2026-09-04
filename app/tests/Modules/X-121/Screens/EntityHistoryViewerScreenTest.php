<?php

declare(strict_types=1);

namespace Tests\Modules\X121\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Ui\EntityHistoryViewer;
use Livewire\Livewire;
use Tests\TestCase;

class EntityHistoryViewerScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-121.entity-history-viewer.admin'))->assertOk();

        Livewire::test(EntityHistoryViewer::class)->assertOk();
    }
}
