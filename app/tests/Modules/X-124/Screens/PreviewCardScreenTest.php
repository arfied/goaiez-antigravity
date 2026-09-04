<?php

namespace Tests\Modules\X124\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PreviewCardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-124.preview-card'))->assertOk();

        Livewire::test(\App\Modules\X124\Ui\PreviewCard::class)->assertOk();
    }
}
