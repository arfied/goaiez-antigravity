<?php

declare(strict_types=1);

namespace Tests\Modules\X110\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X110\Ui\TagVersionPer;
use Livewire\Livewire;
use Tests\TestCase;

class TagVersionPerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-110.tag-version-per'))->assertOk();

        Livewire::test(TagVersionPer::class)->assertOk();
    }
}
