<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Models\Person;

final class PersonUpsertAction
{
    /**
     * @return array{id: int, created: bool}
     */
    public function upsertByPhone(int $businessId, ?string $phone, array $attributes, bool $onlyIfNew = false): array
    {
        // A submission that carries no phone gets its own contact, never a shared one (R245, 2026-09-05).
        $person = $phone === null
            ? new Person(['business_id' => $businessId])
            : Person::firstOrNew(['business_id' => $businessId, 'phone' => $phone]);

        $exists = $person->exists;

        // A submission judged spam never rewrites a contact the business already has (R245, 2026-09-05).
        if (! $exists || ! $onlyIfNew) {
            // Only write the fields the payload actually carried (R245, 2026-09-05).
            $filtered = array_filter($attributes, fn ($v) => $v !== null);
            $person->fill($filtered);

            if (! $exists) {
                // A detail that is blank or whitespace was not given (R245, 2026-09-05).
                $person->first_name ??= 'Visitor';
            }

            $person->save();
        }

        return [
            'id' => $person->id,
            'created' => ! $exists,
        ];
    }
}
