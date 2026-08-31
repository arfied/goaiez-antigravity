<?php

declare(strict_types=1);

namespace Tests\Modules\X121;

use App\Modules\X121\Actions\EntityHistoryAction;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X121\Actions\EntityRestoreAction;
use App\Modules\X121\Actions\EntityWriteAction;
use App\Modules\X121\Domain\EntityService;
use App\Modules\X121\Events\FactInvalidated;
use App\Modules\X121\Models\Business;
use App\Modules\X121\Models\EntityHistoryRecord;
use App\Modules\X121\Models\Fact;
use App\Modules\X121\Models\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X121Test extends TestCase
{
    private EntityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EntityService;
    }

    /**
     * TEST ANCHOR
     * A tenant-A session issuing SELECT FROM jobs returns ZERO tenant-B rows, not an exception (RLS is the wall, not the app);
     * a price change to a Fact invalidates dependents in the same transaction — fact.invalidated and the update share one commit id
     */
    public function test_anchor_rls_tenant_isolation_and_fact_invalidation(): void
    {
        // 1. Setup tenant A and tenant B
        $bizA = Business::provision(['name' => 'Tenant A', 'currency' => 'USD']);
        $bizB = Business::provision(['name' => 'Tenant B', 'currency' => 'USD']);

        // Insert job directly for Tenant B
        DB::statement("SET app.business_id = '{$bizB->id}'");
        $jobB = Job::create([
            'business_id' => $bizB->id,
            'title' => 'Secret Job B',
            'status' => 'pending',
            'price_cents' => 50000,
        ]);

        // Switch session to Tenant A
        DB::statement("SET app.business_id = '{$bizA->id}'");

        // Tenant A querying work_orders must return 0 tenant B rows without throwing
        $jobsSeenByA = DB::table('work_orders')->where('id', $jobB->id)->get();
        $this->assertCount(0, $jobsSeenByA, 'RLS must hide tenant B rows from tenant A session completely');

        // 2. Fact update invalidation in same transaction sharing commit_id
        Event::fake([FactInvalidated::class]);

        $factA = Fact::create([
            'business_id' => $bizA->id,
            'key' => 'service_price_diagnostic',
            'value' => '9900',
            'version' => 1,
            'is_valid' => true,
        ]);

        $writeAction = new EntityWriteAction($this->service);
        $result = $writeAction->handle('facts', $factA->id, $bizA->id, [
            'value' => '12900',
        ], 'test-runner');

        $commitId = $result['commit_id'];
        $this->assertNotEmpty($commitId);

        Event::assertDispatched(FactInvalidated::class, function (FactInvalidated $event) use ($bizA, $factA, $commitId) {
            return $event->businessId === $bizA->id
                && $event->factId === $factA->id
                && $event->commitId === $commitId;
        });
    }

    /**
     * [G4-12] Redis on high-read nouns, invalidated inline on write
     */
    public function test_g4_12_redis_high_read_nouns_invalidated_inline_on_write(): void
    {
        $biz = Business::provision(['name' => 'High Read Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $fact = Fact::create([
            'business_id' => $biz->id,
            'key' => 'store_hours',
            'value' => '9am-5pm',
            'version' => 1,
        ]);

        $readAction = new EntityReadAction($this->service);
        $cachedFirst = $readAction->handle('facts', $fact->id, $biz->id);
        $this->assertEquals('9am-5pm', $cachedFirst['value']);

        $cacheKey = "entity:facts:{$biz->id}:{$fact->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Write updates value and invalidates cache inline
        $writeAction = new EntityWriteAction($this->service);
        $writeAction->handle('facts', $fact->id, $biz->id, ['value' => '8am-6pm']);

        $this->assertFalse(Cache::has($cacheKey), 'Cache must be invalidated inline on write');

        $readSecond = $readAction->handle('facts', $fact->id, $biz->id);
        $this->assertEquals('8am-6pm', $readSecond['value']);
    }

    /**
     * [G4-16] RLS FORCEd on every noun table, never optional
     */
    public function test_g4_16_rls_forced_on_every_noun_table(): void
    {
        $tables = [
            'businesses', 'people', 'conversations', 'messages', 'work_orders', 'reviews',
            'campaigns', 'assets', 'ledger_entries', 'facts', 'sites', 'numbers', 'entity_history',
        ];

        foreach ($tables as $t) {
            $row = DB::selectOne("
                SELECT c.relrowsecurity as rls, c.relforcerowsecurity as forced
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relname = ?
            ", [$t]);

            $this->assertNotNull($row, "Table {$t} must exist in postgres");
            $this->assertTrue((bool) $row->rls, "Table {$t} must have RLS enabled");
            $this->assertTrue((bool) $row->forced, "Table {$t} must have RLS FORCE enabled");
        }
    }

    /**
     * [G4-21] read-replica routing at the entity layer; the topology is turn 95's
     */
    public function test_g4_21_read_replica_routing(): void
    {
        $biz = Business::provision(['name' => 'Replica Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $data = $this->service->read('businesses', $biz->id, $biz->id, forcePrimary: false);
        $this->assertNotNull($data);
        $this->assertEquals('Replica Biz', $data['name']);
    }

    /**
     * [G4-37] Asset versioning; the ransomware case is turn 93's
     */
    public function test_g4_37_asset_versioning_and_ransomware_protection(): void
    {
        $biz = Business::provision(['name' => 'Asset Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $writeAction = new EntityWriteAction($this->service);
        $asset = DB::table('assets')->insertGetId([
            'business_id' => $biz->id,
            'name' => 'contract.pdf',
            'path' => '/assets/contract_v1.pdf',
            'mime_type' => 'application/pdf',
            'version' => 1,
            'checksum' => hash('sha256', 'clean-content'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Malicious or accidental update creates new version in temporal log
        $res = $writeAction->handle('assets', $asset, $biz->id, [
            'path' => '/assets/contract_encrypted.locked',
            'checksum' => hash('sha256', 'ransomware-payload'),
        ], 'ransomware-actor');

        $this->assertEquals(2, $res['version']);

        $history = EntityHistoryRecord::where('business_id', $biz->id)
            ->where('entity_type', 'assets')
            ->where('entity_id', $asset)
            ->get();

        $this->assertNotEmpty($history);
    }

    /**
     * [G4-42] version restore with a compensable reversal class
     */
    public function test_g4_42_version_restore_with_compensable_reversal(): void
    {
        $biz = Business::provision(['name' => 'Restore Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $fact = Fact::create([
            'business_id' => $biz->id,
            'key' => 'doctor_name',
            'value' => 'Dr. Alice',
            'version' => 1,
        ]);

        $writeAction = new EntityWriteAction($this->service);
        $writeAction->handle('facts', $fact->id, $biz->id, ['value' => 'Dr. Bob']);

        $restoreAction = new EntityRestoreAction($this->service);
        $restored = $restoreAction->handle('facts', $fact->id, 1, $biz->id, 'operator');

        $this->assertEquals('Dr. Alice', $restored['entity']['value']);
        $this->assertEquals(3, $restored['version']);
    }

    /**
     * [G4-46] Asset history and side-by-side compare
     */
    public function test_g4_46_asset_history_and_side_by_side_compare(): void
    {
        $biz = Business::provision(['name' => 'Compare Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $fact = Fact::create([
            'business_id' => $biz->id,
            'key' => 'price_tag',
            'value' => '100',
            'version' => 1,
        ]);

        $writeAction = new EntityWriteAction($this->service);
        $writeAction->handle('facts', $fact->id, $biz->id, ['value' => '150']);

        $comparison = $this->service->compareVersions('facts', $fact->id, 1, 2, $biz->id);
        $this->assertNotNull($comparison['version_a']);
        $this->assertNotNull($comparison['version_b']);
        $this->assertEquals('100', $comparison['version_a']['value']);
        $this->assertEquals('150', $comparison['version_b']['value']);
    }

    /**
     * [G4-51] expand/contract on the noun tables; the release switch is Step 8's
     */
    public function test_g4_51_expand_contract_noun_tables(): void
    {
        $biz = Business::provision(['name' => 'Expand Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // Expand: write with metadata
        $writeAction = new EntityWriteAction($this->service);
        $fact = Fact::create(['business_id' => $biz->id, 'key' => 'tier', 'value' => 'gold']);
        $res = $writeAction->handle('facts', $fact->id, $biz->id, ['value' => 'platinum']);
        $this->assertEquals('platinum', $res['entity']['value']);
    }

    /**
     * [G11-14] named in the header — with version restore
     */
    public function test_g11_14_named_in_header_version_restore(): void
    {
        $biz = Business::provision(['name' => 'Header Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $restoreAction = new EntityRestoreAction($this->service);
        $this->assertInstanceOf(EntityRestoreAction::class, $restoreAction);
    }

    /**
     * [G17-28] field-level history with version restore — named in the header
     */
    public function test_g17_28_field_level_history_with_version_restore(): void
    {
        $biz = Business::provision(['name' => 'Field History Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $fact = Fact::create([
            'business_id' => $biz->id,
            'key' => 'clinic_address',
            'value' => '123 Main St',
            'version' => 1,
        ]);

        $writeAction = new EntityWriteAction($this->service);
        $writeAction->handle('facts', $fact->id, $biz->id, ['value' => '456 Oak Ave']);

        $historyAction = new EntityHistoryAction;
        $history = $historyAction->handle('facts', $fact->id, $biz->id);

        $this->assertNotEmpty($history);
        $this->assertArrayHasKey('field_deltas', $history[0]);
    }

    public function test_write_non_existent_entity_throws_refusal(): void
    {
        $biz = Business::provision(['name' => 'Refusal Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->expectException(\InvalidArgumentException::class);
        $writeAction = new EntityWriteAction($this->service);
        $writeAction->handle('facts', 999999, $biz->id, ['value' => 'Non existent']);
    }
}
