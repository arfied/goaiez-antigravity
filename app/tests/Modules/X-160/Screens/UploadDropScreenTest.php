<?php

namespace Tests\Modules\X160\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class UploadDropScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-160.upload-drop'))->assertOk();

        Livewire::test(\App\Modules\X160\Ui\UploadDrop::class)->assertOk();
    }
}
