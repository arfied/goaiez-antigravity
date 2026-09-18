<?php

declare(strict_types=1);

namespace Tests\Modules\X212\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X212\Models\MigrationRun;
use App\Modules\X212\Ui\DryrunPreview;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DryrunPreviewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-212.dryrun-preview'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No imports yet.');

        Tenancy::setUser($owner->id);
        MigrationRun::create([
            'business_id' => $biz->id,
            'source_system' => 'distinctive_source_4631',
            'status' => 'dry_run_ready',
            'total_records' => 120,
            'imported_records' => 118,
            'rejected_records' => 2,
        ]);
        Tenancy::forget();

        $this->get(route('x-212.dryrun-preview'))
            ->assertOk()
            ->assertSee('distinctive_source_4631')
            ->assertSee('dry_run_ready')
            ->assertSee('118')
            ->assertSee('2')
            ->assertDontSee('No imports yet.');

        Livewire::test(DryrunPreview::class)->assertOk();
    }
}
