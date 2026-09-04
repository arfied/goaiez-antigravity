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
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-219.assignment-matrix'))->assertOk();

        Livewire::test(\App\Modules\X219\Ui\AssignmentMatrix::class)->assertOk();
    }
}
