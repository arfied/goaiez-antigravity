<?php

namespace Tests\Modules\CTelephony\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class FailoverLogScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-telephony.failover-log'))->assertOk();

        Livewire::test(\App\Modules\CTelephony\Ui\FailoverLog::class)->assertOk();
    }
}
