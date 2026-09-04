<?php

declare(strict_types=1);

namespace Tests\Modules\X183\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
