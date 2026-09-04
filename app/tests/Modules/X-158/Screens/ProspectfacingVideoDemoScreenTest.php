<?php

declare(strict_types=1);

namespace Tests\Modules\X158\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ProspectfacingVideoDemoScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-158.prospectfacing-video-demo'))->assertOk();

        Livewire::test(\App\Modules\X158\Ui\ProspectfacingVideoDemo::class)->assertOk();
    }
}
