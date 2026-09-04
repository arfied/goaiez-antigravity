<?php

declare(strict_types=1);

namespace Tests\Modules\X82\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X82\Ui\RateRegistryView;
use Livewire\Livewire;
use Tests\TestCase;

class RateRegistryViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-82.rate-registry'))->assertOk();

        Livewire::test(RateRegistryView::class)->assertOk();
    }
}
