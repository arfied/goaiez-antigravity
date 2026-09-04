<?php

namespace Tests\Modules\X110\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AbandonedFormsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-110.abandoned-forms'))->assertOk();

        Livewire::test(\App\Modules\X110\Ui\AbandonedForms::class)->assertOk();
    }
}
