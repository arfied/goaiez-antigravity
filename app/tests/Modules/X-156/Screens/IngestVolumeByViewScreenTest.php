<?php

declare(strict_types=1);

namespace Tests\Modules\X156\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X156\Models\IngestRun;
use App\Modules\X156\Models\IngestSource;
use App\Modules\X156\Ui\IngestVolumeByView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class IngestVolumeByViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-156.ingest-volume-by'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Tenancy::setUser($owner->id);
        $source = IngestSource::create([
            'business_id' => $biz->id,
            'source_type' => 'hubspot',
            'source_name' => 'Distinctive Source 4648',
        ]);
        IngestRun::create([
            'business_id' => $biz->id,
            'source_id' => $source->id,
            'attestation_id' => 'attest_distinctive_4648',
            'records_ingested' => 12,
        ]);

        $this->get(route('x-156.ingest-volume-by'))
            ->assertOk()
            ->assertSee('Distinctive Source 4648');

        Livewire::test(IngestVolumeByView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-156.ingest-volume-by.admin'))->assertOk();

        Livewire::test(IngestVolumeByView::class)->assertOk();
    }
}
