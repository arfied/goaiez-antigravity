<?php

declare(strict_types=1);

namespace App\Modules\X154\Actions;

use App\Modules\X154\Events\LexiconUpdated;
use App\Modules\X154\Models\TenantLexicon;
use Illuminate\Support\Facades\Event;

final class LexiconReadbackAction
{
    public function setMapping(
        int $businessId,
        string $genericTerm,
        string $preferredTerm,
        string $category = 'service_name'
    ): TenantLexicon {
        $lexicon = TenantLexicon::updateOrCreate(
            ['business_id' => $businessId, 'generic_term' => $genericTerm],
            [
                'preferred_term' => $preferredTerm,
                'category' => $category,
                'is_confirmed' => true,
            ]
        );

        Event::dispatch(new LexiconUpdated($businessId, $genericTerm, $preferredTerm));

        return $lexicon;
    }
}
