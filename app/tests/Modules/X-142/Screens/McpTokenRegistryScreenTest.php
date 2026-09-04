<?php

declare(strict_types=1);

namespace Tests\Modules\X142\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X142\Ui\McpTokenRegistry;
use Livewire\Livewire;
use Tests\TestCase;

class McpTokenRegistryScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-142.mcp-token-registry'))->assertOk();

        Livewire::test(McpTokenRegistry::class)->assertOk();
    }
}
