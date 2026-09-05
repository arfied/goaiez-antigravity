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
            ["needles" => ["social security", "ssn"], "reason" => "regulated_ask"],
            ["needles" => ["credit card", "card number", "cvv"], "reason" => "regulated_ask"],
            ["needles" => ["age", "date of birth", "birthday", "how old"], "reason" => "under_18_gate"],
        ];

        foreach ($refusalGroups as $group) {
            foreach ($group["needles"] as $needle) {
                if (str_contains($lowercased, $needle)) {
                    $refused[] = ["ask" => $needle, "reason" => $group["reason"]];
                    $matchedNeedles[] = $needle;
                    break;
                }
            }
        }

        $lowercased = str_replace($matchedNeedles, "", $lowercased);

        $fields = [];
        $map = [
            ["needles" => ["name"], "field" => ["name" => "first_name", "label" => "Your name", "type" => "text"]],
            ["needles" => ["phone"], "field" => ["name" => "phone", "label" => "Phone", "type" => "tel"]],
            ["needles" => ["email"], "field" => ["name" => "email", "label" => "Email", "type" => "email"]],
            ["needles" => ["address"], "field" => ["name" => "address", "label" => "Address", "type" => "text"]],
            ["needles" => ["date", "when"], "field" => ["name" => "preferred_date", "label" => "Preferred date", "type" => "date"]],
            ["needles" => ["message", "describe", "detail"], "field" => ["name" => "message", "label" => "Tell us more", "type" => "textarea"]],
        ];

        foreach ($map as $entry) {
            foreach ($entry["needles"] as $needle) {
                if (str_contains($lowercased, $needle)) {
                    $fields[] = $entry["field"];
                    break;
                }
            }
        }

        return [
            "fields" => $fields,
            "refused" => $refused,
        ];
    }
}
