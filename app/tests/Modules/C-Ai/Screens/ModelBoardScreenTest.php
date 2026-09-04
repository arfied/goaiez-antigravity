<?php

declare(strict_types=1);

namespace Tests\Modules\CAi\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAi\Ui\ModelBoard;
use Livewire\Livewire;
use Tests\TestCase;

class ModelBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-ai.model-board'))->assertOk();

        Livewire::test(ModelBoard::class)->assertOk();
    }
}
