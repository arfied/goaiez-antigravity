<?php

declare(strict_types=1);

namespace Tests\Modules\X202\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X202\Ui\Slack;
use Livewire\Livewire;
use Tests\TestCase;

class SlackScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-202.slack'))->assertOk();

        Livewire::test(Slack::class)->assertOk();
    }
}
