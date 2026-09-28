<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

use App\Modules\X121\Actions\PersonUpsertAction;
use App\Modules\X155\Events\FormCaptured;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use Carbon\Carbon;
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
        // P-148 (GOAIEZ-MASTER-PLAN.md:625, row 30484): an under-18 signal at ingest
        // prevents the contact row. Asserted at the write, not at the reply.
        $signal = $this->ageSignal($payload);
        if ($signal !== null) {
            return [
                'status' => 'rejected',
                'reason' => $signal,
            ];
        }

        $validation = $this->validator->handle($businessId, $formDefinitionId, $payload, $ipAddress, $userTimezone);

        if (! $validation['is_valid']) {
            // G3-64 / G13-05: a SPAM rejection is stored and flagged, never discarded
            // (GOAIEZ-MASTER-PLAN.md:31363). An incomplete step is not spam and is not stored.
            if ($validation['is_spam'] !== true) {
                return [
                    'status' => 'rejected',
                    'reason' => $validation['reason'],
                    'step' => $validation['step'] ?? null,
                    'missing' => $validation['missing'] ?? [],
                ];
            }

            return DB::transaction(function () use ($businessId, $formDefinitionId, $payload, $ipAddress, $userTimezone, $validation) {
                $form = FormDefinition::where('business_id', $businessId)->findOrFail($formDefinitionId);

                $spamPhone = $this->given($payload['phone'] ?? null);

                $upsert = app(PersonUpsertAction::class)->upsertByPhone(
                    $businessId,
                    $spamPhone,
                    [
                        'first_name' => $this->given($payload['first_name'] ?? null) ?? $this->given($payload['name'] ?? null),
                        'email' => $this->given($payload['email'] ?? null),
                    ],
                    true
                );

                $submission = FormSubmission::create([
                    'business_id' => $businessId,
                    'form_definition_id' => $form->id,
                    'person_id' => $upsert['id'],
                    'payload' => $payload,
                    'ip_address' => $ipAddress,
                    'user_timezone' => $userTimezone,
                    'is_spam' => true,
                    'spam_reason' => $validation['reason'],
                ]);

                return [
                    'status' => 'rejected',
                    'reason' => $validation['reason'],
                    'submission_id' => $submission->id,
                    'person_id' => $upsert['id'],
                ];
            });
        }

        return DB::transaction(function () use ($businessId, $formDefinitionId, $payload, $ipAddress, $userTimezone) {
            $form = FormDefinition::where('business_id', $businessId)->findOrFail($formDefinitionId);

            // Direct entity writing (G2-20, G13-35): forms write straight to Person entity, no intermediate buffer
            $phone = $this->given($payload['phone'] ?? null);

            $upsert = app(PersonUpsertAction::class)->upsertByPhone(
                $businessId,
                $phone,
                [
                    'first_name' => $this->given($payload['first_name'] ?? ($payload['name'] ?? null)),
                    'email' => $this->given($payload['email'] ?? null),
                ],
                false
            );

            // Every submission row references a Person id (TEST ANCHOR)
            $submission = FormSubmission::create([
                'business_id' => $businessId,
                'form_definition_id' => $form->id,
                'person_id' => $upsert['id'],
                'payload' => $payload,
                'ip_address' => $ipAddress,
                'user_timezone' => $userTimezone,
                'is_spam' => false,
            ]);

            Event::dispatch(new FormCaptured(
                businessId: $businessId,
                submissionId: $submission->id,
                formDefinitionId: $form->id,
                personId: $upsert['id']
            ));

            return [
                'status' => 'captured',
                'submission_id' => $submission->id,
                'person_id' => $upsert['id'],
            ];
        });
    }

    /** @return 'under_18'|'dob_unreadable'|null */
    private function ageSignal(array $payload): ?string
    {
        if (isset($payload['age']) && is_numeric($payload['age']) && $payload['age'] < 18) {
            return 'under_18';
        }

        foreach (['date_of_birth', 'dob'] as $key) {
            if (is_scalar($payload[$key] ?? '') && trim((string) ($payload[$key] ?? '')) !== '') {
                try {
                    $dob = Carbon::parse($payload[$key]);
                } catch (\Exception $e) {
                    // A value that is present but unreadable is refused and named, never
                    // counted as an adult (wave 819).
                    return 'dob_unreadable';
                }
                if ($dob->diffInYears(now()) < 18) {
                    return 'under_18';
                }
            }
        }

        return null;
    }

    /**
     * A payload value that is blank or whitespace was not given (R245, 2026-09-05).
     * A value which is not a scalar was not given either.
     */
    private function given(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return null;
        }

        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
