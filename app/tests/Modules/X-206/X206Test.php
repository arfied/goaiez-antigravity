<?php

declare(strict_types=1);

namespace Tests\Modules\X206;

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
use Illuminate\Support\Facades\Log;
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

        $bizA = TestCase::provisionTenant(['name' => 'Tenant Alpha', 'currency' => 'USD']);
        $bizB = TestCase::provisionTenant(['name' => 'Tenant Beta', 'currency' => 'USD']);

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
        $biz = TestCase::provisionTenant(['name' => 'Vault Biz', 'currency' => 'USD']);
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
        $biz = TestCase::provisionTenant(['name' => 'Rotate Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->storer->handle($biz->id, 'postmark', 'OLD_KEY');
        $this->rotator->handle($biz->id, 'postmark', 'NEW_KEY');

        $secret = $this->fetcher->handle($biz->id, 'postmark');
        $this->assertEquals('NEW_KEY', $secret);
    }

    /** [N-044] */
    public function test_n_044_fetch_path_cross_tenant(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Vault A', 'currency' => 'USD']);
        $bizB = TestCase::provisionTenant(['name' => 'Vault B', 'currency' => 'USD']);

        DB::statement("SET app.business_id = '{$bizA->id}'");
        $this->storer->handle($bizA->id, 'twilio', 'SK_ONLY_ALPHA_MAY_READ_THIS');

        // control — from its own seat, the owner reads it
        $this->assertEquals('SK_ONLY_ALPHA_MAY_READ_THIS', $this->fetcher->handle($bizA->id, 'twilio'));

        DB::statement("SET app.business_id = '{$bizB->id}'");
        $this->assertNull($this->fetcher->handle($bizB->id, 'twilio'));   // B has none
        $this->assertNull($this->fetcher->handle($bizA->id, 'twilio'));   // B forging A's id
    }

    /** [N-045] */
    public function test_n_045_no_credential_in_exhaust(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Exhaust Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $secret = 'SK_LIVE_ZZQQXX_9182736450';
        Log::spy();
        Event::fake([CredentialStored::class, CredentialRevealed::class, CredentialFailed::class]);

        $cred = $this->storer->handle($biz->id, 'twilio', $secret);
        $this->fetcher->handle($biz->id, 'twilio');
        $this->revealer->handle($biz->id, $cred->id);
        $this->rotator->handle($biz->id, 'twilio', 'NEW_SECRET');

        // 1. The log
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');

        // 2. The event payloads
        Event::assertDispatched(CredentialStored::class, function ($e) use ($secret) {
            return ! str_contains(json_encode($e), $secret);
        });
        Event::assertDispatched(CredentialRevealed::class, function ($e) use ($secret) {
            return ! str_contains(json_encode($e), $secret);
        });

        // 3. The stored row
        $row = Credential::find($cred->id);
        $this->assertNotEquals($secret, $row->encrypted_secret);
        $this->assertStringNotContainsString($secret, $row->encrypted_secret);
        $this->assertLessThanOrEqual(7, strlen($row->key_hint));
        $this->assertStringNotContainsString($secret, $row->key_hint);

        $revealRow = CredentialReveal::where('credential_id', $cred->id)->first();
        $this->assertNotNull($revealRow, 'a permitted reveal writes its own audit row');
        $this->assertStringNotContainsString($secret, json_encode($revealRow->toArray()));
    }
}
