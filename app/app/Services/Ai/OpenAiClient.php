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
 * OpenAI's Chat Completions API, on Laravel's HTTP client.
 *
 * Endpoint, headers and body fields read from OpenAI's live documentation on
 * AiModel::VERIFIED_ON — including one field that would have been wrong from
 * memory: the output ceiling is **`max_completion_tokens`**, and `max_tokens` is
 * deprecated for current models. That is the same class of error as slice B's
 * Atmosphere price, caught the same way: by reading rather than recalling.
 *
 * THE SECOND PROVIDER, WIRED BEFORE ANYTHING NEEDS IT. No task tier defaults to
 * OpenAI today (AiTask). It exists because `CLAUDE.md` requires multi-provider
 * from day one, and because a router with one provider wired cannot honour a
 * setting naming the other — the abstraction is only tested by a second
 * implementation living inside it.
 *
 * DIFFERENCES FROM THE ANTHROPIC CLIENT WORTH KNOWING, since they are the reason
 * the interface is shaped the way it is rather than passing provider bodies
 * through:
 *
 *   auth        `Authorization: Bearer`, not `x-api-key`
 *   ceiling     `max_completion_tokens`, not `max_tokens`
 *   schema      `response_format.json_schema`, nested one level deeper, and
 *               `strict: true` additionally requires `additionalProperties:
 *               false` with every property listed in `required`
 *   refusal     a `refusal` string on the message, not a `stop_reason`
 *   usage       `prompt_tokens` / `completion_tokens`, not `input_tokens` /
 *               `output_tokens`
 */
final class OpenAiClient implements AiClient
{
    private const string ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly AiModel $model,
    ) {}

    public function complete(AiRequest $request): AiResponse
    {
        if (! PlatformCredentials::has($this->model->provider()->credentialKey())) {
            VendorLog::failure('openai', 'POST', self::ENDPOINT, 'credential_not_configured', Tenancy::id());

            return AiResponse::failed($this->model, 'credential_not_configured');
        }

        try {
            $response = VendorLog::timed(
                'openai',
                'POST',
                self::ENDPOINT,
                fn (): Response => $this->request()->post(self::ENDPOINT, $this->body($request)),
                Tenancy::id(),
            );
        } catch (ConnectionException) {
            VendorLog::failure('openai', 'POST', self::ENDPOINT, ConnectionException::class, Tenancy::id());

            return AiResponse::failed($this->model, 'unreachable');
        }

        if ($response->failed()) {
            VendorLog::failure('openai', 'POST', self::ENDPOINT, 'http_'.$response->status(), Tenancy::id());

            return AiResponse::failed($this->model, 'http_'.$response->status());
        }

        return $this->interpret($response, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(AiRequest $request): array
    {
        $messages = [];

        if ($request->system !== null) {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }

        $messages[] = ['role' => 'user', 'content' => $request->prompt];

        $body = [
            'model' => $this->model->value,
            'messages' => $messages,
            // Not `max_tokens` — deprecated for current models. See the class
            // docblock; this is the field a from-memory implementation gets wrong.
            'max_completion_tokens' => $request->task->maxOutputTokens(),
        ];

        if ($request->wantsJson()) {
            $body['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->task->value,
                    // `strict` additionally constrains the schema itself: every
                    // property must be listed in `required` and
                    // `additionalProperties` must be false. Callers build schemas
                    // that satisfy that, because the alternative is structured
                    // output that silently is not structured.
                    'strict' => true,
                    'schema' => $request->jsonSchema,
                ],
            ];
        }

        return $body;
    }

    private function interpret(Response $response, AiRequest $request): AiResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        $inputTokens = (int) data_get($payload, 'usage.prompt_tokens', 0);
        $outputTokens = (int) data_get($payload, 'usage.completion_tokens', 0);

        // Before reading content, as on the Anthropic path — the shape differs
        // but the hazard is identical: a refusal is a successful 200 whose
        // content is null.
        $refusal = data_get($payload, 'choices.0.message.refusal');

        if (is_string($refusal) && $refusal !== '') {
            return AiResponse::refused($this->model, $refusal, $inputTokens, $outputTokens);
        }

        $text = data_get($payload, 'choices.0.message.content');

        if (! is_string($text)) {
            return AiResponse::failed($this->model, 'no_text_content');
        }

        $decoded = $request->wantsJson() ? json_decode($text, true) : null;

        return AiResponse::answered(
            $this->model,
            $text,
            is_array($decoded) ? $decoded : null,
            $inputTokens,
            $outputTokens,
        );
    }

    private function request(): PendingRequest
    {
        return Http::withToken(PlatformCredentials::get($this->model->provider()->credentialKey()))
            ->timeout((int) config('ai.timeout', 60))
            ->acceptJson();
    }
}
