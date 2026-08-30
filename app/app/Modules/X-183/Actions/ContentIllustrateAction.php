<?php

declare(strict_types=1);

namespace App\Modules\X183\Actions;

final class ContentIllustrateAction
{
    public function generateIllustrationPrompt(int $businessId, string $topic): string
    {
        $promptTemplate = 'diagram: %s';

        return sprintf($promptTemplate, $topic);
    }
}
