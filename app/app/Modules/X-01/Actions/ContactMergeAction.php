<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X121\Actions\PersonLookupAction;

final class ContactMergeAction
{
    public function handle(int $businessId, int $sourcePersonId, int $targetPersonId): array
    {
        return app(PersonLookupAction::class)->merge($businessId, $sourcePersonId, $targetPersonId);
    }
}
