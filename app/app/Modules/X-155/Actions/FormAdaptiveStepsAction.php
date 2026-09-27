<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

use App\Modules\X155\Models\FormDefinition;

final class FormAdaptiveStepsAction
{
    /**
     * Which of a form's steps apply to the answers given (G5-30).
     *
     * A step may carry show_if, a map of field => accepted value or list of
     * accepted values. A step whose show_if is not satisfied is not shown, so
     * its required fields are never enforced. A step with no show_if, or an
     * empty one, is always shown.
     *
     * @param  array<mixed, mixed>  $answers
     * @return array<int, array<mixed, mixed>>
     */
    public function handle(FormDefinition $form, array $answers): array
    {
        $steps = is_array($form->steps) ? $form->steps : [];

        $applicable = [];

        foreach ($steps as $key => $step) {
            if (! is_array($step)) {
                continue;
            }

            $conditions = $step['show_if'] ?? [];

            if (! is_array($conditions) || $conditions === []) {
                $applicable[$key] = $step;

                continue;
            }

            $shown = true;

            foreach ($conditions as $field => $accepted) {
                $given = $answers[$field] ?? null;
                $allowed = is_array($accepted) ? $accepted : [$accepted];

                $match = false;

                foreach ($allowed as $candidate) {
                    if ($candidate === $given) {
                        $match = true;

                        break;
                    }

                    $comparable = fn ($v) => is_int($v) || is_float($v) || is_string($v);

                    if ($comparable($candidate) && $comparable($given) && (string) $candidate === (string) $given) {
                        $match = true;

                        break;
                    }
                }

                if (! $match) {
                    $shown = false;

                    break;
                }
            }

            if ($shown) {
                $applicable[$key] = $step;
            }
        }

        return $applicable;
    }
}
