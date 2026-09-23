<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiTask;

/**
 * One thing asked of a model, in provider-neutral terms.
 *
 * Deliberately small. Everything a provider needs that is *not* here — the model
 * id, the token ceiling, the effort level, whether sampling parameters are legal
 * — is derived from the task and the model rather than passed in, so a caller
 * cannot accidentally send a parameter that 400s on one model and works on
 * another (AiModel::rejectsSamplingParameters).
 *
 * `jsonSchema` is how a caller asks for structured output. It is a request, not
 * a guarantee: a refusal or a truncation still produces no usable JSON, which is
 * why AiResponse reports both separately.
 *
 * NO BUSINESS ID, DELIBERATELY. Every AI call is billed to a tenant and the
 * ledger is tenant-owned, so the tenant is read from Tenancy at the point of
 * record rather than passed in. A parameter would let a caller name a business
 * other than the ambient one — which is the *wrong tenant* case, the one
 * `CLAUDE.md` is explicit that RLS "happily returns the wrong tenant's rows" for.
 * A value that cannot be supplied cannot be supplied wrongly.
 */
final readonly class AiRequest
{
    /**
     * @param  array<string, mixed>|null  $jsonSchema  A JSON Schema the response
     *                                                 must satisfy, or null for free text.
     */
    public function __construct(
        public AiTask $task,
        public string $prompt,
        public ?string $system = null,
        public ?array $jsonSchema = null,
        public ?string $promptKey = null,
    ) {}

    public function wantsJson(): bool
    {
        return $this->jsonSchema !== null;
    }
}
