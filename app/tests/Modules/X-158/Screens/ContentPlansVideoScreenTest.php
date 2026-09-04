<?php

declare(strict_types=1);

namespace Tests\Modules\X158\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X158\Ui\ContentPlansVideo;
use Livewire\Livewire;
use Tests\TestCase;

class ContentPlansVideoScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-158.content-plans-video'))->assertOk();

        Livewire::test(ContentPlansVideo::class)->assertOk();
    }
}
