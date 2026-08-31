<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\DataClassification;
use App\Enums\KnowledgeSourceStatus;
use App\Models\Business;
use App\Models\KnowledgeSource;
use App\Services\Ai\AiSpend;
use App\Services\Knowledge\KnowledgeIngestor;

/**
 * Reads one uploaded document into the Business Brain.
 *
 * AN AutopilotJob RATHER THAN A PLAIN QUEUED JOB, for AnalyzeReviewJob's reasons
 * exactly: it supplies the tenant a queued job would otherwise lack, the kill
 * switch, the tenant's own pause, the idempotency key, and a run row that
 * records the outcome whatever it was. `29` §2 rule 40 asks for all of that and
 * for backoff, which the base class also provides.
 *
 * ⚠️ **QUEUED IS NOT A PERFORMANCE CHOICE HERE, IT IS A RULE.** Embedding a
 * document is a vendor call, and `29` §2 forbids an LLM call on the synchronous
 * path. `ArchitectureTest` enforces it structurally rather than by convention:
 * `AiRouter` may not be named under `Http/Controllers`, `Http/Middleware`,
 * `Livewire` or `View/Components`, so the upload screen can only dispatch this.
 *
 * ⚠️ **PHI FIRST, AND THE ORDER IS LOAD-BEARING** — AnalyzeReviewJob's gate,
 * applied to a strictly worse input. A review is a customer's sentence about a
 * practice; **an uploaded document from a dental or medical tenant is whatever
 * they chose to upload**, which can be a patient handout, an intake form, or a
 * spreadsheet somebody exported without thinking. `29` §2 rule 24 forbids
 * sending it to a provider with no BAA, and `SUBPROCESSOR-INVENTORY.md` records
 * that no BAA exists with either. So a PHI tenant's document is never embedded
 * at all, and the source is left visibly unread rather than quietly skipped.
 *
 * ⚠️ **THE PER-REVIEW CONSENT CHECKBOX OF 2079–2081 DOES NOT REACH HERE.** That
 * ruling moved the AI-analysis withhold from per-tenant to per-review by asking
 * *the reviewer* to agree not to include health information. There is no
 * reviewer on this path and nobody to ask: the tenant uploads a file, and a
 * tenant cannot consent on behalf of the patients named inside it. Reading 2079
 * as a general relaxation would be the safety net rewritten to approve of the
 * thing it caught, which 2081 forbids by name.
 *
 * ## Idempotency, and why the key is versioned
 *
 * The key names the source *and* its current version, so a re-upload — which
 * bumps the version — is a genuinely new unit of work, while a redelivery of the
 * same message is not. Without the version, a corrected document would be a
 * permanent no-op against the unique index and the owner would never learn why.
 */
final class IngestKnowledgeSourceJob extends AutopilotJob
{
    private bool $ingested = false;

    private int $chunkCount = 0;

    /**
     * Memoised answer to "may this tenant's content reach a model at all".
     */
    private ?bool $phiWithheld = null;

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $sourceId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'knowledge.ingest';
    }

    protected function idempotencyKey(): string
    {
        // The version is read at dispatch time on purpose — see the class
        // docblock. A source read at version 1 and re-uploaded is version 2, and
        // the two are different units of work.
        $version = KnowledgeSource::query()->whereKey($this->sourceId)->value('version');

        return 'ingest-knowledge:'.$this->sourceId.':'.($version ?? 1);
    }

    /**
     * ⚠️ FALSE UNLESS SOMETHING WAS ACTUALLY STORED, SO A FAILED ATTEMPT CAN BE
     * RETRIED. The base class's default is to keep the claim, which is right for
     * a job whose side effect is a message to somebody's customer and wrong for
     * this one: nothing here is irreversible, and a kept claim after a vendor
     * outage would make every later dispatch a silent no-op against the unique
     * index — a document that never becomes readable and never says why.
     */
    protected function claimIsSpent(): bool
    {
        return $this->ingested;
    }

    protected function canExecute(): bool
    {
        // PHI first, and the cap second. A PHI tenant's document may not be sent
        // at any spend, so asking AiSpend first would make the reason recorded
        // on the run row depend on how much budget happened to be left — and
        // only one of the two is a compliance boundary.
        return ! $this->phiWithheld() && app(AiSpend::class)->allows();
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $source = $this->source();

        if (! $source instanceof KnowledgeSource) {
            return ['skipped' => 'source_not_found'];
        }

        $source->forceFill(['status' => KnowledgeSourceStatus::Ingesting])->save();

        $outcome = app(KnowledgeIngestor::class)->ingest($source);

        if (! $outcome->succeeded()) {
            // The ingestor writes the terminal status on success only, so the
            // non-success statuses are written here — one place, and it cannot
            // be reached without an outcome to write.
            $source->forceFill(['status' => $outcome->status])->save();
        }

        $this->ingested = $outcome->succeeded();
        $this->chunkCount = $outcome->chunks;

        return $outcome->forRunRow();
    }

    /**
     * The same document, without a model.
     *
     * NOT A STUB. `29` §2 rule 44 wants this path built in the same ticket, and
     * here it has real work to do: the source is left in a state the owner can
     * read, with the reason recorded, rather than sitting at "pending" forever
     * while a cap resets or a compliance position is settled.
     *
     * ⚠️ TWO REASONS, NOT ONE, AND THE RUN ROW IS WHERE SOMEBODY LEARNS WHICH.
     * `ai_unavailable` clears — a cap resets on the 1st, an outage ends — and a
     * retry picks it up. `phi_withheld` never clears: it is the correct
     * permanent state for this tenant until rule 24's KMS key and BAA exist, and
     * recording it as an outage would send whoever reads the row looking for a
     * provider incident that never happened.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        $source = $this->source();

        if (! $source instanceof KnowledgeSource) {
            return ['skipped' => 'source_not_found'];
        }

        $withheld = $this->phiWithheld();

        $source->forceFill([
            // Withheld for PHI is terminal for this tenant, so `Unreadable` —
            // the status whose whole meaning is "waiting will not help". An
            // exhausted cap is ours and clears, so `Failed`.
            'status' => $withheld
                ? KnowledgeSourceStatus::Unreadable
                : KnowledgeSourceStatus::Failed,
        ])->save();

        return [
            'ingested' => false,
            'reason' => $withheld ? 'phi_withheld' : 'ai_unavailable',
        ];
    }

    private function phiWithheld(): bool
    {
        return $this->phiWithheld ??= Business::query()
            ->whereKey($this->businessId)
            ->first()?->data_classification === DataClassification::Phi;
    }

    private function source(): ?KnowledgeSource
    {
        return KnowledgeSource::query()->find($this->sourceId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return parent::input() + ['source_id' => $this->sourceId];
    }

    /**
     * Silence unless a document actually became readable.
     *
     * AutopilotJob's docblock names the automation "whose only honest title is
     * 'checked something and found nothing'" as the one that makes the feed
     * worse. A failed read is that: the owner sees the source's own status on
     * the Brain screen, which is where they are looking. A document that *is*
     * now answerable is news — it is the thing they uploaded it for.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->ingested ? AutopilotActionType::AutomationCompleted : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function activityMetadata(): array
    {
        // NEVER THE DOCUMENT TEXT, and never its title. The feed is
        // owner-visible on every staff surface and the audit log beside it is
        // what an erasure request has to honour; an id and a count answer "did
        // my upload work" without putting a tenant's own document contents into
        // a second store.
        return [
            'automation' => $this->automationKey(),
            'source_id' => $this->sourceId,
            'chunks' => $this->chunkCount,
        ];
    }
}
