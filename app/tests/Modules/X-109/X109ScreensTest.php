<?php

declare(strict_types=1);

namespace Tests\Modules\X109;

use App\Models\User;
use App\Models\UserRole;
use App\Modules\X109\Models\CaptchaQuota;
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
}
