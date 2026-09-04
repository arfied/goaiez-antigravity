<?php

declare(strict_types=1);

namespace Tests\Modules\X82\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class RateRegistryViewScreenTest extends TestCase
{

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-82.rate-registry.admin'))->assertOk();

        Livewire::test(\App\Modules\X82\Ui\RateRegistryView::class)->assertOk();
    }
}
