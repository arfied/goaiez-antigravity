<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiModel;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * FLUX.1 [schnell] through fal.ai (the boss, 2026-10-02: low-cost photos). Checked that day against fal.ai's own pages:
 * `POST https://fal.run/fal-ai/flux/schnell`, header `Authorization: Key <key>`, JSON input; $0.003 per megapixel, billed
 * by rounding up to the nearest megapixel. `sync_mode: true` returns the picture inline as a data URI, so no second request
 * goes to another host; an answer carrying a remote URL instead is refused rather than fetched.
 *
 * Cost is recorded through AiModel's per-million prices: for this model one "token" is a millionth of a billed megapixel,
 * so outputTokens = billed megapixels × 1,000,000 and the output price is the price of one megapixel.
 */
final readonly class FalImageClient
{
    private const ENDPOINT = 'https://fal.run/fal-ai/flux/schnell';

    public function __construct(
        private AiModel $model,
    ) {}

    public function generate(ImageRequest $request): ImageResponse
    {
        if (! PlatformCredentials::has($this->model->provider()->credentialKey())) {
            VendorLog::failure('fal', 'POST', self::ENDPOINT, 'credential_not_configured', Tenancy::id());

            return ImageResponse::failed($this->model, 'credential_not_configured');
        }

        try {
            $response = VendorLog::timed(
                'fal',
                'POST',
                self::ENDPOINT,
                fn (): Response => Http::withHeaders(['Authorization' => 'Key '.PlatformCredentials::get($this->model->provider()->credentialKey())])
                    ->timeout((int) config('ai.image_timeout', 120))
                    ->acceptJson()
                    ->post(self::ENDPOINT, [
                        'prompt' => $request->prompt,
                        'image_size' => 'landscape_16_9',
                        'num_images' => 1,
                        'output_format' => 'jpeg',
                        'sync_mode' => true,
                        'enable_safety_checker' => true,
                    ]),
                Tenancy::id(),
            );
        } catch (ConnectionException) {
            VendorLog::failure('fal', 'POST', self::ENDPOINT, ConnectionException::class, Tenancy::id());

            return ImageResponse::failed($this->model, 'unreachable');
        }

        if (! $response->successful()) {
            $detail = (string) (data_get($response->json(), 'detail.0.msg') ?? data_get($response->json(), 'detail') ?? '');
            VendorLog::failure('fal', 'POST', self::ENDPOINT, VendorLog::httpReason($response->status(), $detail), Tenancy::id());

            return ImageResponse::failed($this->model, 'http_'.$response->status());
        }

        $image = data_get($response->json(), 'images.0');
        $url = is_array($image) && is_string($image['url'] ?? null) ? $image['url'] : '';
        if (preg_match('#^data:image/[a-z]+;base64,(.+)$#s', $url, $m) !== 1) {
            return ImageResponse::failed($this->model, 'no_inline_image');
        }
        $bytes = base64_decode($m[1], true);
        if ($bytes === false || $bytes === '') {
            return ImageResponse::failed($this->model, 'no_image');
        }

        $width = (int) ($image['width'] ?? 1024);
        $height = (int) ($image['height'] ?? 576);
        $megapixels = max(1, (int) ceil(($width * $height) / 1_000_000));

        return new ImageResponse(
            bytes: $bytes,
            model: $this->model,
            inputTokens: 0,
            outputTokens: $megapixels * 1_000_000,
            failureReason: null,
            usageReported: true,
        );
    }
}
