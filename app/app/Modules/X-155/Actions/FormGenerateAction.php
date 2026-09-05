<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

final class FormGenerateAction
{
    public function handle(string $description): array
    {
        $lowercased = strtolower($description);
        $refused = [];
        $matchedNeedles = [];

        $refusalGroups = [
            ['needles' => ['social security', 'ssn'], 'reason' => 'regulated_ask'],
            ['needles' => ['credit card', 'card number', 'cvv'], 'reason' => 'regulated_ask'],
            ['needles' => ['age', 'date of birth', 'birthday', 'how old'], 'reason' => 'under_18_gate'],
        ];

        $hasNeedle = fn (string $haystack, string $needle): bool => (bool) preg_match(
            '/(?<![a-z])'.preg_quote($needle, '/').'(?![a-z])/', $haystack
        );

        foreach ($refusalGroups as $group) {
            $groupMatched = false;
            foreach ($group['needles'] as $needle) {
                if ($hasNeedle($lowercased, $needle)) {
                    if (!$groupMatched) {
                        $refused[] = ['ask' => $needle, 'reason' => $group['reason']];
                        $groupMatched = true;
                    }
                    $matchedNeedles[] = $needle;
                }
            }
        }

        foreach ($matchedNeedles as $needle) {
            $lowercased = preg_replace('/(?<![a-z])'.preg_quote($needle, '/').'(?![a-z])/', '', $lowercased);
        }

        $fields = [];
        $map = [
            ['needles' => ['name'], 'field' => ['name' => 'first_name', 'label' => 'Your name', 'type' => 'text']],
            ['needles' => ['phone'], 'field' => ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel']],
            ['needles' => ['email'], 'field' => ['name' => 'email', 'label' => 'Email', 'type' => 'email']],
            ['needles' => ['address'], 'field' => ['name' => 'address', 'label' => 'Address', 'type' => 'text']],
            ['needles' => ['date', 'when'], 'field' => ['name' => 'preferred_date', 'label' => 'Preferred date', 'type' => 'date']],
            ['needles' => ['message', 'describe', 'detail'], 'field' => ['name' => 'message', 'label' => 'Tell us more', 'type' => 'textarea']],
        ];

        foreach ($map as $entry) {
            foreach ($entry['needles'] as $needle) {
                if ($hasNeedle($lowercased, $needle)) {
                    $fields[] = $entry['field'];
                    break;
                }
            }
        }

        return [
            'fields' => $fields,
            'refused' => $refused,
        ];
    }
}
