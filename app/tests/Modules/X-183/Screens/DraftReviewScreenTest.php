<?php

namespace Tests\Modules\X183\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DraftReviewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-183.draft-review'))->assertOk();

        Livewire::test(\App\Modules\X183\Ui\DraftReview::class)->assertOk();
    }
}
