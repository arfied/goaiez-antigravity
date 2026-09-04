<?php

declare(strict_types=1);

namespace Tests\Modules\X201\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X201\Ui\DisputeCard;
use Livewire\Livewire;
use Tests\TestCase;

class DisputeCardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-201.dispute-card'))->assertOk();

        Livewire::test(DisputeCard::class)->assertOk();
    }
}
