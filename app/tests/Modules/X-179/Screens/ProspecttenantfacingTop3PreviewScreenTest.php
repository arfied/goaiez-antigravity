<?php

namespace Tests\Modules\X179\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ProspecttenantfacingTop3PreviewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-179.prospecttenantfacing-top3-preview'))->assertOk();

        Livewire::test(\App\Modules\X179\Ui\ProspecttenantfacingTop3Preview::class)->assertOk();
    }
}
