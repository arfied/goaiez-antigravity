<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

use App\Modules\X155\Events\FormCaptured;
use App\Modules\X155\Models\FormSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class FormReleaseAction
{
    public function handle(int $businessId, int $submissionId): array
    {
        return DB::transaction(function () use ($businessId, $submissionId) {
            $submission = FormSubmission::where('business_id', $businessId)->findOrFail($submissionId);

            if (! $submission->is_spam) {
                return [
                    'status' => 'already_released',
                    'submission_id' => $submission->id,
                    'person_id' => $submission->person_id,
                ];
            }

            $submission->update([
                'is_spam' => false,
                'spam_reason' => null,
            ]);

            Event::dispatch(new FormCaptured(
                businessId: $businessId,
                submissionId: $submission->id,
                formDefinitionId: $submission->form_definition_id,
                personId: $submission->person_id
            ));

            return [
                'status' => 'released',
                'submission_id' => $submission->id,
                'person_id' => $submission->person_id,
            ];
        });
    }
}
