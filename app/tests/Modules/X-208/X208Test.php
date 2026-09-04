<?php

declare(strict_types=1);

namespace Tests\Modules\X208;

use App\Modules\X208\Actions\MailComposeAction;
use App\Modules\X208\Actions\MailProposeAction;
use App\Modules\X208\Actions\MailSendAction;
use App\Modules\X208\Events\ApprovalRequested;
use App\Modules\X208\Models\MailPiece;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X208Test extends TestCase
{
    private MailComposeAction $composeAction;

    private MailProposeAction $proposeAction;

    private MailSendAction $sendAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->composeAction = new MailComposeAction;
        $this->proposeAction = new MailProposeAction;
        $this->sendAction = new MailSendAction;
    }

    /**
     * TEST ANCHOR
     * a Do-Not-Mail address produces NO PIECE — refused at generation, with the reason.
     * The platform holds no Lob credential: doctor asserts the key is read from the tenant's vault row.
     */
    public function test_anchor_do_not_mail_refusal_and_vault_credential_origin(): void
    {
        Event::fake([ApprovalRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Direct Mail Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dnmAddress = '742 Evergreen Terrace, Springfield';
        $validAddress = '100 Main St, Suite 200, Denver CO';

        // 1. Do-Not-Mail address produces NO PIECE — refused at generation (TEST ANCHOR)
        $dnmRes = $this->composeAction->handle(
            businessId: $biz->id,
            recipientAddress: $dnmAddress,
            isDoNotMail: true
        );

        $this->assertEquals('refused', $dnmRes['status']);
        $this->assertEquals('RECIPIENT_ON_DO_NOT_MAIL_LIST', $dnmRes['refusal_code']);
        $this->assertNull($dnmRes['piece'], 'DNM address produces NO PIECE (null)');

        $dnmPiecesCount = MailPiece::where('business_id', $biz->id)->where('recipient_address', $dnmAddress)->count();
        $this->assertEquals(0, $dnmPiecesCount, 'Zero database rows written for DNM address');

        // 2. Valid address composes successfully
        $validRes = $this->composeAction->handle(
            businessId: $biz->id,
            recipientAddress: $validAddress,
            isDoNotMail: false
        );

        $this->assertEquals('composed', $validRes['status']);
        $this->assertNotNull($validRes['piece']);
        $this->assertEquals('tenant_vault', $validRes['piece']->lob_api_key_source);

        // 3. Propose piece -> emits approval.requested
        $proposeRes = $this->proposeAction->handle($biz->id, $validRes['piece_id']);
        $this->assertEquals('proposed', $proposeRes['status']);
        Event::assertDispatched(ApprovalRequested::class);

        // 4. Send piece using credential read from tenant's vault row
        $vaultApiKey = 'live_sec_lob_vault_key_9901';
        $sendRes = $this->sendAction->handle($biz->id, $validRes['piece_id'], $vaultApiKey);
        $this->assertEquals('sent', $sendRes['status']);
        $this->assertEquals('queued_for_print', $sendRes['lob_status']);

        $sentPiece = MailPiece::where('business_id', $biz->id)->find($validRes['piece_id']);
        $this->assertEquals('tenant_vault', $sentPiece->lob_api_key_source);
    }

    /**
     * [N-208-01]
     */
    public function test_header_capabilities(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
