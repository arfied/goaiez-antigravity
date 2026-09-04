<?php

namespace Tests\Modules\X215\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CommentThreadScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-215.comment-thread'))->assertOk();

        Livewire::test(\App\Modules\X215\Ui\CommentThread::class)->assertOk();
    }
}
