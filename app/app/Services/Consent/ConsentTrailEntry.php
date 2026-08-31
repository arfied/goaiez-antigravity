<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\ConsentEventType;
use App\Enums\OutreachChannel;
use App\Models\ConsentRecord;
use App\Models\SuppressionListEntry;
use Carbon\CarbonImmutable;

/**
 * One event in a contact's consent history, whichever table it came from.
 *
 * WHY THIS EXISTS. `17` COMP-01's third acceptance criterion is that consent
 * proof is retrievable for any contact, and `29` §19.4 makes "the consent audit
 * export returns a complete trail" build-failing. `proofFor()` used to return
 * `ConsentRecord` rows only — so for somebody who had sent STOP, the trail
 * showed two grants of consent and no withdrawal. Every row in it was true and
 * the document as a whole was false, in the one artifact a regulator or a
 * carrier would actually read.
 *
 * The underlying model is kept rather than flattened into strings, because an
 * export needs the proof blob, the disclosure version and the capture surface,
 * and inventing a second shape for those invites the two drifting apart.
 */
final readonly class ConsentTrailEntry
{
    private function __construct(
        public ConsentEventType $type,
        public OutreachChannel $channel,
        public ?CarbonImmutable $occurredAt,
        public ?ConsentRecord $consentRecord,
        public ?SuppressionListEntry $withdrawal,
    ) {}

    public static function forConsent(ConsentRecord $record): self
    {
        return new self(
            type: ConsentEventType::Consent,
            channel: $record->channel,
            occurredAt: $record->created_at?->toImmutable(),
            consentRecord: $record,
            withdrawal: null,
        );
    }

    public static function forWithdrawal(SuppressionListEntry $entry): self
    {
        return new self(
            type: ConsentEventType::Withdrawal,
            channel: $entry->channel,
            occurredAt: $entry->created_at?->toImmutable(),
            consentRecord: null,
            withdrawal: $entry,
        );
    }

    /**
     * Why this event happened, in the words already stored for it.
     *
     * A grant says which surface captured it; a withdrawal says what triggered
     * it. Both are what somebody reading the trail is actually asking.
     */
    public function reason(): string
    {
        if ($this->withdrawal !== null) {
            return $this->withdrawal->reason;
        }

        if ($this->consentRecord !== null) {
            return $this->consentRecord->capture_surface->value;
        }

        // Unreachable through the two factories, which is the whole point of the
        // constructor being private: an entry is one or the other, never neither.
        return '';
    }
}
