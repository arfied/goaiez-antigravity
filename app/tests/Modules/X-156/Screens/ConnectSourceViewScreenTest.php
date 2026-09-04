<?php

declare(strict_types=1);

namespace Tests\Modules\X156\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
