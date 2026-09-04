<?php

declare(strict_types=1);

namespace Tests\Modules\X124\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
