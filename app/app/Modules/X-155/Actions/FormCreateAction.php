<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

use App\Modules\X155\Models\FormDefinition;
use Illuminate\Support\Str;

class FormCreateAction
{
    public function handle(int $businessId, string $formName): FormDefinition
    {
        $existing = FormDefinition::where('business_id', $businessId)
            ->where('form_name', $formName)
            ->first();

        if ($existing) {
            return $existing;
        }

        $baseSlug = Str::slug($formName);
        $slug = $baseSlug;
        $counter = 2;

        while (FormDefinition::where('business_id', $businessId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return FormDefinition::create([
            'business_id' => $businessId,
            'form_name' => $formName,
            'slug' => $slug,
            'steps' => [
                [
                    'fields' => ['name', 'phone', 'email', 'message'],
                    'required' => ['name', 'phone'],
                ],
            ],
            'schema' => [
                'fields' => [
                    ['name' => 'name', 'label' => 'Your name', 'type' => 'text'],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'message', 'label' => 'How can we help?', 'type' => 'textarea'],
                ],
            ],
            // honeypot_field left at its default by not setting it, or it defaults in DB.
            // Wait, does it have a default in DB? "honeypot_field default website_url"
        ]);
    }
}
