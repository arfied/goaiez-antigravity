<?php

declare(strict_types=1);

namespace Tests\Modules\X155\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Ui\Forms;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class FormsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-155.forms'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No forms constructed yet.');

        Tenancy::setUser($owner->id);
        FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Distinctive Form 4481',
            'slug' => 'distinctive-4481',
            'steps' => [['step' => 1, 'fields' => ['phone']]],
            'schema' => ['phone' => 'required|string'],
            'honeypot_field' => 'website_url',
        ]);
        Tenancy::forget();

        $this->get(route('x-155.forms'))
            ->assertOk()
            ->assertSee('Distinctive Form 4481')
            ->assertSee('distinctive-4481')
            ->assertSee('1 step(s)')
            ->assertSee('0 submissions')
            ->assertDontSee('No forms constructed yet.');

        Livewire::test(Forms::class)->assertOk();
    }

    public function test_owner_creates_a_form_from_the_screen(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(Forms::class)
            ->set('newFormName', 'Quote request')
            ->call('createForm')
            ->assertSee('Quote request');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-155.forms.admin'))->assertOk();

        Livewire::test(Forms::class)->assertOk();
    }
}
