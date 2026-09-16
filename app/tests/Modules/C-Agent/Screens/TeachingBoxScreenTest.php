<?php

declare(strict_types=1);

namespace Tests\Modules\CAgent\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Models\AgentInstruction;
use App\Modules\CAgent\Ui\TeachingBox;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class TeachingBoxScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-agent.teaching-box'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No custom instructions defined.');

        Tenancy::setUser($owner->id);
        AgentInstruction::create([
            'business_id' => $biz->id,
            'instruction_key' => 'distinctive_key_4512',
            'instruction_text' => 'Distinctive instruction 4512',
        ]);
        Tenancy::forget();

        $this->get(route('c-agent.teaching-box'))
            ->assertOk()
            ->assertSee('distinctive_key_4512')
            ->assertSee('Distinctive instruction 4512')
            ->assertDontSee('No custom instructions defined.');

        Livewire::test(TeachingBox::class)->assertOk();
    }
}
