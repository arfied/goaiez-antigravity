<?php

declare(strict_types=1);

namespace Tests\Modules\X210\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X210\Ui\ActivePromotions;
use Livewire\Livewire;
use Tests\TestCase;

class ActivePromotionsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.active-promotions'))->assertOk();

        Livewire::test(ActivePromotions::class)->assertOk();
    }
}
