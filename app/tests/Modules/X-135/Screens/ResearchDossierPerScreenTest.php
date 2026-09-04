<?php

declare(strict_types=1);

namespace Tests\Modules\X135\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ResearchDossierPerScreenTest extends TestCase
{

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-135.research-dossier-per.admin'))->assertOk();

        Livewire::test(\App\Modules\X135\Ui\ResearchDossierPer::class)->assertOk();
    }
}
