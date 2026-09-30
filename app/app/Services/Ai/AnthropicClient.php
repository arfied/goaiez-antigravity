<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\AiClient;
use App\Enums\AiModel;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic's Messages API, on Laravel's HTTP client.
 *
 * Endpoint, headers and body fields read from Anthropic's live documentation on
 * AiModel::VERIFIED_ON.
 *
 * WHY NOT THE OFFICIAL SDK. Every vendor in this codebase goes through Laravel's
 * HTTP client, and `AppServiceProvider::forbidLiveVendorCallsInTests()` explains
 * what that buys: `Http::preventStrayRequests()` "covers every call in
 * app/Services because they all go through Laravel's HTTP client; a client that
 * built its own Guzzle instance would slip past this, **which is one of the
 * reasons the token refreshers do not use Socialite's**". An SDK carrying its own
 * Guzzle would punch a hole in a guarantee this project deliberately built, and
 * this is the vendor where a stray live call costs money per token (decision 277).
 *
 * THREE MODEL DIFFERENCES THAT ARE 400s, NOT WARNINGS, and all three are one
 * `platform_settings` row apart from each other:
 *
 *   - `temperature` / `top_p` / `top_k` were **removed** on Opus 4.7 and later.
 *     Sending one is a 400. Haiku 4.5 still accepts them.
 *   - `output_config.effort` **errors on Haiku 4.5**. It arrived with the Opus
 *     4.5 generation and was never retrofitted to the small model.
 *   - On Claude Opus 5 **thinking is on unless disabled**, and `max_tokens` caps
 *     thinking *plus* response text together.
 *
 * Each is answered by an AiModel predicate rather than a literal here, because
 * the model serving a task is configuration and the request shape has to follow
 * it without anyone remembering to.
 *
 * THINKING IS LEFT ON, DELIBERATELY (decision 279). Disabling it would shave a
 * fraction of a cent off a reply — and on Opus 5, disabled thinking can leak
 * `<thinking>` tags into the visible response. This application's visible
 * response is a reply **published under a business's name on their Google
 * listing**. Low effort keeps the reasoning shallow and cannot leak.
 */
final class AnthropicClient implements AiClient
{
    private const string ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * Pinned, not floating. Anthropic versions its API by date and an unpinned
     * client inherits breaking changes on somebody else's schedule.
     */
    private const string API_VERSION = '2023-06-01';

    public function __construct(
        private readonly AiModel $model,
    ) {}

    public function complete(AiRequest $request): AiResponse
    {
        if (! PlatformCredentials::has($this->model->provider()->credentialKey())) {
            VendorLog::failure('anthropic', 'POST', self::ENDPOINT, 'credential_not_configured', Tenancy::id());

            return AiResponse::failed($this->model, 'credential_not_configured');
        }

        try {
            $response = VendorLog::timed(
                'anthropic',
                'POST',
                self::ENDPOINT,
                fn (): Response => $this->request()->post(self::ENDPOINT, $this->body($request)),
                Tenancy::id(),
            );
        } catch (ConnectionException) {
            VendorLog::failure('anthropic', 'POST', self::ENDPOINT, ConnectionException::class, Tenancy::id());

            return AiResponse::failed($this->model, 'unreachable');
        }

        if ($response->failed()) {
            VendorLog::failure('anthropic', 'POST', self::ENDPOINT, 'http_'.$response->status(), Tenancy::id());

            return AiResponse::failed($this->model, 'http_'.$response->status());
        }

        return $this->interpret($response, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(AiRequest $request): array
    {
        $body = [
            'model' => $this->model->apiModelId(),
            'max_tokens' => $request->task->maxOutputTokens(),
            'messages' => [
                ['role' => 'user', 'content' => $request->prompt],
            ],
        ];

        if ($request->system !== null) {
            $body['system'] = $request->system;
        }

        $outputConfig = [];

        if ($request->wantsJson()) {
            // Structured outputs, supported on every Anthropic model this enum
            // carries. Constrains the response to the schema rather than asking
            // for JSON in the prompt and hoping — which is the difference between
            // a parse failure being impossible and being intermittent.
            $outputConfig['format'] = [
                'type' => 'json_schema',
                'schema' => $request->jsonSchema,
            ];
        }

        if ($this->model->supportsEffort()) {
            // Low, on every tier. These are short, well-specified jobs — classify
            // a review, write two sentences in a given voice — and effort buys
            // depth this application has no use for. It is also what keeps
            // thinking shallow without disabling it; see the class docblock.
            $outputConfig['effort'] = 'low';
        }

        if ($outputConfig !== []) {
            $body['output_config'] = $outputConfig;
        }

        // No temperature, top_p or top_k anywhere — not merely omitted for the
        // models that reject them, but never sent at all. Prompting is the
        // supported way to steer these models, and a parameter that is a 400 on
        // one tier and a no-op on another is not worth carrying.
        return $body;
    }

    private function interpret(Response $response, AiRequest $request): AiResponse
    {
        // ⛔ **`Response::json()` RETURNS `null` FOR AN EMPTY OR NON-JSON BODY, AND
        // THE ANNOTATION ABOVE IT SAID OTHERWISE** (4194). The docblock asserted
        // `array<string, mixed>`, static analysis believed it, and
        // {@see self::text()} takes a typed `array` — so a **200 with a body that
        // is not JSON** threw a `TypeError` out of a class whose contract, and
        // `AiRouter`'s, is that it never throws for a vendor reason. Rail 6 rests
        // on that contract: the customer gets the standing line when the model is
        // unreachable, and a crash here is a queued job dying instead.
        //
        // ⚠️ **IT IS NOT A THEORETICAL BODY.** A CDN or proxy error page, a
        // truncated response and an empty 200 are all HTML or nothing, and none of
        // them is a vendor outage this application can see coming. **Found by P19's
        // mutation campaign** — a mutation to a different class reached this line
        // and the test errored rather than failing, which is the tell.
        //
        // ⚠️ **THE OTHER TWO CLIENTS DO NOT HAVE IT**: `OpenAiClient` and
        // `OpenAiEmbeddingClient` read every field through `data_get()`, which is
        // null-safe, and hand nothing to a typed parameter. Checked rather than
        // assumed.
        $decoded = $response->json();

        /** @var array<string, mixed> $payload */
        $payload = is_array($decoded) ? $decoded : [];

        $inputTokens = (int) data_get($payload, 'usage.input_tokens', 0);
        $outputTokens = (int) data_get($payload, 'usage.output_tokens', 0);

        // BEFORE reading content, always. A refusal is a successful 200 whose
        // content array is empty or partial, so anything that indexes content
        // first breaks here — silently, and only on the awkward inputs. The
        // classifiers see customer-written review text, which is the one input
        // nobody in this system controls.
        if (data_get($payload, 'stop_reason') === 'refusal') {
            $category = data_get($payload, 'stop_details.category');

            return AiResponse::refused(
                $this->model,
                is_string($category) ? $category : null,
                $inputTokens,
                $outputTokens,
            );
        }

        $text = $this->text($payload);

        if ($text === null) {
            return AiResponse::failed($this->model, 'no_text_content');
        }

        return AiResponse::answered(
            $this->model,
            $text,
            $request->wantsJson() ? $this->decode($text) : null,
            $inputTokens,
            $outputTokens,
        );
    }

    /**
     * The first text block, ignoring thinking blocks.
     *
     * Thinking blocks arrive in `content` alongside the answer and are not the
     * answer. On Opus 5 their text is empty by default anyway, but filtering by
     * type rather than taking `content[0]` is what keeps this correct if anyone
     * ever turns summarised thinking on to debug a prompt.
     *
     * @param  array<string, mixed>  $payload
     */
    private function text(array $payload): ?string
    {
        $blocks = data_get($payload, 'content');

        if (! is_array($blocks)) {
            return null;
        }

        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                return $block['text'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $text): ?array
    {
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => PlatformCredentials::get($this->model->provider()->credentialKey()),
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout((int) config('ai.timeout', 60))
            ->acceptJson();
    }
}
