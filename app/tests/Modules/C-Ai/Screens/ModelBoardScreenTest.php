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
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('c-ai.model-board'))->assertOk();

        Livewire::test(\App\Modules\CAi\Ui\ModelBoard::class)->assertOk();
    }
}
