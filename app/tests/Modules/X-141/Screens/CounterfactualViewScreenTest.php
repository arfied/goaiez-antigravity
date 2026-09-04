<?php

namespace Tests\Modules\X141\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CounterfactualViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-141.counterfactual-view'))->assertOk();

        Livewire::test(\App\Modules\X141\Ui\CounterfactualView::class)->assertOk();
    }
}
