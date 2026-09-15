<?php

declare(strict_types=1);

namespace Tests\Modules\X203\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X203\Models\RestoreTest;
use App\Modules\X203\Ui\RestorationtestLog;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RestorationtestLogScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-203.restorationtest-log'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No restore tests logged.');

        Tenancy::setUser($owner->id);
        RestoreTest::create([
            'business_id' => $biz->id,
            'backup_id' => 'backup_distinctive_4493',
            'expected_checksum' => 'sha_a_4493',
            'actual_checksum' => 'sha_b_4493',
            'expected_row_count' => 4493,
            'restored_row_count' => 4493,
            'status' => 'failed_checksum_mismatch',
            'failure_reason' => 'Distinctive reason 4493'
        ]);
        Tenancy::forget();

        $this->get(route('x-203.restorationtest-log'))
            ->assertOk()
            ->assertSee('Backup backup_distinctive_4493')
            ->assertSee('[failed_checksum_mismatch]')
            ->assertSee('Distinctive reason 4493')
            ->assertDontSee('No restore tests logged.');

        Livewire::test(RestorationtestLog::class)->assertOk();
    }
}
