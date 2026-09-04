<?php

declare(strict_types=1);

namespace Tests\Modules\X142\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class McpTokenRegistryScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-142.mcp-token-registry'))->assertOk();

        Livewire::test(\App\Modules\X142\Ui\McpTokenRegistry::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-142.mcp-token-registry.admin'))->assertOk();

        Livewire::test(\App\Modules\X142\Ui\McpTokenRegistry::class)->assertOk();
    }
}
