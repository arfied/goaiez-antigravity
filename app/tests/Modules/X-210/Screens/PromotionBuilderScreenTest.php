<?php

declare(strict_types=1);

namespace Tests\Modules\X210\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X210\Ui\PromotionBuilder;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionBuilderScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.promotion-builder'))->assertOk();

        Livewire::test(PromotionBuilder::class)->assertOk();
    }
}
