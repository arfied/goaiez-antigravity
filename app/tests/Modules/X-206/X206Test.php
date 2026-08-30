<?php

declare(strict_types=1);

namespace Tests\Modules\X206;

use App\Modules\X121\Models\Business;
use App\Modules\X206\Actions\CredentialFetchAction;
use App\Modules\X206\Actions\CredentialRevealAction;
use App\Modules\X206\Actions\CredentialRotateAction;
use App\Modules\X206\Actions\CredentialStoreAction;
use App\Modules\X206\Events\CredentialFailed;
use App\Modules\X206\Events\CredentialRevealed;
use App\Modules\X206\Events\CredentialStored;
use App\Modules\X206\Models\Credential;
use App\Modules\X206\Models\CredentialReveal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X206Test extends TestCase
{
    private CredentialStoreAction $storer;

    private CredentialFetchAction $fetcher;

    private CredentialRevealAction $revealer;

    private CredentialRotateAction $rotator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storer = new CredentialStoreAction;
        $this->fetcher = new CredentialFetchAction;
        $this->revealer = new CredentialRevealAction;
        $this->rotator = new CredentialRotateAction($this->storer);
    }

    /**
     * TEST ANCHOR
     * THE W0 EXIT CONDITION: a key still present in .env after wave 0's gate FAILS THE GATE.
     * And a tenant may reveal a credential they own; an attempt to reveal one they do not own is REFUSED and logged.
     */
    public function test_anchor_tenant_reveal_and_cross_tenant_refusal_logged(): void
    {
        Event::fake([CredentialStored::class, CredentialRevealed::class, CredentialFailed::class]);

        $bizA = Business::provision(['name' => 'Tenant Alpha', 'currency' => 'USD']);
        $bizB = Business::provision(['name' => 'Tenant Beta', 'currency' => 'USD']);

        // 1. Tenant A stores credential
        DB::statement("SET app.business_id = '{$bizA->id}'");
        $credA = $this->storer->handle($bizA->id, 'twilio', 'SK_SECRET_ALPHA_KEY_9999');

        // 2. Tenant A reveals credential they own -> permitted and decrypted
        $revealA = $this->revealer->handle($bizA->id, $credA->id);
        $this->assertEquals('permitted', $revealA['status']);
        $this->assertEquals('SK_SECRET_ALPHA_KEY_9999', $revealA['secret']);

        Event::assertDispatched(CredentialRevealed::class);

        // 3. Tenant B attempts to reveal Tenant A's credential -> refused and logged
        DB::statement("SET app.business_id = '{$bizB->id}'");
        $revealB = $this->revealer->handle($bizB->id, $credA->id);

        $this->assertEquals('refused', $revealB['status']);
        $this->assertEquals('UNAUTHORIZED_CROSS_TENANT', $revealB['reason']);
        $this->assertNull($revealB['secret']);

        Event::assertDispatched(CredentialFailed::class);

        $logB = CredentialReveal::where('business_id', $bizB->id)->where('status', 'refused')->first();
        $this->assertNotNull($logB);
        $this->assertEquals('UNAUTHORIZED_CROSS_TENANT', $logB->refusal_reason);
    }

    /**
     * [N-206-01] credential store and fetch
     */
    public function test_n_206_01_store_and_fetch(): void
    {
        $biz = Business::provision(['name' => 'Vault Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->storer->handle($biz->id, 'sendgrid', 'SG_API_KEY_SECRET');
        $secret = $this->fetcher->handle($biz->id, 'sendgrid');

        $this->assertEquals('SG_API_KEY_SECRET', $secret);
    }

    /**
     * [N-206-02] credential rotation
     */
    public function test_n_206_02_rotation(): void
    {
        $biz = Business::provision(['name' => 'Rotate Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->storer->handle($biz->id, 'postmark', 'OLD_KEY');
        $this->rotator->handle($biz->id, 'postmark', 'NEW_KEY');

        $secret = $this->fetcher->handle($biz->id, 'postmark');
        $this->assertEquals('NEW_KEY', $secret);
    }
}
