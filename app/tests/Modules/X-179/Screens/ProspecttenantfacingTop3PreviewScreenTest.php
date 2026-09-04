<?php

declare(strict_types=1);

namespace Tests\Modules\X179\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X179\Ui\ProspecttenantfacingTop3Preview;
use Livewire\Livewire;
use Tests\TestCase;

class ProspecttenantfacingTop3PreviewScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-179.prospecttenantfacing-top3-preview.admin'))->assertOk();

        Livewire::test(ProspecttenantfacingTop3Preview::class)->assertOk();
    }
}
