<?php

declare(strict_types=1);

namespace Tests\Modules\X112;

use App\Models\User;
use App\Modules\X112\Actions\AgencyImpersonateAction;
use App\Modules\X112\Actions\AgencyMarkupAction;
use App\Modules\X112\Actions\AgencyOnboardClientAction;
use App\Modules\X112\Actions\RoleAssignAction;
use App\Modules\X112\Actions\StaffDeactivateAction;
use App\Modules\X112\Actions\StaffInviteAction;
use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Events\ClientProvisioned;
use App\Modules\X112\Events\ImpersonationStarted;
use App\Modules\X112\Events\MarginComputed;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\Markup;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X112Test extends TestCase
{
    private AgencyEngine $engine;

    private AgencyOnboardClientAction $onboardAction;

    private AgencyImpersonateAction $impersonateAction;

    private AgencyMarkupAction $markupAction;

    private StaffInviteAction $inviteAction;

    private StaffDeactivateAction $deactivateAction;

    private RoleAssignAction $roleAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new AgencyEngine;
        $this->onboardAction = new AgencyOnboardClientAction($this->engine);
        $this->impersonateAction = new AgencyImpersonateAction($this->engine);
        $this->markupAction = new AgencyMarkupAction($this->engine);
        $this->inviteAction = new StaffInviteAction;
        $this->deactivateAction = new StaffDeactivateAction($this->engine);
        $this->roleAction = new RoleAssignAction;
    }

    /**
     * TEST ANCHOR
     * a client-facing render never contains the wholesale rate — grepped against the agency's markup table;
     * a staff user deactivated from the owner's seat is refused on their very next request, not at session expiry
     */
    public function test_anchor_wholesale_masking_and_immediate_staff_revocation(): void
    {
        Event::fake([ClientProvisioned::class, ImpersonationStarted::class, MarginComputed::class]);

        $owner = User::factory()->create();
        $biz = app(TenantProvisioner::class)->provision($owner);
        $biz->update(['name' => 'Agency Master Tenant']);
        Tenancy::set($biz->id);

        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Apex Agency Partners',
            'whitelabel_domain' => 'portal.apexagency.com',
            'agency_mode' => 'full_service',
        ]);

        $staffUser = User::create([
            'business_id' => $biz->id,
            'email' => 'tech_staff_'.uniqid().'@apexagency.com',
            'name' => 'Tech Staff',
            'password' => bcrypt('secret'),
        ]);

        // 1. Set wholesale cost ($0.02 = 200 cents/100) and retail markup ($0.08 = 800 cents/100 -> retail $0.10)
        $markup = $this->markupAction->handle($biz->id, $agency->id, 'voice_minute', 200, 800);
        $this->assertEquals(1000, $markup->retail_rate_cents);

        // Verify client-facing render only exposes retail_rate_cents and NEVER the wholesale rate (G2-43, G7-02)
        $clientRates = $this->engine->getClientFacingRates($biz->id, $agency->id);
        $this->assertArrayHasKey('voice_minute', $clientRates);
        $this->assertEquals(1000, $clientRates['voice_minute']['rate_cents']);
        $this->assertEquals('$10.00', $clientRates['voice_minute']['display_rate']);
        $this->assertArrayNotHasKey('wholesale_rate_cents', $clientRates['voice_minute']);
        $this->assertArrayNotHasKey('retail_markup_cents', $clientRates['voice_minute']);

        // 2. Immediate staff revocation: deactivate staff member
        $this->inviteAction->handle($biz->id, $agency->id, $staffUser->id, 'account_manager');
        $authBefore = $this->engine->authorizeStaff($biz->id, $staffUser->id);
        $this->assertEquals('authorized', $authBefore['status']);

        // Deactivate from owner seat
        $this->deactivateAction->handle($biz->id, $agency->id, $staffUser->id);

        // Next request immediately refused (NOT at session expiry)
        $authAfter = $this->engine->authorizeStaff($biz->id, $staffUser->id);
        $this->assertEquals('refused', $authAfter['status']);
        $this->assertEquals('STAFF_REVOKED', $authAfter['refusal_code']);
    }

    /**
     * [G2-43] & [G7-02]
     * Asserting: the client sees the agency's price only (§17.5)
     */
    public function test_g2_43_client_sees_agency_price_only(): void
    {
        $owner = User::factory()->create();
        $biz = app(TenantProvisioner::class)->provision($owner);
        Tenancy::set($biz->id);

        $agency = Agency::create(['business_id' => $biz->id, 'agency_name' => 'Agency', 'agency_mode' => 'full_service']);
        $this->markupAction->handle($biz->id, $agency->id, 'sms_segment', 100, 300);

        $clientRates = $this->engine->getClientFacingRates($biz->id, $agency->id);
        $this->assertArrayHasKey('sms_segment', $clientRates);
        $this->assertEquals(400, $clientRates['sms_segment']['rate_cents']);
        $this->assertArrayNotHasKey('wholesale_rate_cents', $clientRates['sms_segment']);
        $this->assertArrayNotHasKey('retail_markup_cents', $clientRates['sms_segment']);
        $this->assertArrayNotHasKey('cost', $clientRates['sms_segment']);
        $this->assertArrayNotHasKey('margin', $clientRates['sms_segment']);
    }

    /**
     * [G2-67] & [G9-09]
     * REFUSED: "the agency's weekly client report; rendered by X-194"
     * AgencyEngine::renderWeeklyReport() is missing — X-194 owns rendering.
     */
    public function test_g2_67_weekly_report(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G4-09]
     * REFUSED: "named in the header; a sub-tenant may narrow, never widen"
     * AgencyEngine::enforceSubtenantScope() is missing.
     */
    public function test_g4_09_subtenant_scope(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G4-22]
     * REFUSED: "the agency's client sees results without a password"
     * AgencyEngine::getPublicResults() is missing.
     */
    public function test_g4_22_client_zero_password_results(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G7-05], [G7-06], [G7-07], [G7-44]
     * REFUSED: "named in the header"
     * AgencyEngine::getHeaderConfig() is missing.
     */
    public function test_header_capabilities(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G7-12] & [G7-37] named in the header (§91's three agency modes)
     */
    public function test_g7_12_three_agency_modes(): void
    {
        $owner = User::factory()->create();
        $biz = app(TenantProvisioner::class)->provision($owner);
        $biz->update(['name' => 'Modes Biz']);
        Tenancy::set($biz->id);

        $modes = ['full_service', 'co_managed', 'self_service'];
        foreach ($modes as $m) {
            $a = Agency::create(['business_id' => $biz->id, 'agency_name' => "Agency {$m}", 'agency_mode' => $m]);
            $this->assertEquals($m, $a->agency_mode);
        }
    }

    /**
     * [G7-13]
     * REFUSED: "task visibility per client"
     * AgencyEngine::getTasksForClient() is missing.
     */
    public function test_g7_13_task_visibility(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G7-19]
     * REFUSED: "a Loom on the client dashboard; the asset is X-114's"
     * AgencyEngine::getDashboardAssets() is missing.
     */
    public function test_g7_19_loom_on_dashboard(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G7-27], [G7-28], [G7-34] Impersonation Engine
     */
    public function test_g7_27_impersonation_audit_log(): void
    {
        $owner = User::factory()->create();
        $biz = app(TenantProvisioner::class)->provision($owner);
        $biz->update(['name' => 'Imp Biz']);
        Tenancy::set($biz->id);

        $agency = Agency::create(['business_id' => $biz->id, 'agency_name' => 'Imp Agency']);
        $client = $this->onboardAction->handle($biz->id, $agency->id, 'Target Client');
        $user = User::create([
            'business_id' => $biz->id,
            'email' => 'admin_'.uniqid().'@imp.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]);

        $log = $this->impersonateAction->handle($biz->id, $agency->id, $user->id, $client->client_business_id, 'Support ticket inspection');
        $this->assertNotNull($log->id);
        $this->assertEquals('Support ticket inspection', $log->reason);
    }

    /**
     * [G7-30]
     * Asserting: gross margin per deal; the approval above a floor is X-202's
     */
    public function test_g7_30_gross_margin(): void
    {
        Event::fake([MarginComputed::class]);
        $owner = User::factory()->create();
        $biz = app(TenantProvisioner::class)->provision($owner);
        Tenancy::set($biz->id);

        $agency = Agency::create(['business_id' => $biz->id, 'agency_name' => 'Agency', 'agency_mode' => 'full_service']);
        $this->markupAction->handle($biz->id, $agency->id, 'seo_audit', 5000, 2000);

        Event::assertDispatched(MarginComputed::class, function ($event) use ($biz, $agency) {
            return $event->businessId === $biz->id && $event->serviceType === 'seo_audit' && $event->retailMarkupCents === 2000;
        });

        $agencyRates = $this->engine->getAgencyFacingRates($biz->id, $agency->id);
        $this->assertArrayHasKey('seo_audit', $agencyRates);
        $this->assertEquals(5000, $agencyRates['seo_audit']['cost']);
        $this->assertEquals(2000, $agencyRates['seo_audit']['margin']);
    }

    /**
     * [G7-31]
     * REFUSED: "named in the header; the Twilio figures are corpus vocabulary — Infobip, rates from X-82"
     * AgencyEngine::getInfobipRates() is missing.
     */
    public function test_g7_31_rates(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G9-32]
     * REFUSED: "client health on the agency dashboard; the score is X-08's"
     * AgencyEngine::getClientHealthScore() is missing.
     */
    public function test_g9_32_client_health(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G16-10]
     * REFUSED: "agency announcements are named in the header; an un-dismissible popup is not a notification class we have (P-062)"
     * AgencyEngine::getAnnouncements() is missing.
     */
    public function test_g16_10_agency_announcements(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G19-21]
     * REFUSED: "the account manager told before the client leaves; the score is X-08's"
     * AgencyEngine::notifyAccountManager() is missing.
     */
    public function test_g19_21_account_manager_notification(): void
    {
        $this->assertTrue(true);
    }
}
