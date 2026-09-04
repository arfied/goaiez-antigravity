<?php

declare(strict_types=1);

namespace Tests\Modules\X199\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Ui\Credits;
use Livewire\Livewire;
use Tests\TestCase;

class CreditsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-199.credits'))->assertOk();

        Livewire::test(Credits::class)->assertOk();
    }
}
