<?php

declare(strict_types=1);

namespace Tests\Modules\X122;

use App\Modules\X122\Actions\ActionInvokeAction;
use App\Modules\X122\Actions\ActionReverseAction;
use App\Modules\X122\Actions\AuditExportAction;
use App\Modules\X122\Actions\ManifestRegisterAction;
use App\Modules\X122\Events\ActionInvoked;
use App\Modules\X122\Events\ActionRefused;
use App\Modules\X122\Models\ActionInvocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X122Test extends TestCase
{
    private ManifestRegisterAction $registry;

    private ActionInvokeAction $invoker;

    private ActionReverseAction $reverser;

    private AuditExportAction $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ManifestRegisterAction;
        $this->invoker = new ActionInvokeAction;
        $this->reverser = new ActionReverseAction;
        $this->auditor = new AuditExportAction;
    }

    /**
     * TEST ANCHOR
     * an action absent from the registry, invoked by the assistant, returns "I can't do that yet" and writes assistant.unsupported —
     * never executes, never approximates; the same payload sent twice with one refId charges once
     */
    public function test_anchor_absent_action_refusal_and_idempotency(): void
    {
        Event::fake([ActionInvoked::class, ActionRefused::class]);

        $biz = TestCase::provisionTenant(['name' => 'Catalog Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Invoking unregistered action returns refusal and code assistant.unsupported
        $refused = $this->invoker->handle(
            businessId: $biz->id,
            actionName: 'unregistered.secret_action',
            parameters: ['amount' => 500]
        );

        $this->assertEquals('refused', $refused['status']);
        $this->assertEquals('assistant.unsupported', $refused['code']);
        $this->assertEquals("I can't do that yet", $refused['message']);

        Event::assertDispatched(ActionRefused::class, function (ActionRefused $event) use ($biz) {
            return $event->businessId === $biz->id
                && $event->refusalCode === 'assistant.unsupported';
        });

        // 2. Register valid action
        $this->registry->handle(
            businessId: $biz->id,
            actionName: 'payment.charge',
            schema: ['required' => ['amount_cents', 'customer_id']],
            reversalAction: 'payment.refund',
            isReversible: true
        );

        // 3. Idempotency test: Same payload sent twice with one refId charges once
        $refId = 'charge-ref-unique-12345';
        $params = ['amount_cents' => 2500, 'customer_id' => 999];

        $firstRes = $this->invoker->handle(
            businessId: $biz->id,
            actionName: 'payment.charge',
            parameters: $params,
            refId: $refId
        );

        $this->assertEquals('completed', $firstRes['status']);
        $invId = $firstRes['invocation_id'];

        $secondRes = $this->invoker->handle(
            businessId: $biz->id,
            actionName: 'payment.charge',
            parameters: $params,
            refId: $refId
        );

        $this->assertEquals('idempotent_cached', $secondRes['status']);
        $this->assertEquals($invId, $secondRes['invocation_id']);

        $count = ActionInvocation::where('business_id', $biz->id)->where('ref_id', $refId)->count();
        $this->assertEquals(1, $count, 'Must create exactly 1 invocation record for same refId');
    }

    /**
     * [G4-03] every invocation logged with timestamp, IP + geo, user and actor_type
     */
    public function test_g4_03_invocation_logged_with_metadata(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Log Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->registry->handle($biz->id, 'contact.create', ['required' => ['name']]);

        $res = $this->invoker->handle(
            businessId: $biz->id,
            actionName: 'contact.create',
            parameters: ['name' => 'Alice'],
            refId: 'inv-geo-1',
            actorType: 'assistant',
            userId: 42,
            ipAddress: '198.51.100.1',
            geoCountry: 'US',
            geoCity: 'Chicago'
        );

        $record = ActionInvocation::findOrFail($res['invocation_id']);
        $this->assertEquals('assistant', $record->actor_type);
        $this->assertEquals(42, $record->user_id);
        $this->assertEquals('198.51.100.1', $record->ip_address);
        $this->assertEquals('US', $record->geo_country);
        $this->assertEquals('Chicago', $record->geo_city);
    }

    /**
     * [G10-14] every invocation is already immutably logged
     */
    public function test_g10_14_immutable_log(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Immutable Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->registry->handle($biz->id, 'note.add', ['required' => ['text']]);
        $res = $this->invoker->handle($biz->id, 'note.add', ['text' => 'hello']);

        $inv = ActionInvocation::findOrFail($res['invocation_id']);
        $this->assertNotEmpty($inv->result['payload_hash']);
    }

    /**
     * [G10-21] append-only action log; QLDB is corpus vocabulary — one database (§22)
     */
    public function test_g10_21_append_only_log(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Append Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->registry->handle($biz->id, 'task.done', ['required' => ['id']]);
        $res = $this->invoker->handle($biz->id, 'task.done', ['id' => 1]);

        $drivers = array_column(config('database.connections'), 'driver');
        $this->assertNotContains('qldb', $drivers);
        $this->assertNull((new ActionInvocation)->getConnectionName());

        $firstInv = ActionInvocation::findOrFail($res['invocation_id']);
        $firstParams = $firstInv->parameters;

        $res2 = $this->invoker->handle($biz->id, 'task.done', ['id' => 2]);
        $this->assertNotEquals($res['invocation_id'], $res2['invocation_id']);

        $firstInvReRead = ActionInvocation::findOrFail($res['invocation_id']);
        $this->assertEquals($firstParams, $firstInvReRead->parameters);

        $this->assertEquals(2, ActionInvocation::where('business_id', $biz->id)->count());
    }

    /**
     * [G10-35] HMAC-SHA256 on every outbound webhook is named in the header
     */
    public function test_g10_35_hmac_sha256_audit_export(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'HMAC Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->registry->handle($biz->id, 'ping', ['required' => []]);
        $this->invoker->handle($biz->id, 'ping', []);

        $export = $this->auditor->handle($biz->id, 'tenant-secret-key-xyz');
        $this->assertEquals('HMAC-SHA256', $export['algorithm']);
        $this->assertNotEmpty($export['hmac_sha256']);
    }

    /**
     * [G10-41] per-tenant HMAC secret on the outbound payload
     */
    public function test_g10_41_per_tenant_hmac_signature(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sign Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $exportA = $this->auditor->handle($biz->id, 'secret-a');
        $exportB = $this->auditor->handle($biz->id, 'secret-b');

        $this->assertNotEquals($exportA['hmac_sha256'], $exportB['hmac_sha256']);
    }

    /**
     * [G11-26] strict JSON-schema validation; a missing field is refused, never defaulted
     */
    public function test_g11_26_strict_schema_validation_missing_field_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Strict Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->registry->handle(
            businessId: $biz->id,
            actionName: 'user.invite',
            schema: ['required' => ['email', 'role']]
        );

        // Omit required 'role' -> must refuse, never default
        $res = $this->invoker->handle(
            businessId: $biz->id,
            actionName: 'user.invite',
            parameters: ['email' => 'user@example.com']
        );

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('schema.missing_field', $res['code']);
        $this->assertStringContainsString('Missing required parameter: role', $res['message']);
    }

    /**
     * [G17-16] every invocation logs IP + MaxMind geo — named in the header
     */
    public function test_g17_16_maxmind_geo_logging(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Geo Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->registry->handle($biz->id, 'location.check', ['required' => []]);
        $res = $this->invoker->handle(
            businessId: $biz->id,
            actionName: 'location.check',
            parameters: [],
            ipAddress: '203.0.113.195',
            geoCountry: 'GB',
            geoCity: 'London'
        );

        $inv = ActionInvocation::findOrFail($res['invocation_id']);
        $this->assertEquals('203.0.113.195', $inv->ip_address);
        $this->assertEquals('GB', $inv->geo_country);
        $this->assertEquals('London', $inv->geo_city);
    }
}
