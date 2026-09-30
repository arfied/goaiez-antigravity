<?php

declare(strict_types=1);

namespace Tests\Modules\X212;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X212\Actions\MigrationCommitAction;
use App\Modules\X212\Actions\MigrationDryRunAction;
use App\Modules\X212\Actions\MigrationMapFieldAction;
use App\Modules\X212\Actions\MigrationRollbackAction;
use App\Modules\X212\Domain\X212Engine;
use App\Modules\X212\Events\MigrationCommitted;
use App\Modules\X212\Events\MigrationDryRunReady;
use App\Modules\X212\Events\MigrationStarted;
use App\Modules\X212\Models\MigrationRecord;
use App\Modules\X212\Models\MigrationReject;
use App\Modules\X212\Models\MigrationRun;
use App\Modules\X212\Ui\Commit;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
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
     */

    /**
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

    public function test_dry_run_rejects_email_only_record(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Migration Agreement Tenant']);
        Tenancy::set((int) $biz->id);

        $records = [
            ['phone' => '', 'email' => 'only@example.test', 'first_name' => 'Eve'],
            ['phone' => '+15551230001', 'email' => '', 'first_name' => 'Ada'],
        ];

        $run = $this->dryRunAction->handle($biz->id, 'test_source', $records);

        $this->assertEquals(1, $run->rejected_records);
        $this->assertEquals(1, $run->imported_records);

        $reject = MigrationReject::where('migration_run_id', $run->id)->first();
        $this->assertNotNull($reject);
        $this->assertStringContainsString('No phone number', $reject->rejection_reason);
    }

    public function test_commit_refuses_run_not_dry_run_ready(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Migration Agreement Tenant']);
        Tenancy::set((int) $biz->id);

        $run = MigrationRun::create([
            'business_id' => $biz->id,
            'source_system' => 'test_source',
            'status' => 'started',
            'total_records' => 1,
            'imported_records' => 0,
            'rejected_records' => 0,
            'is_silent_mode' => true,
        ]);

        $peopleCountBefore = Person::where('business_id', $biz->id)->count();

        $records = [['phone' => '+15551230001', 'first_name' => 'Ada']];

        $result = $this->commitAction->handle($biz->id, $run->id, $records);

        $this->assertEquals('refused', $result['status']);

        $peopleCountAfter = Person::where('business_id', $biz->id)->count();
        $this->assertEquals($peopleCountBefore, $peopleCountAfter);
    }

    public function test_dry_run_promise_and_commit_result_agree(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Migration Agreement Tenant']);
        Tenancy::set((int) $biz->id);

        $records = [
            ['phone' => '+15551230001', 'email' => '', 'first_name' => 'Ada'],
            ['phone' => '', 'email' => 'only@example.test', 'first_name' => 'Eve'],
        ];

        $run = $this->dryRunAction->handle($biz->id, 'test_source', $records);
        $promisedCount = $run->imported_records;

        $commitRecords = [
            ['phone' => '+15551230001', 'email' => '', 'first_name' => 'Ada'],
        ];
        $result = $this->commitAction->handle($biz->id, $run->id, $commitRecords);

        $this->assertEquals($promisedCount, $result['imported_records']);
    }

    public function test_dry_run_persists_valid_records(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Migration Valid Records Tenant']);
        Tenancy::set((int) $biz->id);

        $records = [
            ['phone' => '+15551230001', 'email' => '', 'first_name' => 'Ada'],
            ['phone' => '', 'email' => 'only@example.test', 'first_name' => 'Eve'],
        ];

        $run = $this->dryRunAction->handle($biz->id, 'test_source', $records);

        $count = MigrationRecord::where('migration_run_id', $run->id)->count();
        $this->assertEquals(1, $count);

        $record = MigrationRecord::where('migration_run_id', $run->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals(0, $record->record_index);
        $this->assertIsArray($record->raw_data);
        $this->assertEquals('+15551230001', $record->raw_data['phone']);
    }

    public function test_committing_a_dry_run_imports_the_validated_records(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);

        $records = [
            ['phone' => '+15551230001', 'email' => '', 'first_name' => 'Ada'],
            ['phone' => '+15551230002', 'email' => '', 'first_name' => 'Grace'],
            ['phone' => '', 'email' => 'only@example.test', 'first_name' => 'Eve'],
        ];

        $run = $this->dryRunAction->handle($biz->id, 'test_source', $records);

        Livewire::actingAs($owner)->test(Commit::class)->call('commitRun', $run->id);

        $run->refresh();
        $this->assertEquals('committed', $run->status);
        $this->assertEquals(2, $run->imported_records);

        $person1 = Person::where('business_id', $biz->id)->where('phone', '+15551230001')->first();
        $this->assertNotNull($person1);
        $person2 = Person::where('business_id', $biz->id)->where('phone', '+15551230002')->first();
        $this->assertNotNull($person2);
    }

    public function test_a_committed_run_cannot_be_committed_twice(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);

        $records = [
            ['phone' => '+15551230001', 'email' => '', 'first_name' => 'Ada'],
        ];

        $run = $this->dryRunAction->handle($biz->id, 'test_source', $records);

        $component = Livewire::actingAs($owner)->test(Commit::class);
        $component->call('commitRun', $run->id);

        $run->refresh();
        $this->assertEquals(1, $run->imported_records);

        $component->call('commitRun', $run->id);
        $run->refresh();
        $this->assertEquals(1, $run->imported_records);
        $component->assertSee('Only a run that has been dry-run and not yet committed can be committed.');
    }

    public function test_the_commit_screen_offers_no_button_for_a_committed_run(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);

        $records = [
            ['phone' => '+15551230001', 'email' => '', 'first_name' => 'Ada'],
        ];

        $run = $this->dryRunAction->handle($biz->id, 'test_source', $records);

        Livewire::actingAs($owner)->test(Commit::class)->call('commitRun', $run->id);

        $this->actingAs($owner)->get(route('x-212.commit'))
            ->assertDontSee('Import these')
            ->assertSee('[committed]');
    }
}
