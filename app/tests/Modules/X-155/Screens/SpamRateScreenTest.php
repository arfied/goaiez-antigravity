<?php

declare(strict_types=1);

namespace Tests\Modules\X155\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use App\Modules\X155\Ui\SpamRate;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SpamRateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-155.spam-rate'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No submissions yet.');

        Tenancy::setUser($owner->id);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Distinctive Form 4492', 'slug' => 'distinctive-4492', 'steps' => [], 'schema' => []]);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'phone' => '+15125554492']);
        FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => false, 'payload' => []]);
        FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => true, 'payload' => []]);
        Tenancy::forget();

        $this->get(route('x-155.spam-rate'))
            ->assertOk()
            ->assertSee('Total: 2')
            ->assertSee('Spam: 1')
            ->assertSee('Rate: 50%')
            ->assertDontSee('No submissions yet.');

        Livewire::test(SpamRate::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-155.spam-rate.admin'))->assertOk();

        Livewire::test(SpamRate::class)->assertOk();
    }
}
