<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;

class X155Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-155';
    }

    public function fill(Business $business): int
    {
        if (FormDefinition::where('business_id', $business->id)->where('form_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        $person = Person::create(['business_id' => $business->id, 'first_name' => self::MARKER.'Person', 'phone' => '+15125554487']);
        $form1 = FormDefinition::create(['business_id' => $business->id, 'form_name' => self::MARKER.'Form 1', 'slug' => 'demo-form-1', 'steps' => [], 'schema' => []]);
        $form2 = FormDefinition::create(['business_id' => $business->id, 'form_name' => self::MARKER.'Form 2', 'slug' => 'demo-form-2', 'steps' => [], 'schema' => []]);
        for ($i = 0; $i < 5; $i++) {
            FormSubmission::create(['business_id' => $business->id, 'form_definition_id' => $form1->id, 'person_id' => $person->id, 'is_spam' => false, 'payload' => []]);
        }
        FormSubmission::create(['business_id' => $business->id, 'form_definition_id' => $form2->id, 'person_id' => $person->id, 'is_spam' => true, 'payload' => []]);

        return 8; // 2 forms, 6 submissions
    }

    public function purge(Business $business): int
    {
        $forms = FormDefinition::where('business_id', $business->id)->where('form_name', 'like', self::MARKER.'%')->get();
        $count = 0;
        foreach ($forms as $form) {
            $count += FormSubmission::where('form_definition_id', $form->id)->delete();
            $form->delete();
            $count++;
        }
        $count += Person::where('business_id', $business->id)->where('first_name', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
