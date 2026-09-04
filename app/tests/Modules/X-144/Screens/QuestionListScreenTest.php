<?php

namespace Tests\Modules\X144\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class QuestionListScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-144.question-list'))->assertOk();

        Livewire::test(\App\Modules\X144\Ui\QuestionList::class)->assertOk();
    }
}
