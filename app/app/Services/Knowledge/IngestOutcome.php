<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\KnowledgeSourceStatus;

/**
 * What happened to one source, in terms the run row and the owner both use.
 *
 * ⚠️ **A STATUS AND A REASON, NEVER A BARE BOOLEAN.** `automation_runs.output`
 * is what somebody reads after the fact, and "false" answers none of the
 * questions they will have: was this our outage or their file, will it fix
 * itself, is it worth retrying. The three states this can carry map exactly onto
 * the three the owner is shown.
 */
final readonly class IngestOutcome
{
    private function __construct(
        public KnowledgeSourceStatus $status,
        public int $chunks,
        public ?string $reason = null,
    ) {}

    public static function ingested(int $chunks): self
    {
        return new self(KnowledgeSourceStatus::Ingested, $chunks);
    }

    /**
     * Ours, and it clears on a retry.
     */
    public static function failed(string $reason): self
    {
        return new self(KnowledgeSourceStatus::Failed, 0, $reason);
    }

    /**
     * Theirs, and no amount of retrying changes it.
     */
    public static function unreadable(string $reason): self
    {
        return new self(KnowledgeSourceStatus::Unreadable, 0, $reason);
    }

    public function succeeded(): bool
    {
        return $this->status === KnowledgeSourceStatus::Ingested;
    }

    /**
     * @return array<string, mixed>
     */
    public function forRunRow(): array
    {
        return array_filter([
            'status' => $this->status->value,
            'chunks' => $this->chunks,
            'reason' => $this->reason,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
