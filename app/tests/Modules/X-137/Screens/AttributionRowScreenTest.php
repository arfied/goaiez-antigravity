<?php

declare(strict_types=1);

namespace Tests\Modules\X137\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class AttributionRowScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-137.attribution-row'))->assertOk();

        Livewire::test(\App\Modules\X137\Ui\AttributionRow::class)->assertOk();
    }
}
