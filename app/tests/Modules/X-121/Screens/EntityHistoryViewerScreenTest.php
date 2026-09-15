<?php

declare(strict_types=1);

namespace Tests\Modules\X121\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\EntityHistoryRecord;
use App\Modules\X121\Ui\EntityHistoryViewer;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class EntityHistoryViewerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-121.entity-history-viewer'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No history records yet.');

        Tenancy::setUser($owner->id);
        EntityHistoryRecord::create([
            'business_id' => $biz->id,
            'entity_type' => 'people',
            'entity_id' => 4489,
            'version' => 1,
            'field_deltas' => [],
            'snapshot' => [],
            'reversal_action' => 'entity.restore',
            'created_by' => 'test',
            'commit_id' => 'commit_distinctive_4489',
        ]);
        Tenancy::forget();

        $this->get(route('x-121.entity-history-viewer'))
            ->assertOk()
            ->assertSee('v1')
            ->assertSee('people #4489')
            ->assertDontSee('No history records yet.');

        Livewire::test(EntityHistoryViewer::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-121.entity-history-viewer.admin'))->assertOk();

        Livewire::test(EntityHistoryViewer::class)->assertOk();
    }
}
