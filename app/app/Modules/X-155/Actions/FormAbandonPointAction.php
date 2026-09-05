<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

use App\Modules\X110\Domain\PixelEngine;
use App\Modules\X155\Models\FormDefinition;

final class FormAbandonPointAction
{
    public function __construct(private readonly PixelEngine $pixel) {}

    /**
     * G11-01: where visitors stop filling one of this tenant's forms.
     *
     * @return array{form_definition_id: int, slug: string, total: int, points: array<int, array{field: string, count: int}>, top_field: string|null}
     */
    public function handle(int $businessId, int $formDefinitionId): array
    {
        $form = FormDefinition::where('business_id', $businessId)->findOrFail($formDefinitionId);

        $report = $this->pixel->abandonPointsForForm($businessId, (string) $form->slug);

        return [
            'form_definition_id' => (int) $form->id,
            'slug' => (string) $form->slug,
            'total' => $report['total'],
            'points' => $report['points'],
            'top_field' => $report['points'][0]['field'] ?? null,
        ];
    }
}
