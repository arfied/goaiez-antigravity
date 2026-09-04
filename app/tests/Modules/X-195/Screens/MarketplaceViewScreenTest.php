<?php

declare(strict_types=1);

namespace Tests\Modules\X195\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X195\Ui\MarketplaceView;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-195.marketplace'))->assertOk();

        Livewire::test(MarketplaceView::class)->assertOk();
    }
}
