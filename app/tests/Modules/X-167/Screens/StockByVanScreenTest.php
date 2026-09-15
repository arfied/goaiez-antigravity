<?php

declare(strict_types=1);

namespace Tests\Modules\X167\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X167\Models\StockItem;
use App\Modules\X167\Models\StockLocation;
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

        // Empty state
        $this->get(route('x-167.stock-by-van'))
            ->assertOk()
            ->assertSeeText('No stock yet');

        // Seed distinctive StockItem/StockLocation
        $loc = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Distinctive Van 999',
        ]);

        StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $loc->id,
            'sku' => 'DISTINCT-123',
            'name' => 'Distinctive Widget',
            'quantity' => 10,
            'reorder_point' => 5,
            'unit' => 'ea',
        ]);

        $this->get(route('x-167.stock-by-van'))
            ->assertOk()
            ->assertSeeText('Distinctive Van 999')
            ->assertSeeText('Distinctive Widget');

        Livewire::test(StockByVan::class)->assertOk();
    }
}
