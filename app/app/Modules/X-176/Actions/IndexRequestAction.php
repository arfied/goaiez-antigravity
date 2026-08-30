<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

use App\Modules\X176\Events\IndexRequested;
use Illuminate\Support\Facades\Event;

final class IndexRequestAction
{
    public function handle(int $businessId, string $url): array
    {
        Event::dispatch(new IndexRequested($businessId, $url));

        return [
            'status' => 'indexing_requested',
            'url' => $url,
        ];
    }
}
