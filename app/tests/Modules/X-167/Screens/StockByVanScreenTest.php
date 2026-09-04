<?php

namespace Tests\Modules\X167\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class StockByVanScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-167.stock-by-van'))->assertOk();

        Livewire::test(\App\Modules\X167\Ui\StockByVan::class)->assertOk();
    }
}
