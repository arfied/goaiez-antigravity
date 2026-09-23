<?php

declare(strict_types=1);

namespace Tests\Modules\X195\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X195\Models\MarketItem;
use App\Modules\X195\Ui\MarketplaceView;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-195.marketplace'))->assertOk();

        Livewire::test(MarketplaceView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-195.marketplace.admin'))->assertOk();

        Livewire::test(MarketplaceView::class)->assertOk();
    }

    public function test_can_publish_item(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(MarketplaceView::class)
            ->set('itemName', 'Test Item')
            ->set('itemSlug', 'test-item')
            ->set('version', '1.0.0')
            ->set('summary', 'Test summary')
            ->call('publish')
            ->assertSet('success', 'Item Test Item (1.0.0) is listed. A repeat for the same slug edits the listing.');

        $this->assertDatabaseHas((new MarketItem)->getTable(), [
            'business_id' => $biz->id,
            'item_slug' => 'test-item',
            'item_name' => 'Test Item',
            'version' => '1.0.0',
            'is_verified' => false,
        ]);

        $this->get(route('x-195.marketplace'))->assertSee('Test Item')->assertSee('test-item')->assertSee('1.0.0');
        $this->get(route('x-195.manifest-review-queue'))->assertSee('Test Item')->assertSee('Unverified');
    }

    public function test_refuses_empty_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(MarketplaceView::class)
            ->call('publish')
            ->assertSet('error', 'Item name, slug, version, and summary are required.');

        $this->assertDatabaseMissing((new MarketItem)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
