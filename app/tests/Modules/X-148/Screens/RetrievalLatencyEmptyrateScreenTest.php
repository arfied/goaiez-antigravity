<?php

declare(strict_types=1);

namespace Tests\Modules\X148\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X148\Ui\RetrievalLatencyEmptyrate;
use Livewire\Livewire;
use Tests\TestCase;

class RetrievalLatencyEmptyrateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-148.retrieval-latency-emptyrate'))->assertOk();

        Livewire::test(RetrievalLatencyEmptyrate::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-148.retrieval-latency-emptyrate.admin'))->assertOk();

        Livewire::test(RetrievalLatencyEmptyrate::class)->assertOk();
    }
}
