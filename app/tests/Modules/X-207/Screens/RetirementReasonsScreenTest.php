<?php

declare(strict_types=1);

namespace Tests\Modules\X207\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X207\Ui\RetirementReasons;
use Livewire\Livewire;
use Tests\TestCase;

class RetirementReasonsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-207.retirement-reasons'))->assertOk();

        Livewire::test(RetirementReasons::class)->assertOk();
    }
}
