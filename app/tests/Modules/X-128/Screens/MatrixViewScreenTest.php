<?php

declare(strict_types=1);

namespace Tests\Modules\X128\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
