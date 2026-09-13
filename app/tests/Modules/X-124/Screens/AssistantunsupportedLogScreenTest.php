<?php

declare(strict_types=1);

namespace Tests\Modules\X124\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X124\Actions\AssistantAskAction;
use App\Modules\X124\Ui\AssistantunsupportedLog;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantunsupportedLogScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-124.assistantunsupported-log.admin'))->assertOk();
    }

    public function test_screen_renders_for_tenant(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-124.assistantunsupported-log'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No unsupported utterances recorded.')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(AssistantAskAction::class)->handle((int) $biz->id, 'ui101-sess', 'Fly me to Mars tomorrow morning');
        Tenancy::forget();

        $this->get(route('x-124.assistantunsupported-log'))
            ->assertOk()
            ->assertSee('Fly me to Mars tomorrow morning')
            ->assertSee("I can't do that yet")
            ->assertDontSee('No unsupported utterances recorded.');

        Livewire::test(AssistantunsupportedLog::class)->assertOk();
    }
}
