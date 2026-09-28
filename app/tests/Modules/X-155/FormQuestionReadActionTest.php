<?php

namespace Tests\Modules\X155;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Actions\FormQuestionReadAction;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use App\Support\Tenancy;
use Tests\TestCase;

class FormQuestionReadActionTest extends TestCase
{
    public function test_it_returns_questions_from_form_payloads(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::setUser($owner->id);
        Tenancy::set($biz->id);

        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Form 1', 'slug' => 'form-1', 'steps' => [], 'schema' => []]);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'phone' => '+15125554487']);

        $sub1 = FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => false, 'payload' => ['name' => 'Ann', 'message' => 'Distinctive form question 4511?']]);

        $sub2 = FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => false, 'payload' => ['name' => 'Bob', 'phone' => '5551234567', 'details' => 'Distinctive long free text 4512 about a roof']]);

        FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => false, 'payload' => ['name' => 'Cy', 'email' => 'cy@example.test']]);

        FormSubmission::create(['business_id' => $biz->id, 'form_definition_id' => $form->id, 'person_id' => $person->id, 'is_spam' => true, 'payload' => ['message' => 'spam message']]);

        $action = new FormQuestionReadAction;
        $results = $action->recent($biz->id, 90, 10);

        $this->assertCount(2, $results);
        // Order by id desc, so sub2 comes first
        $this->assertEquals($sub2->id, $results[0]['id']);
        $this->assertEquals('Distinctive long free text 4512 about a roof', $results[0]['question']);

        $this->assertEquals($sub1->id, $results[1]['id']);
        $this->assertEquals('Distinctive form question 4511?', $results[1]['question']);
    }
}
