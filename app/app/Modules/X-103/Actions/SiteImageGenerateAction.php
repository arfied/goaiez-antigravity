<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Models\Business;
use App\Services\Ai\AiRouter;
use App\Services\Ai\ImageRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class SiteImageGenerateAction
{
    public function handle(int $businessId, string $description): array
    {
        $business = Business::query()->findOrFail($businessId);

        $prompt = "Photograph for a small business website ({$business->name}). {$description}. Realistic, natural light, no text, no logos, no watermarks.";

        $router = app(AiRouter::class);
        $response = $router->image(new ImageRequest(AiTask::SiteImage, $prompt));

        if (! $response->isUsable()) {
            return [
                'status' => 'refused',
                'reason' => $response->failureReason ?? 'unknown',
            ];
        }

        $ulid = Str::ulid()->toString();
        $path = "site-inventory/{$businessId}/ai-{$ulid}.jpg";

        Storage::disk('local')->put($path, $response->bytes);

        return [
            'status' => 'generated',
            'path' => $path,
        ];
    }
}
