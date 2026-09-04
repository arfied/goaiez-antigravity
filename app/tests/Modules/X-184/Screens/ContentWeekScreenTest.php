<?php

declare(strict_types=1);

namespace Tests\Modules\X184\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X184\Ui\ContentWeek;
use Livewire\Livewire;
use Tests\TestCase;

class ContentWeekScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-184.content-week'))->assertOk();

        Livewire::test(ContentWeek::class)->assertOk();
    }
}
