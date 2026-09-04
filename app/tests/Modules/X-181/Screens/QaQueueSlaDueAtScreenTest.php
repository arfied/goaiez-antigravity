<?php

declare(strict_types=1);

namespace Tests\Modules\X181\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X181\Ui\QaQueueSlaDueAt;
use Livewire\Livewire;
use Tests\TestCase;

class QaQueueSlaDueAtScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-181.qa-queue-sladueat'))->assertOk();

        Livewire::test(QaQueueSlaDueAt::class)->assertOk();
    }
}
