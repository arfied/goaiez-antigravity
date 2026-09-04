<?php

declare(strict_types=1);

namespace Tests\Modules\X153\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X153\Ui\AlertReplyBy;
use Livewire\Livewire;
use Tests\TestCase;

class AlertReplyByScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-153.alert-reply-by'))->assertOk();

        Livewire::test(AlertReplyBy::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-153.alert-reply-by.admin'))->assertOk();

        Livewire::test(AlertReplyBy::class)->assertOk();
    }
}
