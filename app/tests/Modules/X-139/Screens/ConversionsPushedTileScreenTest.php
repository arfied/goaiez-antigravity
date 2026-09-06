<?php

declare(strict_types=1);

namespace Tests\Modules\X139\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X139\Ui\ConversionsPushedTile;
use Livewire\Livewire;
use Tests\TestCase;

class ConversionsPushedTileScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-139.conversions-pushed-tile'))->assertOk();

        Livewire::test(ConversionsPushedTile::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-139.conversions-pushed-tile.admin'))->assertOk();

        Livewire::test(ConversionsPushedTile::class)->assertOk();
    }

    public function test_component_shows_count_and_summed_value(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        // Empty state
        Livewire::test(ConversionsPushedTile::class, ['businessId' => $biz->id])
            ->assertSee('Uploaded Conversions: 0 ($0.00)');

        \App\Support\Tenancy::actingAs($biz->id, function () use ($biz) {
            \App\Modules\X139\Models\ConversionUpload::forceCreate([
                'business_id' => $biz->id,
                'job_id' => 101,
                'conversion_value_cents' => 45678900,
                'gclid_or_fbc' => 'gclid_1',
                'status' => 'uploaded',
            ]);
            \App\Modules\X139\Models\ConversionUpload::forceCreate([
                'business_id' => $biz->id,
                'job_id' => 102,
                'conversion_value_cents' => 100000,
                'gclid_or_fbc' => 'gclid_2',
                'status' => 'uploaded',
            ]);
            \App\Modules\X139\Models\ConversionUpload::forceCreate([
                'business_id' => $biz->id,
                'job_id' => 103,
                'conversion_value_cents' => 500000,
                'gclid_or_fbc' => 'gclid_3',
                'status' => 'pending',
            ]);
        });

        $this->actingAs($owner);
        \App\Support\Tenancy::set((int) $biz->id);

        // 456789.00 + 1000.00 = 457789.00
        Livewire::test(ConversionsPushedTile::class, ['businessId' => $biz->id])
            ->assertSee('Uploaded Conversions: 2 ($457,789.00)');
    }
}
