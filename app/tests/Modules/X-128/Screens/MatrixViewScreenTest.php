<?php

declare(strict_types=1);

namespace Tests\Modules\X128\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X128\Models\IntegrationMatrix;
use App\Modules\X128\Ui\MatrixView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class MatrixViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-128.matrix-view'))->assertOk();

        Livewire::test(MatrixView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-128.matrix-view.admin'))->assertOk();

        Livewire::test(MatrixView::class)->assertOk();
    }

    public function test_can_generate_the_integration_matrix(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set($biz->id);

        $component = Livewire::test(MatrixView::class)
            ->call('generateMatrix');

        $this->assertStringStartsWith('Matrix generated: ', $component->get('success'));

        $this->assertDatabaseHas('integration_matrix', ['business_id' => $biz->id]);
    }

    public function test_generated_matrix_stores_a_checksum_of_the_manifest_scan(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set($biz->id);

        Livewire::test(MatrixView::class)->call('generateMatrix');

        $matrix = IntegrationMatrix::where('business_id', $biz->id)->first();
        $this->assertNotNull($matrix);
        $this->assertEquals(64, strlen($matrix->checksum));
    }

    public function test_matrix_view_shows_the_generated_matrix(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set($biz->id);
        Livewire::test(MatrixView::class)->call('generateMatrix');
        Tenancy::forget();

        $this->actingAs($owner);
        $this->get(route('x-128.matrix-view'))
            ->assertOk()
            ->assertDontSee('No matrix generated yet.')
            ->assertSee('Orphans:');
    }
}
