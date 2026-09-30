<?php

declare(strict_types=1);

namespace Tests\Modules\X212;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X212\Models\MigrationRecord;
use App\Modules\X212\Models\MigrationReject;
use App\Modules\X212\Models\MigrationRun;
use App\Modules\X212\Ui\UnmatchedfieldMap;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class MigrationRecheckTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_a_mapped_column_rescues_a_rejected_record(): void
    {

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $run = MigrationRun::create([
            'business_id' => $biz->id,
            'source_system' => 'service_titan',
            'status' => 'dry_run_ready',
        ]);
        $reject = MigrationReject::create([
            'business_id' => $biz->id,
            'migration_run_id' => $run->id,
            'record_index' => 4631,
            'raw_data' => ['mobile' => '5551230001', 'first_name' => 'Distinctive Row'],
            'rejection_reason' => 'No phone number — this import matches people by phone, so a record with only an email cannot be brought in',
        ]);

        Livewire::test(UnmatchedfieldMap::class)
            ->set('targetField.'.$run->id.'-mobile', 'phone')
            ->call('mapField', $run->id, 'mobile')
            ->assertSet('success', 'Mapped mobile to person.phone.')
            ->call('recheck', $run->id)
            ->assertSet('success', 'Resolved 1, still rejected 0, skipped 0 non-person maps.');

        $record = MigrationRecord::where('business_id', $biz->id)
            ->where('migration_run_id', $run->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('5551230001', $record->raw_data['phone']);

        $reject->refresh();
        $this->assertNotNull($reject->resolved_at);

        $run->refresh();
        $this->assertEquals(0, $run->rejected_records);
    }

    public function test_recheck_with_no_maps_refuses_and_changes_nothing(): void
    {

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $run = MigrationRun::create([
            'business_id' => $biz->id,
            'source_system' => 'service_titan',
            'status' => 'dry_run_ready',
        ]);
        $reject = MigrationReject::create([
            'business_id' => $biz->id,
            'migration_run_id' => $run->id,
            'record_index' => 4631,
            'raw_data' => ['mobile' => '5551230001', 'first_name' => 'Distinctive Row'],
            'rejection_reason' => 'No phone number — this import matches people by phone, so a record with only an email cannot be brought in',
        ]);

        Livewire::test(UnmatchedfieldMap::class)
            ->call('recheck', $run->id)
            ->assertSet('error', 'No field mappings for this import yet. Map a column first.');

        $this->assertEquals(0, MigrationRecord::where('migration_run_id', $run->id)->count());
        $reject->refresh();
        $this->assertNull($reject->resolved_at);
    }

    public function test_recheck_refuses_a_committed_run(): void
    {

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $run = MigrationRun::create([
            'business_id' => $biz->id,
            'source_system' => 'service_titan',
            'status' => 'committed',
        ]);
        $reject = MigrationReject::create([
            'business_id' => $biz->id,
            'migration_run_id' => $run->id,
            'record_index' => 4631,
            'raw_data' => ['mobile' => '5551230001', 'first_name' => 'Distinctive Row'],
            'rejection_reason' => 'No phone number — this import matches people by phone, so a record with only an email cannot be brought in',
        ]);

        Livewire::test(UnmatchedfieldMap::class)
            ->call('recheck', $run->id)
            ->assertSet('error', 'This run reads committed. Only a run that has been dry-run and not yet committed can be committed.');

        $this->assertEquals(0, MigrationRecord::where('migration_run_id', $run->id)->count());
        $reject->refresh();
        $this->assertNull($reject->resolved_at);
    }

    public function test_the_screen_lists_an_unmapped_column_and_stops_listing_it_once_mapped(): void
    {

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // We use Tenancy::setUser instead of Tenancy::set for route get because of how provisionTenant behaves.
        // As per the brief: "Tenancy::setUser() IS NOT A TENANT SWITCH... if a test needs a second tenant... Tenancy::set((int) $bizB->id)"
        Tenancy::setUser($owner->id);

        $run = MigrationRun::create([
            'business_id' => $biz->id,
            'source_system' => 'service_titan',
            'status' => 'dry_run_ready',
        ]);
        $reject = MigrationReject::create([
            'business_id' => $biz->id,
            'migration_run_id' => $run->id,
            'record_index' => 4631,
            'raw_data' => ['mobile' => '5551230001', 'first_name' => 'Distinctive Row'],
            'rejection_reason' => 'No phone number — this import matches people by phone, so a record with only an email cannot be brought in',
        ]);

        Tenancy::forget();

        $this->get(route('x-212.unmatchedfield-map'))
            ->assertOk()
            ->assertSee('mobile');

        // Map it
        Tenancy::set((int) $biz->id);
        Livewire::test(UnmatchedfieldMap::class)
            ->set('targetField.'.$run->id.'-mobile', 'phone')
            ->call('mapField', $run->id, 'mobile');
        Tenancy::forget();

        $this->get(route('x-212.unmatchedfield-map'))
            ->assertOk()
            ->assertDontSee('wire:click="mapField('.$run->id.', \'mobile\')"', false);
    }
}
