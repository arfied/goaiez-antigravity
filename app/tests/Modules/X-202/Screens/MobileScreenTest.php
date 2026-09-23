<?php

declare(strict_types=1);

namespace Tests\Modules\X202\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X202\Models\ApprovalItem;
use App\Modules\X202\Ui\Mobile;
use App\Modules\X202\Ui\Queue;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class MobileScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-202.mobile'))->assertOk();

        Livewire::test(Mobile::class)->assertOk();
    }

    public function test_the_screen_lists_a_waiting_item(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund past the mobile limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Tenancy::forget();
        $this->get(route('x-202.mobile'))
            ->assertOk()
            ->assertSee('Refund past the mobile limit')
            ->assertSee('approveItem(');
    }

    public function test_can_approve_from_the_mobile_screen(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund past the mobile limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(Mobile::class)
            ->call('approveItem', $item->id)
            ->assertSet('error', null)
            ->assertSee('it does not carry out what was asked');

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'approved',
        ]);
    }

    public function test_can_reject_from_the_mobile_screen(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund past the mobile limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(Mobile::class)
            ->call('rejectItem', $item->id)
            ->assertSet('error', null)
            ->assertSee('it does not carry out what was asked');

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'rejected',
        ]);
    }

    public function test_a_decided_item_is_refused_and_leaves_the_list(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund past the mobile limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(Mobile::class)
            ->call('approveItem', $item->id);

        $item->refresh();

        Livewire::test(Mobile::class)
            ->call('approveItem', $item->id)
            ->assertSee('Nothing was changed.')
            ->assertSee($item->decided_at->toDateString());

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'approved',
        ]);

        Tenancy::forget();
        $this->get(route('x-202.mobile'))
            ->assertOk()
            ->assertDontSee('Refund past the mobile limit');
    }

    public function test_approving_another_tenants_item_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund past the mobile limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $itemB = ApprovalItem::where('business_id', $bizB->id)->firstOrFail();

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);
        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Mobile::class)
            ->call('approveItem', $itemB->id);
    }
}
