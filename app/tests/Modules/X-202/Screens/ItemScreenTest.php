<?php

declare(strict_types=1);

namespace Tests\Modules\X202\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X202\Models\ApprovalItem;
use App\Modules\X202\Ui\Item;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ItemScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);

        $item = ApprovalItem::create([
            'business_id' => $biz->id,
            'item_type' => 'test_type',
            'subject' => 'Test Subject',
            'payload' => ['foo' => 'bar'],
            'expires_at' => now()->addDays(7),
        ]);

        $this->get(route('x-202.item', ['id' => $item->id]))->assertOk();

        Livewire::test(Item::class, ['id' => $item->id])
            ->assertOk()
            ->assertSee('Test Subject')
            ->assertSee('test_type');
    }

    public function test_unknown_or_other_tenant_id_returns_404(): void
    {
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);

        $itemA = ApprovalItem::create([
            'business_id' => $bizA->id,
            'item_type' => 'test_type_a',
            'subject' => 'Test Subject A',
            'payload' => [],
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($ownerB);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);

        $response = $this->get(route('x-202.item', ['id' => $itemA->id]));
        $response->assertNotFound();

        $responseUnknown = $this->get(route('x-202.item', ['id' => 99999]));
        $responseUnknown->assertNotFound();
    }

    public function test_no_tenant_returns_403(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        // Do not provision a tenant!

        $this->actingAs($owner);

        $response = $this->get(route('x-202.item', ['id' => 1]));
        $response->assertForbidden();
    }

    public function test_approve_moves_status_and_stamps_decided_at(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);

        $item = ApprovalItem::create([
            'business_id' => $biz->id,
            'item_type' => 'test_type',
            'subject' => 'Test Subject',
            'payload' => [],
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::test(Item::class, ['id' => $item->id])
            ->call('approveItem')
            ->assertSee('is approved');

        $item->refresh();
        $this->assertEquals('approved', $item->status);
        $this->assertNotNull($item->decided_at);
        $this->assertEquals($owner->id, $item->decided_by_user_id);
    }

    public function test_escalate_marks_escalated_and_leaves_decided_at_null(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);

        $item = ApprovalItem::create([
            'business_id' => $biz->id,
            'item_type' => 'test_type',
            'subject' => 'Test Subject',
            'payload' => [],
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::test(Item::class, ['id' => $item->id])
            ->set('escalateReason', 'Need more info')
            ->call('escalateItem')
            ->assertSee('stays on this queue');

        $item->refresh();
        $this->assertEquals('escalated', $item->status);
        $this->assertNull($item->decided_at);
    }
}
