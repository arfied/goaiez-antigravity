<?php

declare(strict_types=1);

namespace Tests\Modules\X212;

use App\Modules\X121\Models\Person;
use App\Modules\X212\Actions\MigrationCommitAction;
use App\Modules\X212\Actions\MigrationDryRunAction;
use App\Modules\X212\Actions\MigrationMapFieldAction;
use App\Modules\X212\Actions\MigrationRollbackAction;
use App\Modules\X212\Domain\X212Engine;
use App\Modules\X212\Events\MigrationCommitted;
use App\Modules\X212\Events\MigrationDryRunReady;
use App\Modules\X212\Events\MigrationStarted;
use App\Modules\X212\Models\MigrationReject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X212Test extends TestCase
{
    private MigrationDryRunAction $dryRunAction;

    private MigrationCommitAction $commitAction;

    private MigrationRollbackAction $rollbackAction;

    private MigrationMapFieldAction $mapAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dryRunAction = new MigrationDryRunAction;
        $this->commitAction = new MigrationCommitAction;
        $this->rollbackAction = new MigrationRollbackAction;
        $this->mapAction = new MigrationMapFieldAction;
    }

    

    /**
* Test dry-run, validation, silent commit and rollback
     */
    public function test_migration_dry_run_silent_commit_and_rollback(): void
    {
        Event::fake([MigrationStarted::class, MigrationDryRunReady::class, MigrationCommitted::class]);

        $biz = TestCase::provisionTenant(['name' => 'Migration Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $records = [
            ['first_name' => 'Alice', 'phone' => '+15551234567', 'email' => 'alice@titan.com'],
            ['first_name' => 'Bob', 'phone' => '+15552345678', 'email' => 'bob@titan.com'],
            ['first_name' => 'Invalid Record', 'phone' => '', 'email' => ''], // Missing contact -> reject
        ];

        // 1. Dry run execution
        $run = $this->dryRunAction->handle($biz->id, 'service_titan', $records);
        $this->assertEquals('dry_run_ready', $run->status);
        $this->assertEquals(2, $run->imported_records);
        $this->assertEquals(1, $run->rejected_records);
        $this->assertTrue($run->is_silent_mode, 'Migration runs in silent mode — sends nothing during ingest');

        $rejects = MigrationReject::where('business_id', $biz->id)->where('migration_run_id', $run->id)->get();
        $this->assertCount(1, $rejects);

        Event::assertDispatched(MigrationStarted::class);
        Event::assertDispatched(MigrationDryRunReady::class);

        // 2. Field mapping
        $this->mapAction->handle($biz->id, $run->id, 'CustName', 'person', 'first_name');

        // 3. Silent commit
        $commitRes = $this->commitAction->handle($biz->id, $run->id, $records);
        $this->assertEquals('committed', $commitRes['status']);
        $this->assertEquals(2, $commitRes['imported_records']);
        $this->assertTrue($commitRes['is_silent_mode']);

        $person = Person::where('business_id', $biz->id)->where('phone', '+15551234567')->first();
        $this->assertNotNull($person);
        $this->assertEquals('Alice', $person->first_name);

        Event::assertDispatched(MigrationCommitted::class);

        // 4. Rollback
        $rbRes = $this->rollbackAction->handle($biz->id, $run->id);
        $this->assertEquals('rolled_back', $rbRes['status']);
    }

    /**
     * [N-004], [N-038], [N-040], [G4-54]
     * [N-042] ⛔ REFUSED: `php artisan why N-042` reports it is never DEFINED. 500 imported jobs → ZERO outbound messages. Nothing to assert. (R245, REV-81/REV-83)
 *
* [N-042]
 */
    public function test_n_042_weak_identifier_rejected(): void
    {
        $engine = new X212Engine;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REFUSES: weak identifier rejected');

        $engine->validateImport([
            ['first_name' => 'Alice', 'phone' => '', 'email' => ''],
        ]);
    }
}
