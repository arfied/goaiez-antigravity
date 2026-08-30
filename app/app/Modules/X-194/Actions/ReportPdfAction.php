<?php

declare(strict_types=1);

namespace App\Modules\X194\Actions;

use App\Modules\X194\Models\SavedView;

final class ReportPdfAction
{
    public function generate(int $businessId, int $viewId): array
    {
        $view = SavedView::where('business_id', $businessId)->findOrFail($viewId);

        $fakePdfBinary = "%PDF-1.4\n% Report: {$view->view_name}\n%%EOF";

        return [
            'status' => 'generated',
            'view_id' => $view->id,
            'pdf_payload' => base64_encode($fakePdfBinary),
        ];
    }
}
