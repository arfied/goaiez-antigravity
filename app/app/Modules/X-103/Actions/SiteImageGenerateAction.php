<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiModel;
use App\Enums\AiProvider;
use App\Enums\AiTask;
use App\Models\Business;
use App\Services\Ai\AiRouter;
use App\Services\Ai\ImageRequest;
use App\Support\PlatformCredentials;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class SiteImageGenerateAction
{
    public function handle(int $businessId, string $description): array
    {
        $business = Business::query()->findOrFail($businessId);

        $prompt = "Photograph for a small business website ({$business->name}). {$description}. Realistic, natural light, no text, no logos, no watermarks.";

        $router = app(AiRouter::class);
        // Low-cost FLUX pictures once a fal.ai key is set (the boss, 2026-10-02); OpenAI images until then, so pictures never stop.
        $model = PlatformCredentials::has(AiProvider::Fal->credentialKey()) ? AiModel::FluxSchnell : null;
        $response = $router->image(new ImageRequest(AiTask::SiteImage, $prompt, model: $model));
        // A refused FLUX picture falls back to OpenAI once (production, 2026-10-04: fal.ai answered 403 to all twelve pictures
        // of four designs and every page came out with none, although the OpenAI image key worked).
        if (! $response->isUsable() && $model === AiModel::FluxSchnell) {
            $response = $router->image(new ImageRequest(AiTask::SiteImage, $prompt));
        }

        if (! $response->isUsable()) {
            return [
                'status' => 'refused',
                'reason' => $response->failureReason ?? 'unknown',
            ];
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($response->bytes);
        $ext = match (true) {
            $mime === 'image/jpeg' => 'jpg',
            $mime === 'image/png' => 'png',
            $mime === 'image/webp' => 'webp',
            default => null,
        };

        if ($ext === null) {
            return ['status' => 'refused', 'reason' => 'not_an_image'];
        }

        $ulid = Str::ulid()->toString();
        $path = "site-inventory/{$businessId}/ai-{$ulid}.{$ext}";

        Storage::disk('local')->put($path, $response->bytes);

        return [
            'status' => 'generated',
            'path' => $path,
        ];
    }
}
