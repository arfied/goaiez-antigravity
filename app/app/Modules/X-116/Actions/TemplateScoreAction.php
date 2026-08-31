<?php

declare(strict_types=1);

namespace App\Modules\X116\Actions;

use App\Modules\X116\Events\TemplateScored;
use App\Modules\X116\Models\Template;
use Illuminate\Support\Facades\Event;

final class TemplateScoreAction
{
    public function score(int $businessId, int $templateId, float $conversionRate): Template
    {
        $template = Template::where('business_id', $businessId)->findOrFail($templateId);
        $template->update(['conversion_rate' => $conversionRate]);

        Event::dispatch(new TemplateScored($businessId, $template->id, $conversionRate));

        return $template;
    }
}
