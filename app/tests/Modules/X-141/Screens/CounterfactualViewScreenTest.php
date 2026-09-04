<?php

declare(strict_types=1);

namespace Tests\Modules\X141\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X141\Ui\CounterfactualView;
use Livewire\Livewire;
use Tests\TestCase;

class CounterfactualViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-141.counterfactual-view'))->assertOk();

        Livewire::test(CounterfactualView::class)->assertOk();
    }
}
