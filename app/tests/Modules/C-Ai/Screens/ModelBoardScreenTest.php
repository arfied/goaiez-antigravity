<?php

namespace Tests\Modules\CAi\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ModelBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-ai.model-board'))->assertOk();

        Livewire::test(\App\Modules\CAi\Ui\ModelBoard::class)->assertOk();
    }
}
