<?php

declare(strict_types=1);

namespace Tests\Modules\X172;

use App\Models\User;
use App\Modules\X165\Actions\MembershipStartAction;
use App\Modules\X165\Actions\PlanProposeAction;
use App\Modules\X172\Models\PortalLink;
use App\Modules\X172\Ui\CustomerfacingPortal;
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

    public function test_seeded_row_reaches_the_page(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Tech Job',
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
            'eta_minutes' => 47,
            'estimated_arrival_at' => now()->addMinutes(47),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = 'valid_job_tok_'.uniqid();
        PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => $jobId,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        $owner = User::find($biz->owner_user_id);

        $response = $this->actingAs($owner)->get(route('x-172.customerfacing-portal', ['token' => $token]));
        $response->assertOk()
            ->assertSee('47 minutes out');
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

        $token = 'valid_job_tok_'.uniqid();
        $link = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => $jobId,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
            'is_sample' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertOk()
            ->assertSee('Fix Sink')
            ->assertSee('15 minutes out')
            ->assertSeeHtml('<span aria-hidden="true">▲</span>
    <span>Sample</span>', false);

        $this->assertDatabaseHas('portal_views', [
            'portal_link_id' => $link->id,
        ]);
    }

    public function test_approve_writes_action_taken(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $token = 'approve_tok_'.uniqid();
        $link = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => 1,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
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

        $token = 'expired_tok_'.uniqid();
        $oldLink = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => 1,
            'token' => $token,
            'expires_at' => now()->subHours(1),
            'is_active' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertOk()
            ->assertSee('has been refreshed');

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
        $out = shell_exec('grep -riE password '.app_path('Modules/X-172'));
        $this->assertEmpty($out, 'No handwritten password text should exist in X-172');
    }

    public function test_active_membership_displays_status(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $personId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Alice',
            'last_name' => 'Member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plan = app(PlanProposeAction::class)->handle($biz->id, 'Gold Plan', 19900);
        app(MembershipStartAction::class)->handle($biz->id, $plan->id, $personId);

        $token = 'member_tok_'.uniqid();
        $link = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => 1,
            'customer_id' => $personId,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertOk()
            ->assertSee('active');
    }

    public function test_expired_link_refreshes_in_band_and_shows_the_resource(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Distinctive Fixer Job',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = 'expired_in_band_tok_'.uniqid();
        $oldLink = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => $jobId,
            'token' => $token,
            'expires_at' => now()->subHours(1),
            'is_active' => true,
        ]);

        $component = Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertOk()
            ->assertSee('has been refreshed', 'The notice still shows')
            ->assertSee('Distinctive Fixer Job', 'This is the assertion the whole wave exists for.');

        $newToken = $component->get('token');
        $this->assertNotSame($token, $newToken, 'Component token must be updated to the new token');

        $this->assertDatabaseHas('portal_links', [
            'token' => $newToken,
            'is_active' => true,
        ]);

        $newLink = PortalLink::where('token', $newToken)->first();
        $this->assertTrue($newLink->expires_at->isFuture(), 'New link expires_at is in the future');
    }

    public function test_unknown_token_mints_no_link(): void
    {
        $countBefore = PortalLink::count();

        Livewire::test(CustomerfacingPortal::class, ['token' => 'nope_'.uniqid()])
            ->assertNotFound();

        $countAfter = PortalLink::count();
        $this->assertSame($countBefore, $countAfter, 'a 404 must never mint a portal link');
    }

    public function test_deactivated_link_is_404_and_mints_no_link(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $token = 'deactivated_tok_'.uniqid();
        PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => 1,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => false,
        ]);

        $countBefore = PortalLink::count();

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertNotFound();

        $countAfter = PortalLink::count();
        $this->assertSame($countBefore, $countAfter, 'a 404 must never mint a portal link');
    }

    public function test_review_button_is_absent(): void
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

        $token = 'valid_job_tok_'.uniqid();
        $link = PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => $jobId,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
            'is_sample' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertOk()
            ->assertSee('Approve')
            ->assertDontSee('Leave a review');
    }

    public function test_en_route_without_eta_prediction_shows_no_minutes(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Job No ETA',
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

        $token = 'no_eta_tok_'.uniqid();
        PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => $jobId,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertOk()
            ->assertSee('Your technician is en route.')
            ->assertDontSee('minutes out');
    }

    public function test_the_portal_does_not_call_a_cancelled_job_booked_and_confirmed(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Tech Job',
            'status' => 'cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = 'valid_job_tok_'.uniqid();
        PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => $jobId,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertOk()
            ->assertSee('This job was cancelled.')
            ->assertDontSee('booked and confirmed');
    }

    public function test_the_portal_says_the_job_is_on_file_when_nothing_confirms_it(): void
    {
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Tech Job',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = 'valid_job_tok_'.uniqid();
        PortalLink::create([
            'business_id' => $biz->id,
            'resource_type' => 'job',
            'resource_id' => $jobId,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->assertOk()
            ->assertSee('Your job is on file')
            ->assertDontSee('booked and confirmed');
    }
}
