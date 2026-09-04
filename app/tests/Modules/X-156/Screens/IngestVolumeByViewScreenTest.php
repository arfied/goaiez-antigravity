<?php

declare(strict_types=1);

namespace Tests\Modules\X156\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X156\Ui\IngestVolumeByView;
use Livewire\Livewire;
use Tests\TestCase;

class IngestVolumeByViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-156.ingest-volume-by'))->assertOk();

        Livewire::test(IngestVolumeByView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-156.ingest-volume-by.admin'))->assertOk();

        Livewire::test(IngestVolumeByView::class)->assertOk();
    }
}
