<?php

declare(strict_types=1);

namespace Tests\Modules\X139\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X139\Ui\AdaccountConnectCard;
use Livewire\Livewire;
use Tests\TestCase;

class AdaccountConnectCardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-139.adaccount-connect-card'))->assertOk();

        Livewire::test(AdaccountConnectCard::class)->assertOk();
    }
}
