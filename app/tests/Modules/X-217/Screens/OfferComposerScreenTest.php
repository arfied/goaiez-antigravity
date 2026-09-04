<?php

declare(strict_types=1);

namespace Tests\Modules\X217\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class OfferComposerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-217.offer-composer'))->assertOk();

        Livewire::test(\App\Modules\X217\Ui\OfferComposer::class)->assertOk();
    }
}
