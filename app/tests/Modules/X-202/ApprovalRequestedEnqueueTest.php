<?php

declare(strict_types=1);

namespace Tests\Modules\X202;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Events\ApprovalRequested;
use App\Modules\X202\Models\ApprovalItem;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Note: X-190's slot proposals and X-205's payout requests also dispatch a class
 * called ApprovalRequested, they carry different fields, and turning either into an
 * approval item needs a product decision about what its itemType, subject and payload
 * should be — so they are deliberately not wired.
 */
class ApprovalRequestedEnqueueTest extends TestCase
{
    public function test_listener_enqueues_approval_and_appears_on_queue_screen(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $subject = 'Proposed layout optimization for Home';

        Event::dispatch(new ApprovalRequested(
            businessId: $biz->id,
            itemType: 'site_optimization',
            subject: $subject,
            payload: ['proposed_blocks' => ['header', 'footer']]
        ));

        $this->assertDatabaseHas(ApprovalItem::class, [
            'business_id' => $biz->id,
            'item_type' => 'site_optimization',
            'subject' => $subject,
        ]);

        $this->get('/app/x-202/queue')
            ->assertOk()
            ->assertSee($subject);
    }

    public function test_listener_does_not_bypass_engine_refusal_for_empty_payload(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Event::dispatch(new ApprovalRequested(
            businessId: $biz->id,
            itemType: 'site_optimization',
            subject: 'Invalid Empty Payload Request',
            payload: []
        ));

        $this->assertDatabaseMissing(ApprovalItem::class, [
            'business_id' => $biz->id,
            'subject' => 'Invalid Empty Payload Request',
        ]);
    }
}
