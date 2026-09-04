<?php

declare(strict_types=1);

namespace Tests\Modules\X124\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X124\Ui\AssistantunsupportedLog;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantunsupportedLogScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-124.assistantunsupported-log.admin'))->assertOk();

        Livewire::test(AssistantunsupportedLog::class)->assertOk();
    }
}
