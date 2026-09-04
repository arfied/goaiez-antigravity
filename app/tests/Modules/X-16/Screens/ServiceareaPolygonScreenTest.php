<?php

namespace Tests\Modules\X16\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ServiceareaPolygonScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-16.servicearea-polygon'))->assertOk();

        Livewire::test(\App\Modules\X16\Ui\ServiceareaPolygon::class)->assertOk();
    }
}
