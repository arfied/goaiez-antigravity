<?php

declare(strict_types=1);

namespace Tests\Modules\X130\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X130\Ui\PublicIndexPages;
use Livewire\Livewire;
use Tests\TestCase;

class PublicIndexPagesScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-130.public-index-pages'))->assertOk();

        Livewire::test(PublicIndexPages::class)->assertOk();
    }
}
