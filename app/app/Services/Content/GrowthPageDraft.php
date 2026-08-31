<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\GrowthPageType;

/**
 * A candidate page, as handed to {@see GrowthPages::draft()}.
 *
 * ⛔ **TYPED FIELDS, NEVER A BLOB OF HTML** — 220/306's family, and the same
 * argument `BUILD-PLAN` §2.11.3 slice I makes about the T3 payload endpoint:
 * whatever this becomes is written onto somebody else's website, so the shape
 * that reaches it must be one this application can read, gate and put back.
 *
 * ⚠️ **THIS IS NOT A GENERATOR AND MUST NOT GROW INTO ONE.** §2.11.5 conflict 5:
 * the content engine and the Orchestrator's DIAGNOSE are Stage 5. What this
 * carries is a candidate somebody or something else composed; slice C's job is
 * to judge it.
 */
final readonly class GrowthPageDraft
{
    public function __construct(
        public GrowthPageType $type,
        /** The path on the tenant's own site — never a full URL; see the migration. */
        public string $slug,
        public PageCopy $copy,
        public ?string $targetKeyword = null,
    ) {}
}
