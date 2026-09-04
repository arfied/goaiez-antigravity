<?php

declare(strict_types=1);

namespace Tests\Modules\X173\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X173\Ui\ConflictsListView;
use Livewire\Livewire;
use Tests\TestCase;

class ConflictsListViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-173.conflicts-list'))->assertOk();

        Livewire::test(ConflictsListView::class)->assertOk();
    }
}
