<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

use App\Modules\X121\Models\Person;
use App\Modules\X155\Events\FormCaptured;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class FormCaptureAction
{
    public function __construct(private readonly FormValidateAction $validator) {}

    public function handle(
        int $businessId,
        int $formDefinitionId,
        array $payload,
        ?string $ipAddress = null,
        ?string $userTimezone = null
    ): array {
        $validation = $this->validator->handle($businessId, $formDefinitionId, $payload, $ipAddress, $userTimezone);

        if (! $validation['is_valid']) {
            return [
                'status' => 'rejected',
                'reason' => $validation['reason'],
                'step' => $validation['step'] ?? null,
                'missing' => $validation['missing'] ?? [],
            ];
        }

        return DB::transaction(function () use ($businessId, $formDefinitionId, $payload, $ipAddress, $userTimezone) {
            $form = FormDefinition::where('business_id', $businessId)->findOrFail($formDefinitionId);

            // Direct entity writing (G2-20, G13-35): forms write straight to Person entity, no intermediate buffer
            $phone = $payload['phone'] ?? '+15550000000';
            $firstName = $payload['first_name'] ?? ($payload['name'] ?? 'Visitor');
            $email = $payload['email'] ?? null;

            $person = Person::updateOrCreate(
                ['business_id' => $businessId, 'phone' => $phone],
                ['first_name' => $firstName, 'email' => $email]
            );

            // Every submission row references a Person id (TEST ANCHOR)
            $submission = FormSubmission::create([
                'business_id' => $businessId,
                'form_definition_id' => $form->id,
                'person_id' => $person->id,
                'payload' => $payload,
                'ip_address' => $ipAddress,
                'user_timezone' => $userTimezone,
                'is_spam' => false,
            ]);

            Event::dispatch(new FormCaptured(
                businessId: $businessId,
                submissionId: $submission->id,
                formDefinitionId: $form->id,
                personId: $person->id
            ));

            return [
                'status' => 'captured',
                'submission_id' => $submission->id,
                'person_id' => $person->id,
            ];
        });
    }
}
