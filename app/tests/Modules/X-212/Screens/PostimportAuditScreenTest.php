<?php

declare(strict_types=1);

namespace Tests\Modules\X212\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X212\Models\MigrationReject;
use App\Modules\X212\Models\MigrationRun;
use App\Modules\X212\Ui\PostimportAudit;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PostimportAuditScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-212.postimport-audit'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing was rejected.');

        Tenancy::setUser($owner->id);
        $run = MigrationRun::create([
            'business_id' => $biz->id,
            'source_system' => 'distinctive_source_4631',
            'status' => 'dry_run_ready',
            'total_records' => 120,
            'imported_records' => 118,
            'rejected_records' => 2,
        ]);
        MigrationReject::create([
            'business_id' => $biz->id,
            'migration_run_id' => $run->id,
            'record_index' => 4631,
            'raw_data' => ['name' => 'Distinctive Row'],
            'rejection_reason' => 'distinctive weak identifier',
        ]);
        Tenancy::forget();

        $this->get(route('x-212.postimport-audit'))
            ->assertOk()
            ->assertSee('distinctive weak identifier')
            ->assertSee('4631')
            ->assertDontSee('Nothing was rejected.');

        Livewire::test(PostimportAudit::class)->assertOk();
    }
}
