<?php

declare(strict_types=1);

namespace Tests\Modules\X172;

use App\Models\User;
use App\Modules\X172\Models\PortalLink;
use App\Modules\X172\Ui\CustomerfacingPortal;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerfacingPortalTest extends TestCase
{
    public function test_unknown_token_throws_404(): void
    {
        Livewire::test(CustomerfacingPortal::class, ['token' => 'invalid_tok'])
            ->assertNotFound();
    }

    public function test_valid_job_link_renders_title_and_live_eta(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");
        
        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Fix Sink',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dispatch_assignments')->insert([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'tech_id' => 1,
            'status' => 'en_route',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('eta_predictions')->insert([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'eta_minutes' => 15,
            'estimated_arrival_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $link = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => $jobId,
            'token' => 'valid_job_tok_1788551073',
            'expires_at' => now()->addHours(24),
            'is_active' => true,
            'is_sample' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => 'valid_job_tok_1788551073'])
            ->assertOk()
            ->assertSee('Fix Sink')
            ->assertSee('15 minutes out')
            ->assertSeeHtml('<span', false);
            
        $this->assertDatabaseHas('portal_views', [
            'portal_link_id' => $link->id,
        ]);
    }

    public function test_approve_writes_action_taken(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $link = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => 1,
            'token' => 'approve_tok_1788551073',
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => 'approve_tok_1788551073'])
            ->call('approve');

        $this->assertDatabaseHas('portal_views', [
            'portal_link_id' => $link->id,
            'action_taken' => 'approved',
        ]);
    }

    public function test_expired_link_yields_new_active_link(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $oldLink = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => 1,
            'token' => 'expired_tok_1788551073',
            'expires_at' => now()->subHours(1),
            'is_active' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => 'expired_tok_1788551073'])
            ->assertOk()
            ->assertSee('A fresh link was sent');

        $this->assertDatabaseHas('portal_links', [
            'id' => $oldLink->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('portal_links', [
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => 1,
            'is_active' => true,
        ]);
    }
    
    public function test_no_password_text_exists(): void
    {
        $out = shell_exec("grep -riE password " . app_path('Modules/X-172'));
        $this->assertEmpty($out, 'No handwritten password text should exist in X-172');
    }
}
