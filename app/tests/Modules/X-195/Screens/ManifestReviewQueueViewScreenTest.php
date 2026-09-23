<?php

declare(strict_types=1);

namespace Tests\Modules\X195\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X195\Models\MarketItem;
use App\Modules\X195\Ui\ManifestReviewQueueView;
use Livewire\Livewire;
use Tests\TestCase;

class ManifestReviewQueueViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-195.manifest-review-queue'))->assertOk();

        Livewire::test(ManifestReviewQueueView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-195.manifest-review-queue.admin'))->assertOk();

        Livewire::test(ManifestReviewQueueView::class)->assertOk();
    }

    public function test_renders_unverified_items(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        MarketItem::create([
            'business_id' => $biz->id,
            'item_slug' => 'queue-item',
            'item_name' => 'Queue Item',
            'version' => '1.0.0',
            'manifest_json' => ['summary' => 'Queue summary'],
            'is_verified' => false,
        ]);

        $this->get(route('x-195.manifest-review-queue'))->assertSee('Queue Item')->assertSee('Unverified');
    }
}
