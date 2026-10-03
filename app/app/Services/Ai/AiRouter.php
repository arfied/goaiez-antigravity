<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\AiClient;
use App\Contracts\EmbeddingClient;
use App\Enums\AiModel;
use App\Enums\AiProvider;
use App\Enums\PlatformHealthSignal;
use App\Modules\X220\Actions\PromptRegisterAction;
use App\Services\Ops\PlatformHealth;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * The admin-editable model router (row 3 slice A0).
 *
 * THE ONLY WAY THIS APPLICATION CALLS A MODEL. Nothing constructs an
 * AnthropicClient or an OpenAiClient directly, because everything a caller
 * would have to remember lives here: which model serves this task today, whether
 * the tenant is inside the interim spend ceiling, and writing the call down
 * afterwards — both what it cost us and what it charges the tenant. A call site
 * that bypassed the router would work perfectly and quietly spend money nobody
 * is charged for, so ArchitectureTest asserts the AiClient implementations have
 * no callers outside this **directory** — corrected 2026-08-28 (11456). This
 * said *"outside this class"*, and `AiTest`'s rule is the directory arm: any
 * file under `app/Services/Ai/` may name a client, and `AiRouter::embed()`
 * already states it correctly a hundred-odd lines below. ⚠️ **The directory arm
 * needs no stale guard because it fails LOUD** — move the directory and every
 * client-naming file becomes an offender at once — which is why wave 43 deleted
 * the redundant one-entry allowlist beside it (11393).
 *
 * ✅ **THE CEILING THIS CHECKS IS THE TENANT'S AI CREDIT BALANCE — AND, BEHIND
 * IT, `ai.monthly_cap_per_tenant`** (decisions 3293, 3295, 3608, 3820). The owner
 * deleted the per-tenant dollar cost cap and made the credit balance the only
 * ceiling; 3608 built the balance refusal and removed the interim cap, and 3820
 * put the cap back **behind** it, because a balance bounds only a tenant who has
 * one and today every account is unfunded until it verifies. **`AiSpend::allows()`
 * is still the question this class asks** — it now answers with both.
 *
 * ⚠️ **A REFUSAL HERE IS NOT PROOF THE TENANT IS OUT OF CREDIT ON EVERY PATH, OR
 * OUT OF CREDIT AT ALL.** The gate is the AI product's balance alone; texts and
 * emails have their own, and a tenant out of AI credit still sends invites. A
 * refusal may also be the platform cost cap rather than the tenant's balance, and
 * this class does not tell the two apart — the caller records that no model output
 * arrived and leaves the work for a human either way.
 *
 * NEVER ON A SYNCHRONOUS PATH. `29` §2 forbids an LLM call on the synchronous
 * context path, which must answer in under 200ms p95 from materialised state.
 * Every caller of this class is a queued job, and that too is a build-failing
 * test rather than a convention.
 *
 * NEVER THROWS FOR A VENDOR OR BUDGET REASON, inheriting AiClient's contract.
 * Callers are queued jobs whose correct response to "no model output" is to
 * record it and leave the work for a human — not to crash and retry a bill.
 */
final class AiRouter
{
    public function __construct(
        private readonly AiSpend $spend,
        /**
         * ⚠️ **HERE FOR THE LOG LINE AND NOTHING ELSE.** The refusal itself is
         * `AiSpend::allows()`'s, so this class asks one collaborator whether to
         * proceed; the balance is read only to say *how much was left* in the
         * warning an operator will actually read. A refusal with no figure on it
         * is the line somebody raises a ticket about.
         */
        private readonly AiCredits $credits = new AiCredits,
        /**
         * ⚠️ **THE PLATFORM'S COUNTER, NOT THE TENANT'S METER.** `AiSpend`
         * above records what this call cost and who owes it; this records only
         * that a provider answered or did not, so that P23 can tell an operator
         * their model provider is down. Defaulted rather than injected so that
         * every existing construction site of this class — and there are
         * several in tests — keeps working: an alerting counter must never be
         * the reason a model call cannot be made (R25).
         */
        private readonly PlatformHealth $health = new PlatformHealth,
    ) {}

    /**
     * Run one request against whichever model currently serves its task.
     */
    public function dispatch(AiRequest $request): AiResponse
    {
        // A caller may name the model (the site designer compares four AIs); otherwise the task's setting decides.
        $model = $request->model ?? $this->spend->modelFor($request->task);

        // ⚠️ THE WRONG-DOOR GUARD, AND IT IS WHAT MAKES AiTask::maxOutputTokens()'
        // ZERO ARM UNREACHABLE RATHER THAN MERELY UNUSED. An embedding tier sent
        // through here would build a chat-completions body around a model that
        // has no chat endpoint — a 404 the caller reads as an outage — and would
        // do it with a token ceiling of zero. Refused as a failure rather than
        // thrown, because every caller of this class is a queued job and this
        // class's contract is that it never throws.
        if ($request->task->producesEmbedding() || $request->task->producesImage()) {
            Log::warning('embedding or image task sent to the completion path', [
                'task' => $request->task->value,
                'model' => $model->value,
            ]);

            return AiResponse::failed($model, 'not_a_completion_task');
        }

        // A picture or embedding model named for a text task (by a caller, or by an admin setting) has no text client;
        // refused here so this method keeps its promise never to throw.
        if ($model->isImage() || $model->isEmbedding()) {
            return AiResponse::failed($model, 'not_a_completion_model');
        }

        // Resolved before the cap is checked, because the cap is a sum over a
        // tenant-scoped table and an unscoped sum would be the platform's spend
        // measured against one tenant's ceiling.
        if (! $this->spend->hasTenant()) {
            Log::warning('ai call outside tenant context', [
                'task' => $request->task->value,
                'model' => $model->value,
            ]);

            return AiResponse::failed($model, 'no_tenant_context');
        }

        $refusal = $this->spend->refusal();

        if ($refusal !== null) {
            // Logged, deliberately NOT written to the ledger. The ledger records
            // spend, and this call never left the building — but more than that:
            // the thing this ceiling exists to catch is a runaway loop, and a loop
            // that has already exhausted the balance would write a refusal row per
            // iteration, turning a cost incident into a storage incident.
            //
            // ⛔ THE LINE NAMES WHICH CEILING REFUSED AND CARRIES BOTH FIGURES
            // (3962). It said "ai credit exhausted" with a balance beside it for
            // all three refusals, and for two of them that was the wrong sentence
            // and the wrong number: the cost cap has nothing to do with the
            // tenant's balance, and a lapsed plan leaves a balance that may be
            // large and unspendable (3441). An operator reading a healthy figure
            // under a message about exhausted credit raises a ticket about the
            // wrong thing.
            Log::warning('ai call refused by a ceiling', [
                'task' => $request->task->value,
                'model' => $model->value,
                'refusal' => $refusal,
                'spent_hundredths' => $this->spend->spentThisMonthHundredths(),
                'cap_hundredths' => $this->spend->monthlyCapHundredths(),
                'balance_hundredths' => $this->credits->balanceHundredths(),
            ]);

            return AiResponse::failed($model, $refusal);
        }

        $key = $request->promptKey ?? $request->task->value;
        $effectiveText = ($request->system ?? '').$request->prompt;

        // @phpstan-ignore class.notFound
        $registered = app(PromptRegisterAction::class)->handle(
            Tenancy::idOrFail(),
            $key,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? 'unknown',
            $effectiveText
        );

        $promptId = $registered->id;
        $promptVersion = $registered->version;

        $response = $this->client($model)->complete($request);

        // Recorded whatever happened — answered, refused, or failed. A refusal
        // bills for what was generated before the classifier fired, and a call
        // that errors after the model has read the prompt can still bill for
        // input. See AiSpend::record().
        $this->spend->record($request->task, $response, $promptId, $promptVersion);

        $this->watch($model, $response->failureReason);

        return $response;
    }

    /**
     * Turn a batch of texts into vectors, through the same meter and brake.
     *
     * ⚠️ **THIS EXISTS SO THE EMBEDDING PATH CANNOT BE THE ONE THAT SKIPS THE
     * CAP.** `ArchitectureTest` refuses any mention of a provider client outside
     * `Services/Ai`, so an ingest job cannot reach `OpenAiEmbeddingClient`
     * directly — but a lint only stops the mistake it was written for, and the
     * substantive reason is the one in this class's own docblock: a call site
     * that bypassed the router would work perfectly and quietly spend unmetered
     * money. Embedding a corpus is the largest single batch of vendor calls this
     * application makes, so it is the worst candidate for the exception.
     *
     * Every guard `dispatch()` applies applies here in the same order and for
     * the same reasons: no tenant, no call; no budget, no call; and whatever
     * happened is written to the ledger afterwards.
     */
    public function embed(EmbeddingRequest $request): EmbeddingResponse
    {
        $model = $this->spend->modelFor($request->task);

        // The mirror of dispatch()'s guard. A completion tier arriving here
        // would post `messages`-shaped work to an embeddings endpoint and get a
        // 400 that names a field rather than the mistake.
        if (! $request->task->producesEmbedding()) {
            Log::warning('completion task sent to the embedding path', [
                'task' => $request->task->value,
                'model' => $model->value,
            ]);

            return EmbeddingResponse::failed($model, 'not_an_embedding_task');
        }

        if (! $this->spend->hasTenant()) {
            Log::warning('ai call outside tenant context', [
                'task' => $request->task->value,
                'model' => $model->value,
            ]);

            return EmbeddingResponse::failed($model, 'no_tenant_context');
        }

        $refusal = $this->spend->refusal();

        if ($refusal !== null) {
            // The same line as `dispatch()`'s, for the same reason (3962) — and it
            // is spelled out twice rather than extracted because the two paths
            // return different response types and a helper would have to hand back
            // a reason for the caller to wrap, which is what this already is.
            Log::warning('ai call refused by a ceiling', [
                'task' => $request->task->value,
                'model' => $model->value,
                'refusal' => $refusal,
                'spent_hundredths' => $this->spend->spentThisMonthHundredths(),
                'cap_hundredths' => $this->spend->monthlyCapHundredths(),
                'balance_hundredths' => $this->credits->balanceHundredths(),
            ]);

            return EmbeddingResponse::failed($model, $refusal);
        }

        // @phpstan-ignore class.notFound
        $registered = app(PromptRegisterAction::class)->handle(
            Tenancy::idOrFail(),
            $request->task->value,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? 'unknown',
            ''
        );

        $response = $this->embeddingClient($model)->embed($request);

        $this->spend->recordEmbedding($request->task, $response, $registered->id, $registered->version);

        $this->watch($model, $response->failureReason);

        return $response;
    }

    public function image(ImageRequest $request): ImageResponse
    {
        // A caller may name the picture model (FLUX once a fal.ai key exists); otherwise the task's setting decides.
        $model = $request->model ?? $this->spend->modelFor($request->task);

        if (! $request->task->producesImage() || ! $model->isImage()) {
            Log::warning('non-image task sent to the image path', [
                'task' => $request->task->value,
                'model' => $model->value,
            ]);

            return ImageResponse::failed($model, 'not_an_image_task');
        }

        if (! $this->spend->hasTenant()) {
            Log::warning('ai call outside tenant context', [
                'task' => $request->task->value,
                'model' => $model->value,
            ]);

            return ImageResponse::failed($model, 'no_tenant_context');
        }

        $refusal = $this->spend->refusal();

        if ($refusal !== null) {
            Log::warning('ai call refused by a ceiling', [
                'task' => $request->task->value,
                'model' => $model->value,
                'refusal' => $refusal,
                'spent_hundredths' => $this->spend->spentThisMonthHundredths(),
                'cap_hundredths' => $this->spend->monthlyCapHundredths(),
                'balance_hundredths' => $this->credits->balanceHundredths(),
            ]);

            return ImageResponse::failed($model, $refusal);
        }

        // @phpstan-ignore class.notFound
        $registered = app(PromptRegisterAction::class)->handle(
            Tenancy::idOrFail(),
            $request->task->value,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? 'unknown',
            $request->prompt
        );

        $response = match ($model->provider()) {
            AiProvider::Fal => (new FalImageClient($model))->generate($request),
            AiProvider::OpenAi => (new OpenAiImageClient($model))->generate($request),
            AiProvider::Anthropic, AiProvider::Xai, AiProvider::Gemini => ImageResponse::failed($model, 'no_image_client'),
        };

        $this->spend->recordImage($request->task, $response, $registered->id, $registered->version);

        $this->watch($model, $response->failureReason);

        return $response;
    }

    /**
     * Tell the platform-health counter what the provider just did — P23's
     * model-API error rate.
     *
     * ⚠️ **HERE RATHER THAN IN THE CLIENTS, AND THE REASON IS THIS CLASS'S OWN
     * DOCBLOCK**: nothing constructs a provider client directly and a lint
     * enforces it, so this is the one place every model call in the application
     * passes through. Recording in each client would be two copies to keep in
     * step and a third the day a provider is added.
     *
     * ⚠️ **AFTER THE GUARDS, NEVER BEFORE THEM.** A call refused by a ceiling or
     * by a missing tenant never reached the provider, and counting it as a
     * failure would make an exhausted balance look like an outage — alerting an
     * operator about a vendor that is working perfectly, which is the fastest
     * way to teach them to ignore the alert.
     *
     * ⛔ **A REFUSAL IS NOT COUNTED AS A FAILURE EITHER**, and that is why this
     * reads `failureReason` rather than `isUsable()`: `AiResponse::refused()`
     * carries no failure reason, because a model declining to answer is the
     * safety classifier doing its job.
     *
     * ⛔ **AND IT RECORDS THE PROVIDER, NEVER THE TENANT.** The counter is
     * platform-scoped by construction — see its migration — and this call runs
     * inside whichever tenant's job made it. A per-tenant model-error rate is the
     * tenant dashboard's subject, not this one's.
     */
    private function watch(AiModel $model, ?string $failureReason): void
    {
        $provider = $model->provider()->value;

        if ($failureReason === null) {
            $this->health->recordSuccess(PlatformHealthSignal::VendorCall, $provider);

            return;
        }

        $this->health->recordFailure(PlatformHealthSignal::VendorCall, $provider);
    }

    /**
     * The embedding client for a model's provider.
     *
     * ⚠️ **ONE ARM, AND IT IS NOT AN OVERSIGHT.** Anthropic publishes no
     * embeddings API — its own documentation says so and points at a third
     * vendor — so there is no second implementation to write. The `match` is
     * still a `match` rather than a bare `new`, because it is what fails the
     * build the day somebody adds an Anthropic embedding model to AiModel
     * without noticing there is nowhere to send it. `AiSpend::modelFor()` keeps
     * a settings row from reaching this with a completion model.
     */
    private function embeddingClient(AiModel $model): EmbeddingClient
    {
        return match ($model->provider()) {
            AiProvider::OpenAi => new OpenAiEmbeddingClient($model),
            AiProvider::Anthropic => throw new LogicException(
                'No embedding client exists for '.$model->provider()->label().'. Anthropic '
                .'publishes no embeddings API; if that changes, add the client here rather '
                .'than letting this model reach the OpenAI one.',
            ),
            AiProvider::Xai, AiProvider::Gemini, AiProvider::Fal => throw new LogicException(
                'No embedding client exists for '.$model->provider()->label().'.'
            ),
        };
    }

    /**
     * The client for a model's provider.
     *
     * A `match` rather than a container binding, because the container cannot
     * express "an AiClient for *this* model" — the model is what decides the
     * endpoint, the auth header, the token-ceiling field name and which request
     * parameters are legal. Both implementations go through Laravel's HTTP
     * client, so `Http::fake()` substitutes either one without a binding, and
     * `Http::preventStrayRequests()` still covers both.
     */
    private function client(AiModel $model): AiClient
    {
        return match ($model->provider()) {
            AiProvider::Anthropic => new AnthropicClient($model),
            AiProvider::OpenAi => new OpenAiClient($model),
            AiProvider::Xai => new XaiClient($model),
            AiProvider::Gemini => new GeminiClient($model),
            AiProvider::Fal => throw new LogicException('fal.ai serves pictures only; no completion client exists for it.'),
        };
    }
}
