<?php

namespace Tests\Modules\X206\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RevealLogScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-206.reveal-log'))->assertOk();

        Livewire::test(\App\Modules\X206\Ui\RevealLog::class)->assertOk();
    }
}
