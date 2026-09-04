<?php

namespace Tests\Modules\X173\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ConflictsListViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-173.conflicts-list'))->assertOk();

        Livewire::test(\App\Modules\X173\Ui\ConflictsListView::class)->assertOk();
    }
}
