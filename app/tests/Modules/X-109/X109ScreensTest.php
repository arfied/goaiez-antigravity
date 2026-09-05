<?php

declare(strict_types=1);

namespace Tests\Modules\X109;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X109\Models\CaptchaQuota;
use App\Modules\X109\Ui\ManualQueue;
use App\Modules\X109\Ui\SubmissionLog;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class X109ScreensTest extends TestCase
{
    public function test_submission_log_renders_empty_state_and_is_tenant_scoped(): void
    {
        $otherBiz = TestCase::provisionTenant();
        CaptchaQuota::forceCreate([
            'business_id' => $otherBiz->id,
            'campaign_id' => 10,
            'prospect_identifier' => '100',
            'status' => 'submitted',
            'available_quota' => 10,
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(SubmissionLog::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('No submissions yet')
            ->assertDontSee((string) $otherBiz->id);

        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('x-109.submission-log.admin', ['business' => $biz->id]))
            ->assertOk()
            ->assertSee('No submissions yet');
    }

    public function test_submission_log_renders_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'prospect_identifier' => '992',
            'status' => 'submitted',
            'available_quota' => 45,
        ]);
        Livewire::test(SubmissionLog::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('991')
            ->assertSee('992')
            ->assertSee('45');
    }

    public function test_manual_queue_renders_empty_state_and_is_tenant_scoped(): void
    {
        $otherBiz = TestCase::provisionTenant();
        CaptchaQuota::forceCreate([
            'business_id' => $otherBiz->id,
            'campaign_id' => 10,
            'prospect_identifier' => '100',
            'status' => 'queued_manual',
            'available_quota' => 0,
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ManualQueue::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('Queue is empty')
            ->assertDontSee((string) $otherBiz->id);

        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('x-109.manual-queue.admin', ['business' => $biz->id]))
            ->assertOk()
            ->assertSee('Queue is empty');
    }

    public function test_manual_queue_resubmit_anchor(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        $queueRow = CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'prospect_identifier' => '992',
            'status' => 'queued_manual',
            'available_quota' => 0,
        ]);

        Livewire::test(ManualQueue::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSeeHtml('wire:submit="resubmit('.$queueRow->id.')"');
    }

    /**
     * @group G3-06
     * @group G3-36
     */
    public function test_manual_queue_resubmit_success(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        // Add some quota
        CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'status' => 'submitted', // latest record
            'available_quota' => 5,
        ]);

        $queueRow = CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'prospect_identifier' => '992',
            'status' => 'queued_manual',
            'available_quota' => 0,
        ]);

        Livewire::test(ManualQueue::class, ['businessId' => $biz->id])
            ->call('resubmit', $queueRow->id)
            ->assertDontSee('Zero quota');

        $this->assertNull(CaptchaQuota::find($queueRow->id));
        $this->assertTrue(CaptchaQuota::where('business_id', $biz->id)->where('prospect_identifier', '992')->where('status', 'submitted')->exists());
    }

    public function test_manual_queue_resubmit_queued_again(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        // Add zero quota
        CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'status' => 'submitted', // latest record
            'available_quota' => 0,
        ]);

        $queueRow = CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'prospect_identifier' => '992',
            'status' => 'queued_manual',
            'available_quota' => 0,
        ]);

        Livewire::test(ManualQueue::class, ['businessId' => $biz->id])
            ->call('resubmit', $queueRow->id)
            ->assertSee('Zero quota: queued to manual review without third-party charges');

        // Original row should be updated in place, not deleted
        $this->assertNotNull(CaptchaQuota::find($queueRow->id));
        $this->assertTrue(CaptchaQuota::where('business_id', $biz->id)->where('prospect_identifier', '992')->where('status', 'queued_manual')->exists());
    }

    public function test_manual_queue_resubmit_skipped_duplicate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'prospect_identifier' => '992',
            'status' => 'submitted',
            'available_quota' => 5,
        ]);

        $queueRow = CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'prospect_identifier' => '992',
            'status' => 'queued_manual',
            'available_quota' => 0,
        ]);

        Livewire::test(ManualQueue::class, ['businessId' => $biz->id])
            ->call('resubmit', $queueRow->id)
            ->assertSee('Prospect form already submitted in this campaign');

        $this->assertNotNull(CaptchaQuota::find($queueRow->id));
    }

    public function test_manual_queue_resubmit_cross_tenant_id(): void
    {
        $otherBiz = TestCase::provisionTenant();
        $otherBizQueueRow = CaptchaQuota::forceCreate([
            'business_id' => $otherBiz->id,
            'campaign_id' => 991,
            'prospect_identifier' => '992',
            'status' => 'queued_manual',
            'available_quota' => 0,
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ManualQueue::class, ['businessId' => $biz->id])
            ->call('resubmit', $otherBizQueueRow->id)
            ->assertDontSee('Prospect form already submitted')
            ->assertDontSee('Zero quota'); // shouldn't show messages meant for valid actions

        Tenancy::set($otherBiz->id);
        // Shouldn't be processed or deleted
        $this->assertNotNull(CaptchaQuota::find($otherBizQueueRow->id));
    }

    public function test_submission_log_excludes_quota_balance_row(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        CaptchaQuota::forceCreate([
            'business_id' => $biz->id,
            'campaign_id' => 991,
            'prospect_identifier' => null, // This is the balance row
            'status' => 'submitted',
            'available_quota' => 45,
        ]);

        Livewire::test(SubmissionLog::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('No submissions yet');
    }

    public function test_manual_queue_toggles_sample_state(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        Livewire::test(ManualQueue::class, ['businessId' => $biz->id])
            ->assertDontSee('9401')
            ->call('toggleSample')
            ->assertSee('9401');
    }

    public function test_submission_log_toggles_sample_state(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        Livewire::test(SubmissionLog::class, ['businessId' => $biz->id])
            ->assertDontSee('9401')
            ->call('toggleSample')
            ->assertSee('9401');
    }
}
