<?php

declare(strict_types=1);

namespace Tests\Modules\X159\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X159\Ui\AuditScore;
use Livewire\Livewire;
use Tests\TestCase;

class AuditScoreScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-159.score'))->assertOk();

        Livewire::test(AuditScore::class)->assertOk();
    }
}
