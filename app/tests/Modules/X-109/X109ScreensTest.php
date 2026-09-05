<?php

declare(strict_types=1);

namespace Tests\Modules\X109;

use App\Models\User;
use App\Enums\UserRole;
use App\Modules\X109\Models\CaptchaQuota;
use App\Modules\X109\Ui\ManualQueue;
use App\Modules\X109\Ui\SubmissionLog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class X109ScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_log_renders_empty_state_and_is_tenant_scoped(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        CaptchaQuota::forceCreate([
            'business_id' => 99999, // Another tenant
            'campaign_id' => 10,
            'prospect_identifier' => '100',
            'status' => 'submitted',
            'available_quota' => 10,
        ]);

        Livewire::test(SubmissionLog::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('No submissions yet')
            ->assertDontSee('99999');

        $this->get(route('x-109.submission-log.admin', ['business' => $biz->id]))
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
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        CaptchaQuota::forceCreate([
            'business_id' => 99999, // Another tenant
            'campaign_id' => 10,
            'prospect_identifier' => '100',
            'status' => 'queued_manual',
            'available_quota' => 0,
        ]);

        Livewire::test(ManualQueue::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('Queue is empty')
            ->assertDontSee('99999');

        $this->get(route('x-109.manual-queue.admin', ['business' => $biz->id]))
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

        // Original row should be deleted
        $this->assertNull(CaptchaQuota::find($queueRow->id));
        // A new queued_manual row should be created by the action
        $this->assertTrue(CaptchaQuota::where('business_id', $biz->id)->where('prospect_identifier', '992')->where('status', 'queued_manual')->exists());
    }
}
