<?php

namespace Tests\Modules\X124\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AssistantunsupportedLogScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-124.assistantunsupported-log'))->assertOk();

        Livewire::test(\App\Modules\X124\Ui\AssistantunsupportedLog::class)->assertOk();
    }
}
