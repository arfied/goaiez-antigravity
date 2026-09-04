<?php

namespace Tests\Modules\X128\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MatrixViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-128.matrix-view'))->assertOk();

        Livewire::test(\App\Modules\X128\Ui\MatrixView::class)->assertOk();
    }
}
