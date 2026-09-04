<?php

declare(strict_types=1);

namespace Tests\Modules\X203\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X203\Ui\RunbookRunner;
use Livewire\Livewire;
use Tests\TestCase;

class RunbookRunnerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-203.runbook-runner'))->assertOk();

        Livewire::test(RunbookRunner::class)->assertOk();
    }
}
