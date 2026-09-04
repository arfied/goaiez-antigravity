<?php

namespace Tests\Modules\X153\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AlertReplyByScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-153.alert-reply-by'))->assertOk();

        Livewire::test(\App\Modules\X153\Ui\AlertReplyBy::class)->assertOk();
    }
}
