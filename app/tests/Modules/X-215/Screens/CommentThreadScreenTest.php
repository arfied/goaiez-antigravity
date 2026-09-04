<?php

declare(strict_types=1);

namespace Tests\Modules\X215\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X215\Ui\CommentThread;
use Livewire\Livewire;
use Tests\TestCase;

class CommentThreadScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-215.comment-thread'))->assertOk();

        Livewire::test(CommentThread::class)->assertOk();
    }
}
