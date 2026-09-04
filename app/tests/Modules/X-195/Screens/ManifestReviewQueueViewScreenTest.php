<?php

namespace Tests\Modules\X195\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ManifestReviewQueueViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-195.manifest-review-queue'))->assertOk();

        Livewire::test(\App\Modules\X195\Ui\ManifestReviewQueueView::class)->assertOk();
    }
}
