<?php

declare(strict_types=1);

namespace Tests\Modules\X155\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use App\Modules\X155\Ui\SubmissionsThread;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionsThreadScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-155.submissions-thread'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No submissions recorded.');

        Tenancy::setUser($owner->id);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Distinctive Form 4487', 'slug' => 'distinctive-4487', 'steps' => [], 'schema' => []]);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'phone' => '+15125554487']);
        FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => false, 'payload' => []]);
        Tenancy::forget();

        $this->get(route('x-155.submissions-thread'))
            ->assertOk()
            ->assertSee('Distinctive Form 4487')
            ->assertSee('[VALID]')
            ->assertDontSee('No submissions recorded.');

        Livewire::test(SubmissionsThread::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-155.submissions-thread.admin'))->assertOk();

        Livewire::test(SubmissionsThread::class)->assertOk();
    }

    public function test_can_release_a_submission_from_spam(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::setUser($owner->id);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Distinctive Form 4487', 'slug' => 'distinctive-4487', 'steps' => [], 'schema' => []]);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'phone' => '+15125554487']);
        $submission = FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => true, 'payload' => []]);

        Livewire::test(SubmissionsThread::class)
            ->call('releaseSubmission', $submission->id)
            ->assertSet('success', 'Submission #'.$submission->id.' is released and now counts as valid.');

        $this->assertDatabaseHas((new FormSubmission)->getTable(), [
            'id' => $submission->id,
            'is_spam' => false,
            'spam_reason' => null,
        ]);
    }

    public function test_releasing_an_already_valid_submission_says_nothing_changed(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::setUser($owner->id);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Distinctive Form 4487', 'slug' => 'distinctive-4487', 'steps' => [], 'schema' => []]);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'phone' => '+15125554487']);
        $submission = FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => false, 'payload' => []]);

        Livewire::test(SubmissionsThread::class)
            ->call('releaseSubmission', $submission->id)
            ->assertSet('success', 'Submission #'.$submission->id.' was already valid — nothing changed.');
    }

    public function test_submissions_thread_shows_the_released_submission(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Distinctive Form 4487', 'slug' => 'distinctive-4487', 'steps' => [], 'schema' => []]);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'phone' => '+15125554487']);
        $submission = FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => true, 'payload' => []]);

        Livewire::test(SubmissionsThread::class)->call('releaseSubmission', $submission->id);

        Tenancy::forget();

        $this->get(route('x-155.submissions-thread'))
            ->assertOk()
            ->assertSee('[VALID]')
            ->assertDontSee('[SPAM]');

        $this->get(route('x-155.spam-rate'))
            ->assertOk()
            ->assertSee('Spam: 0')
            ->assertDontSee('Spam: 1');
    }

    public function test_releasing_another_tenants_submission_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $form = FormDefinition::create(['business_id' => $bizB->id, 'form_name' => 'Distinctive Form 4487', 'slug' => 'distinctive-4487', 'steps' => [], 'schema' => []]);
        $person = Person::create(['business_id' => $bizB->id, 'first_name' => 'Distinctive', 'phone' => '+15125554487']);
        $submissionB = FormSubmission::create(['business_id' => $bizB->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => true, 'payload' => []]);

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(SubmissionsThread::class)->call('releaseSubmission', $submissionB->id);
    }
}
