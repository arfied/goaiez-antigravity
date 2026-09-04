<?php

declare(strict_types=1);

namespace Tests\Modules\X193\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X193\Ui\QuiethourHolds;
use Livewire\Livewire;
use Tests\TestCase;

class QuiethourHoldsScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-193.quiethour-holds.admin'))->assertOk();

        Livewire::test(QuiethourHolds::class)->assertOk();
    }
}
