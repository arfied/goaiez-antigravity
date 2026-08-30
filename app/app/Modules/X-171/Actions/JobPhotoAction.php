<?php

declare(strict_types=1);

namespace App\Modules\X171\Actions;

use App\Modules\X171\Events\PhotoCaptured;
use Illuminate\Support\Facades\Event;

final class JobPhotoAction
{
    public function handle(int $businessId, int $jobId, string $photoUrl): array
    {
        Event::dispatch(new PhotoCaptured($businessId, $jobId, $photoUrl));

        return ['status' => 'photo_captured', 'photo_url' => $photoUrl];
    }
}
