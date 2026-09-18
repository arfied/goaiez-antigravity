<?php

declare(strict_types=1);

namespace Tests\Modules\X212\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X212\Models\MigrationFieldMap;
use App\Modules\X212\Models\MigrationRun;
use App\Modules\X212\Ui\UnmatchedfieldMap;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class UnmatchedfieldMapScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-212.unmatchedfield-map'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing to map yet.');

        Tenancy::setUser($owner->id);
        $run = MigrationRun::create([
            'business_id' => $biz->id,
            'source_system' => 'service_titan',
        ]);
        MigrationFieldMap::create([
            'business_id' => $biz->id,
            'migration_run_id' => $run->id,
            'source_field' => 'distinctive_source_field_4650',
            'target_entity' => 'person',
            'target_field' => 'first_name',
        ]);
        Tenancy::forget();

        $this->get(route('x-212.unmatchedfield-map'))
            ->assertOk()
            ->assertSee('distinctive_source_field_4650')
            ->assertDontSee('Nothing to map yet.');

        Livewire::test(UnmatchedfieldMap::class)->assertOk();
    }
}
