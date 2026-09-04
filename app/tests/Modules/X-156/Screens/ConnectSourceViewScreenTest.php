<?php

namespace Tests\Modules\X156\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ConnectSourceViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-156.connect-source'))->assertOk();

        Livewire::test(\App\Modules\X156\Ui\ConnectSourceView::class)->assertOk();
    }
}
