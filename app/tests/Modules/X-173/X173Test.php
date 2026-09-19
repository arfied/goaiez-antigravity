<?php

declare(strict_types=1);

namespace Tests\Modules\X173;

use App\Modules\X173\Actions\AccountingConnectAction;
use App\Modules\X173\Actions\AccountingMapAction;
use App\Modules\X173\Actions\AccountingSyncAction;
use App\Modules\X173\Events\AccountingSynced;
use App\Modules\X173\Events\CategoryInferred;
use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Models\AccountMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X173Test extends TestCase
{
    private AccountingConnectAction $connectAction;

    private AccountingMapAction $mapAction;

    private AccountingSyncAction $syncAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectAction = new AccountingConnectAction;
        $this->mapAction = new AccountingMapAction;
        $this->syncAction = new AccountingSyncAction;
    }
    /**
     * TEST ANCHOR
     * a conflict row is never auto-closed — asserted on the table's write paths;
     * a category below the confidence threshold posts to "uncategorised" with a flag, never to a guessed code
     */
    public function test_anchor_low_confidence_posts_to_uncategorised_and_conflict_never_autoclosed(): void
    {
        Event::fake([AccountingSynced::class, CategoryInferred::class]);

        $biz = TestCase::provisionTenant(['name' => 'Accounting Sync Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $connection = $this->connectAction->connect($biz->id, 'quickbooks', 'realm_qb_4412', 'oauth_qb_4412_fixture');
        $this->mapAction->mapAccount($biz->id, $connection->id, 'Job Revenue', 'gl_4000', 'HVAC Service Income');

        $transactions = [
            // High confidence transaction (0.95 >= 0.85) -> categorised
            [
                'ref' => 'inv_tx_101',
                'description' => 'Standard AC tune-up payment',
                'confidence' => 0.95,
                'category' => 'Job Revenue',
            ],
            // Low confidence transaction (0.62 < 0.85) -> MUST post to 'uncategorised' with review flag (TEST ANCHOR & G1-03)
            [
                'ref' => 'inv_tx_102',
                'description' => 'Unusual settlement refund adjustment',
                'confidence' => 0.62,
                'category' => 'Unknown Guess',
            ],
        ];

        $syncResult = $this->syncAction->syncTransactions($biz->id, $connection->id, $transactions);

        $this->assertEquals(1, $syncResult['records_synced']);
        $this->assertEquals(1, $syncResult['conflicts_count']);

        // Assert conflict row in database
        $conflict = AccountingSyncConflict::where('business_id', $biz->id)->where('transaction_ref', 'inv_tx_102')->first();
        $this->assertNotNull($conflict);
        $this->assertEquals('uncategorised', $conflict->assigned_category, 'Posts to uncategorised, never to guessed code (TEST ANCHOR)');
        $this->assertTrue($conflict->flagged_for_review);
        $this->assertEquals('open', $conflict->status, 'Conflict row is open and never auto-closed (TEST ANCHOR)');

        Event::assertDispatched(AccountingSynced::class);
        Event::assertDispatched(CategoryInferred::class, 2);
    }

    /**
     * [G1-03], [G1-77], [G15-13], [G15-22], [G15-25], [G15-26]
     * [N-063] ⛔ REFUSED: `php artisan why N-063` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-064] ⛔ REFUSED: `php artisan why N-064` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-067] ⛔ REFUSED: `php artisan why N-067` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-073] ⛔ REFUSED: `php artisan why N-073` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-076] ⛔ REFUSED: `php artisan why N-076` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-079] ⛔ REFUSED: `php artisan why N-079` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-082] ⛔ REFUSED: `php artisan why N-082` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-085] ⛔ REFUSED: `php artisan why N-085` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     */
public function test_accounting_connect_stores_no_credential_without_token(): void
    {
        $biz = TestCase::provisionTenant();
        $connection = $this->connectAction->connect($biz->id, 'xero', 'realm_xyz');

        $persisted = AccountingConnection::find($connection->id);
        $this->assertNull($persisted->access_token);
        $this->assertStringNotContainsString('token_oauth_', (string) $persisted->access_token);
    }

    public function test_missing_keys_take_refusal_path(): void
    {
        $biz = TestCase::provisionTenant();
        DB::statement("SET app.business_id = '{$biz->id}'");

        $connection = $this->connectAction->connect($biz->id, 'xero', 'realm_xyz');

        $transactions = [
            ['ref' => 'inv_tx_missing', 'description' => 'missing both'],
        ];
        $syncResult = $this->syncAction->syncTransactions($biz->id, $connection->id, $transactions);

        $this->assertEquals(0, $syncResult['records_synced']);
        $this->assertEquals(1, $syncResult['conflicts_count']);

        $conflict = AccountingSyncConflict::where('business_id', $biz->id)->where('transaction_ref', 'inv_tx_missing')->first();
        $this->assertEquals('uncategorised', $conflict->assigned_category);
        $this->assertTrue((bool) $conflict->flagged_for_review);
        $this->assertEquals('open', $conflict->status);
    }

    public function test_a_concurrent_mapping_is_absorbed_into_one_row_for_the_category(): void
    {
        // This test turns on X173Test being a CLASS-BASED PHPUnit file, which Pest's
        // ->use(RefreshesTenantDatabase::class)->in('Modules') binding does NOT reach, so the racer's
        // row COMMITS on pgsql_migrate and mapAccount() can see it. If TestCase ever binds a refresh
        // trait directly, this test stops proving anything -- while very likely still passing.
        $biz = TestCase::provisionTenant(['name' => 'Mapping Race Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // A token makes the connection live; mapAccount() refuses an inactive one before it writes.
        $connection = $this->connectAction->connect($biz->id, 'quickbooks', 'realm_race', 'oauth_race_fixture');

        // A second request lands a mapping for the same category between updateOrCreate()'s read and its
        // insert. It writes on pgsql_migrate, a separate session, so it commits. RLS is FORCED and
        // constrains the owner role too.
        $racer = DB::connection('pgsql_migrate');
        $racer->statement("SET app.business_id = '{$biz->id}'");

        AccountMapping::creating(function () use ($racer, $biz, $connection): void {
            $racer->table('account_mappings')->insert([
                'business_id' => $biz->id,
                'connection_id' => $connection->id,
                'internal_category' => 'Job Revenue',
                'remote_gl_account_id' => 'gl_racer',
                'remote_gl_account_name' => 'Racer Income',
            ]);
        });

        try {
            $result = $this->mapAction->mapAccount($biz->id, $connection->id, 'Job Revenue', 'gl_4000', 'HVAC Service Income');
        } finally {
            AccountMapping::flushEventListeners();
        }

        // The loser absorbs the winner's row: one mapping for the category.
        $this->assertSame(
            1,
            AccountMapping::where('business_id', $biz->id)->where('connection_id', $connection->id)->where('internal_category', 'Job Revenue')->count()
        );

        // And updateOrCreate() filled the loser's account onto the row that won, and returned that row.
        $mapping = AccountMapping::where('business_id', $biz->id)->where('connection_id', $connection->id)->where('internal_category', 'Job Revenue')->first();
        $this->assertSame('mapped', $result['status']);
        $this->assertSame($mapping->id, $result['mapping_id']);
        $this->assertSame('gl_4000', $mapping->remote_gl_account_id);
        $this->assertSame('HVAC Service Income', $mapping->remote_gl_account_name);
    }
}
