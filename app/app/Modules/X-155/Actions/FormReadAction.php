<?php

namespace App\Modules\X155\Actions;

use App\Modules\X155\Models\FormDefinition;

class FormReadAction
{
    public function firstIdForBusiness(int $businessId): ?int
    {
        return FormDefinition::where('business_id', $businessId)->orderBy('id')->value('id');
    }

    public function firstDefinitionForBusiness(int $businessId): ?array
    {
        $form = FormDefinition::where('business_id', $businessId)->orderBy('id')->first();

        if (! $form) {
            return null;
        }

        $fields = $form->schema['fields'] ?? [];
        $required = [];
        if (isset($form->steps) && is_array($form->steps) && isset($form->steps[0]['required'])) {
            $required = $form->steps[0]['required'];
        }
        $honeypot = $form->honeypot_field ?? 'website_url';

        return [
            'id' => $form->id,
            'fields' => $fields,
            'required' => $required,
            'honeypot' => $honeypot,
        ];
    }
}
