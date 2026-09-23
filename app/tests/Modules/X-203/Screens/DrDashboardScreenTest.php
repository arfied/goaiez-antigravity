<?php

declare(strict_types=1);

namespace Tests\Modules\X203\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X203\Models\RestoreTest;
use App\Modules\X203\Ui\DrDashboard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DrDashboardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-203.dr-dashboard'))->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No restore tests executed.');

        Tenancy::setUser($owner->id);
        RestoreTest::create([
            'business_id' => $biz->id,
            'backup_id' => 'backup_distinctive_4489',
            'expected_checksum' => 'sha_distinctive_4489',
            'actual_checksum' => 'sha_distinctive_4489',
            'expected_row_count' => 4489,
            'restored_row_count' => 4489,
            'status' => 'passed',
        ]);
        Tenancy::forget();

        $this->get(route('x-203.dr-dashboard'))->assertOk()
            ->assertSee('Backup backup_distinctive_4489')
            ->assertSee('[passed]')
            ->assertSee('Rows: 4489')
            ->assertDontSee('No restore tests executed.');

        Livewire::test(DrDashboard::class)->assertOk();
    }

    public function test_control_writes_and_clears_empty_states(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $table = (new RestoreTest)->getTable();

        Livewire::test(DrDashboard::class)
            ->set('backupId', 'backup_123')
            ->set('expectedChecksum', 'abc')
            ->set('actualChecksum', 'def')
            ->set('expectedRowCount', '100')
            ->set('restoredRowCount', '90')
            ->call('recordTest')
            ->assertSet('backupId', '')
            ->assertSee('Recorded restore test outcome');

        $this->assertDatabaseHas($table, [
            'business_id' => $biz->id,
            'backup_id' => 'backup_123',
            'expected_row_count' => 100,
        ]);

        $this->get(route('x-203.dr-dashboard'))
            ->assertSee('Backup backup_123')
            ->assertDontSee('No restore tests executed.');

        $this->get(route('x-203.restorationtest-log'))
            ->assertDontSee('No restore tests logged.');
    }

    public function test_control_refuses_empty_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $table = (new RestoreTest)->getTable();

        Livewire::test(DrDashboard::class)
            ->set('backupId', '')
            ->set('expectedChecksum', 'abc')
            ->call('recordTest')
            ->assertSet('error', 'Backup ID is required.');
        
        $this->assertDatabaseMissing($table, [
            'expected_checksum' => 'abc',
        ]);
    }
}