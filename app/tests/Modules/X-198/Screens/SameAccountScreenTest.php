<?php

declare(strict_types=1);

namespace Tests\Modules\X198\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X198\Ui\SameAccount;
use Livewire\Livewire;
use Tests\TestCase;

class SameAccountScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-198.same-account'))->assertOk();

        Livewire::test(SameAccount::class)->assertOk();
    }
}
