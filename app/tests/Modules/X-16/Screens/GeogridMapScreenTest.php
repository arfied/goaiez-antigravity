<?php

declare(strict_types=1);

namespace Tests\Modules\X16\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X16\Ui\GeogridMap;
use Livewire\Livewire;
use Tests\TestCase;

class GeogridMapScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-16.geogrid-map'))->assertOk();

        Livewire::test(GeogridMap::class)->assertOk();
    }
}
