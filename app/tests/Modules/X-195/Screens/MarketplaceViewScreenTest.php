<?php

namespace Tests\Modules\X195\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MarketplaceViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-195.marketplace'))->assertOk();

        Livewire::test(\App\Modules\X195\Ui\MarketplaceView::class)->assertOk();
    }
}
