<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiModel;
use App\Support\PlatformCredentials;
use Illuminate\Support\Facades\Http;

final readonly class OpenAiImageClient
{
    public function __construct(
        private AiModel $model,
    ) {}

    public function generate(ImageRequest $request): ImageResponse
    {
        if (! PlatformCredentials::has($this->model->provider()->credentialKey())) {
            return ImageResponse::failed($this->model, 'credential_not_configured');
        }

        $response = Http::withToken(PlatformCredentials::get($this->model->provider()->credentialKey()))
            ->timeout((int) config('ai.image_timeout', 120))
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => $this->model->apiModelId(),
                'prompt' => $request->prompt,
                'size' => 'auto',
                'quality' => $request->quality,
                'output_format' => 'jpeg',
                'output_compression' => 80,
                'n' => 1,
            ]);

        if (! $response->successful()) {
            return ImageResponse::failed($this->model, 'http_'.$response->status());
        }

        $json = $response->json();
        $b64 = $json['data'][0]['b64_json'] ?? '';
        $bytes = base64_decode($b64, true);

        if ($bytes === false || $bytes === '') {
            return ImageResponse::failed($this->model, 'no_image');
        }

        $inputTokens = $json['usage']['input_tokens'] ?? null;
        $outputTokens = $json['usage']['output_tokens'] ?? null;

        $usageReported = $inputTokens !== null && $outputTokens !== null;

        return new ImageResponse(
            bytes: $bytes,
            model: $this->model,
            inputTokens: (int) $inputTokens,
            outputTokens: (int) $outputTokens,
            failureReason: null,
            usageReported: $usageReported,
        );
    }
}
