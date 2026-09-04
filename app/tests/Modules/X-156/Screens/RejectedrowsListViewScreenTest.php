<?php

namespace Tests\Modules\X156\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RejectedrowsListViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-156.rejectedrows-list'))->assertOk();

        Livewire::test(\App\Modules\X156\Ui\RejectedrowsListView::class)->assertOk();
    }
}
