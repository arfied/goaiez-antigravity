<?php

declare(strict_types=1);

namespace Tests\Modules\X175\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X175\Ui\StafffacingAssistantPanel;
use Livewire\Livewire;
use Tests\TestCase;

class StafffacingAssistantPanelScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-175.stafffacing-assistant-panel'))->assertOk();

        Livewire::test(StafffacingAssistantPanel::class)->assertOk();
    }
}
