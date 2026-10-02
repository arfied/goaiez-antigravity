<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\EmbeddingClient;
use App\Enums\AiModel;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI's Embeddings API, on Laravel's HTTP client.
 *
 * Endpoint, headers and body fields read from OpenAI's live documentation on
 * AiModel::VERIFIED_ON. Shaped after OpenAiClient deliberately — same credential
 * lookup, same VendorLog wrapping, same "never throw for a vendor reason"
 * contract — so the two differ only where the vendor does.
 *
 * WHERE THE VENDOR DIFFERS, AND EACH ONE IS A REAL TRAP:
 *
 *   endpoint    `/v1/embeddings`, not `/v1/chat/completions`
 *   body        `input` (an array), not `messages`
 *   usage       `prompt_tokens` only — `completion_tokens` is absent, not zero,
 *               so reading it with a 0 default is the honest form
 *   ordering    the response `data` array carries an `index` per item and is
 *               **not guaranteed to arrive in request order**. Sorting by it is
 *               not defensive tidiness: an out-of-order batch silently pairs
 *               chunk four's text with chunk three's vector, and the resulting
 *               retrieval is confidently wrong forever with nothing to notice.
 *
 * NO `dimensions` PARAMETER IS SENT. The model's default output length is 1536,
 * which is exactly what `knowledge_chunks.embedding` is declared as; sending a
 * shortening parameter would create a second place for those two numbers to
 * disagree. AiModel::embeddingDimensions() is the one place, and the ingest path
 * checks the returned vector against it.
 */
final class OpenAiEmbeddingClient implements EmbeddingClient
{
    private const string ENDPOINT = 'https://api.openai.com/v1/embeddings';

    public function __construct(
        private readonly AiModel $model,
    ) {}

    public function embed(EmbeddingRequest $request): EmbeddingResponse
    {
        if (! PlatformCredentials::has($this->model->provider()->credentialKey())) {
            VendorLog::failure('openai', 'POST', self::ENDPOINT, 'credential_not_configured', Tenancy::id());

            return EmbeddingResponse::failed($this->model, 'credential_not_configured');
        }

        try {
            $response = VendorLog::timed(
                'openai',
                'POST',
                self::ENDPOINT,
                fn (): Response => $this->request()->post(self::ENDPOINT, [
                    'model' => $this->model->value,
                    'input' => $request->inputs,
                ]),
                Tenancy::id(),
            );
        } catch (ConnectionException) {
            VendorLog::failure('openai', 'POST', self::ENDPOINT, ConnectionException::class, Tenancy::id());

            return EmbeddingResponse::failed($this->model, 'unreachable');
        }

        if ($response->failed()) {
            $detail = (string) data_get($response->json(), 'error.message', '');
            VendorLog::failure('openai', 'POST', self::ENDPOINT, VendorLog::httpReason($response->status(), $detail), Tenancy::id());

            return EmbeddingResponse::failed($this->model, 'http_'.$response->status());
        }

        return $this->interpret($response);
    }

    private function interpret(Response $response): EmbeddingResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        $inputTokens = (int) data_get($payload, 'usage.prompt_tokens', 0);

        $data = data_get($payload, 'data');

        if (! is_array($data) || $data === []) {
            return EmbeddingResponse::failed($this->model, 'no_embedding_data', $inputTokens);
        }

        $indexed = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                return EmbeddingResponse::failed($this->model, 'malformed_embedding_item', $inputTokens);
            }

            $vector = $item['embedding'] ?? null;

            if (! is_array($vector) || $vector === []) {
                return EmbeddingResponse::failed($this->model, 'malformed_embedding_item', $inputTokens);
            }

            // The vendor's own `index`, not the array position. See the class
            // docblock: position is what silently mispairs a batch.
            $indexed[(int) ($item['index'] ?? count($indexed))] = array_map(
                static fn (mixed $component): float => (float) $component,
                array_values($vector),
            );
        }

        ksort($indexed);

        return EmbeddingResponse::answered($this->model, array_values($indexed), $inputTokens);
    }

    private function request(): PendingRequest
    {
        return Http::withToken(PlatformCredentials::get($this->model->provider()->credentialKey()))
            ->timeout((int) config('ai.timeout', 60))
            ->acceptJson();
    }
}
