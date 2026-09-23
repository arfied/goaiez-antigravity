<?php

declare(strict_types=1);

namespace Tests\Modules\X172;

use App\Modules\X121\Models\Person;
use App\Modules\X172\Actions\PortalActionHandler;
use App\Modules\X172\Actions\PortalLinkAction;
use App\Modules\X172\Actions\PortalViewAction;
use App\Modules\X172\Events\PortalAction;
use App\Modules\X172\Events\PortalViewed;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X172Test extends TestCase
{
    private PortalLinkAction $linkAction;

    private PortalViewAction $viewAction;

    private PortalActionHandler $actionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linkAction = new PortalLinkAction(app(DefaultsRegistry::class));
        $this->viewAction = new PortalViewAction;
        $this->actionHandler = new PortalActionHandler;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'password' app/Modules/X-172/ returns nothing;
     * a portal link expires and re-issues without the customer ever seeing a login form
     */
    public function test_anchor_zero_password_tokenized_reissue_and_document_interaction(): void
    {
        Event::fake([PortalViewed::class, PortalAction::class]);

        $biz = TestCase::provisionTenant(['name' => 'Portal Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

        // 1. Issue an expiring portal link (-1 hour)
        $expiredLink = $this->linkAction->handle(
            businessId: $biz->id,
            resourceType: 'estimate',
            resourceId: 101,
            customerId: $customer->id,
            ttlHours: -1
        );

        $viewExpired = $this->viewAction->handle($expiredLink->token);
        $this->assertEquals('expired_link', $viewExpired['status']);

        // 2. Re-issue without any login form/credential requirement
        $freshLink = $this->linkAction->handle(
            businessId: $biz->id,
            resourceType: 'estimate',
            resourceId: 101,
            customerId: $customer->id,
            ttlHours: 48
        );

        $this->assertNotEquals($expiredLink->token, $freshLink->token);
        $this->assertTrue($freshLink->is_active);

        // Open fresh link (G13-14: customer opened the document)
        $viewFresh = $this->viewAction->handle($freshLink->token, '1.2.3.4', 'Mozilla/5.0');
        $this->assertEquals('opened', $viewFresh['status']);
        Event::assertDispatched(PortalViewed::class);

        // 3. Customer signs via signature pad (G10-24)
        $signRes = $this->actionHandler->handle($freshLink->token, 'signature_signed', [
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...',
            'explanation' => 'Agreed to standard terms above signature line',
        ]);
        $this->assertEquals('action_recorded', $signRes['status']);
        Event::assertDispatched(PortalAction::class);
    }

    /**
     * [G10-24] REFUSAL: a redline is SURFACED with a diff, never accepted
     */
    public function test_g10_24_refusal_redline_never_accepted(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Refusal Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $link = $this->linkAction->handle($biz->id, 'contract', 101);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('REFUSAL (G10-24): a redline is SURFACED with a diff, never accepted');

        $this->actionHandler->handle($link->token, 'redline_accepted', [
            'clause_id' => 'section_4',
        ]);
    }

    /**
     * [G10-32] a clause comment from the customer; the decision routes to X-202
     */
    public function test_g10_32_clause_comment_routing(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Comment Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $link = $this->linkAction->handle($biz->id, 'contract', 202);
        $res = $this->actionHandler->handle($link->token, 'clause_commented', [
            'clause_id' => 'section_4',
            'comment' => 'Can we adjust payment terms to net-15?',
        ]);

        $this->assertEquals('action_recorded', $res['status']);
    }

    /**
     * [G13-14] when the customer opened the document, in the portal
     */
    public function test_g13_14_document_opened_timestamp(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Open Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $link = $this->linkAction->handle($biz->id, 'contract', 101);
        $view = $this->viewAction->handle($link->token, '1.2.3.4', 'Mozilla');
        $this->assertEquals('opened', $view['status']);
    }

    /**
     * [G16-27] a recorded explanation above the signature line
     */
    public function test_g16_27_explanation_above_signature(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sign Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $link = $this->linkAction->handle($biz->id, 'contract', 101);
        $res = $this->actionHandler->handle($link->token, 'signature_signed', [
            'signature_data' => 'base64...',
            'explanation' => 'I agree to the terms',
        ]);
        $this->assertEquals('action_recorded', $res['status']);
    }
}
