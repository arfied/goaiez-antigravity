<?php

namespace Tests\Modules\X220\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PromptHistoryScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-220.prompt-history'))->assertOk();

        Livewire::test(\App\Modules\X220\Ui\PromptHistory::class)->assertOk();
    }
}
