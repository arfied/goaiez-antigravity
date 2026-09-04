<?php

declare(strict_types=1);

namespace Tests\Modules\X109\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X109\Ui\SubmissionLog;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionLogScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-109.submission-log'))->assertOk();

        Livewire::test(SubmissionLog::class)->assertOk();
    }
}
