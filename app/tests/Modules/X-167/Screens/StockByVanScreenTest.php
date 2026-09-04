<?php

declare(strict_types=1);

namespace Tests\Modules\X167\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X167\Ui\StockByVan;
use Livewire\Livewire;
use Tests\TestCase;

class StockByVanScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-167.stock-by-van'))->assertOk();

        Livewire::test(StockByVan::class)->assertOk();
    }
}
