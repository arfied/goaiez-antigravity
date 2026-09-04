<?php

namespace Tests\Modules\X189\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PreviewPerDestinationScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-189.preview-per-destination'))->assertOk();

        Livewire::test(\App\Modules\X189\Ui\PreviewPerDestination::class)->assertOk();
    }
}
