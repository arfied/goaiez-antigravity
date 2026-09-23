<?php

declare(strict_types=1);

namespace Tests\Modules\X212\Screens;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Modules\X212\Models\MigrationReject;
use App\Modules\X212\Models\MigrationRun;
use App\Modules\X212\Ui\PickSource;
use Livewire\Livewire;
use Tests\TestCase;

class PickSourceScreenTest extends TestCase
{
    public function test_a_real_get_renders_the_owner_shell(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-212.pick-source'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertSee('Import from another system')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(PickSource::class)->assertOk();
    }

    public function test_a_dry_run_records_a_run_and_rejects_rows_without_a_contact(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Livewire::actingAs($owner)->test(PickSource::class)
            ->set('sourceSystem', 'jobber')
            ->set('csv', "first_name,phone,email\nDistinctive Row 7731,+15125567731,\nNo Contact 7732,,\nEmail Only 7733,,seven7733@example.test")
            ->call('dryRun')
            ->assertHasNoErrors();

        $run = MigrationRun::where('business_id', $biz->id)->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('jobber', $run->source_system);
        $this->assertSame('dry_run_ready', $run->status);
        $this->assertSame(3, $run->total_records);
        $this->assertSame(2, $run->imported_records);
        $this->assertSame(1, $run->rejected_records);
        $this->assertTrue($run->is_silent_mode);

        $this->assertSame(1, MigrationReject::where('migration_run_id', $run->id)->count());
        $this->assertSame(1, (int) MigrationReject::where('migration_run_id', $run->id)->first()->record_index);

        $this->actingAs($owner)->get(route('x-212.dryrun-preview'))->assertSee('jobber');
    }

    public function test_nothing_is_imported_by_a_dry_run(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Livewire::actingAs($owner)->test(PickSource::class)
            ->set('sourceSystem', 'jobber')
            ->set('csv', "first_name,phone,email\nDistinctive Row 7731,+15125567731,\nNo Contact 7732,,\nEmail Only 7733,,seven7733@example.test")
            ->call('dryRun')
            ->assertHasNoErrors();

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_a_header_only_paste_is_refused_and_writes_nothing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Livewire::actingAs($owner)->test(PickSource::class)
            ->set('csv', 'first_name,phone')
            ->call('dryRun')
            ->assertHasErrors(['csv']);

        $this->assertSame(0, MigrationRun::count());
    }
}
