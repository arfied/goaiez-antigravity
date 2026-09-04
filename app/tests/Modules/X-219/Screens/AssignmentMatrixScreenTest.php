<?php

namespace Tests\Modules\X219\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AssignmentMatrixScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-219.assignment-matrix'))->assertOk();

        Livewire::test(\App\Modules\X219\Ui\AssignmentMatrix::class)->assertOk();
    }
}
