<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AlertOrigin;
use App\Enums\OperatorAlertKind;
use App\Enums\UserRole;
use App\Livewire\Admin\OperatorAlertBoard;
use App\Models\User;
use App\Services\Ops\OperatorAlerts;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class OperatorAlertBoardScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
    }

    public function test_get_operator_alert_board_renders_layout(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.operator-alerts'))
            ->assertOk()
            ->assertSee('Internal Platform Console');
    }

    public function test_empty_state_renders(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OperatorAlertBoard::class)
            ->assertSee('No alert is on record.');
    }

    public function test_alert_renders_with_distinctive_title(): void
    {
        $alerts = app(OperatorAlerts::class);
        $alerts->raise(OperatorAlertKind::PixelIngestRejects, 'DistinctiveAlert8823', 'summary', [], AlertOrigin::Request);

        Livewire::actingAs($this->admin)
            ->test(OperatorAlertBoard::class)
            ->assertSee('DistinctiveAlert8823');
    }
}
