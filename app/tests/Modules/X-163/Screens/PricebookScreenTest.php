<?php

declare(strict_types=1);

namespace Tests\Modules\X163\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X163\Ui\Pricebook;
use Livewire\Livewire;
use Tests\TestCase;

class PricebookScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-163.pricebook'))->assertOk();

        Livewire::test(Pricebook::class)->assertOk();
    }
}
