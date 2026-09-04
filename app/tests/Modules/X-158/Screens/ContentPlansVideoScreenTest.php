<?php

namespace Tests\Modules\X158\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ContentPlansVideoScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-158.content-plans-video'))->assertOk();

        Livewire::test(\App\Modules\X158\Ui\ContentPlansVideo::class)->assertOk();
    }
}
