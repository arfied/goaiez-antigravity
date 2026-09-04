<?php

namespace Tests\Modules\X159\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AuditScoreScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-159.score'))->assertOk();

        Livewire::test(\App\Modules\X159\Ui\AuditScore::class)->assertOk();
    }
}
