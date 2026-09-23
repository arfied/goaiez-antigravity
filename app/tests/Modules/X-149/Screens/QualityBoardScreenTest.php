<?php

declare(strict_types=1);

namespace Tests\Modules\X149\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X149\Ui\QualityBoard;
use Livewire\Livewire;
use Tests\TestCase;

class QualityBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-149.quality-board'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(QualityBoard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-149.quality-board.admin'))->assertOk();

        Livewire::test(QualityBoard::class)->assertOk();
    }

    public function test_can_record_quality(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(QualityBoard::class)
            ->set('currentRefusalRate', '0.05')
            ->set('baselineRefusalRate', '0.10')
            ->call('recordQuality')
            ->assertSet('error', null)
            ->assertSet('success', 'Recorded quality series. Anomaly detected: refusal_rate.fell. This feeds the quality lists; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('quality_series', [
            'business_id' => $biz->id,
            'anomaly_detected' => 1,
        ]);

        $this->get(route('x-149.quality-board'))
            ->assertOk()
            ->assertSee('refusal_rate.fell')
            ->assertDontSee('No quality metrics recorded.');

    }

    public function test_refuses_invalid_quality(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(QualityBoard::class)
            ->set('currentRefusalRate', '')
            ->set('baselineRefusalRate', '0.10')
            ->call('recordQuality')
            ->assertSet('error', 'Valid rates are required.');

        $this->assertDatabaseMissing('quality_series', [
            'business_id' => $biz->id,
        ]);
    }
}
