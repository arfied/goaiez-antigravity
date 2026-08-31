<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditCheckKey;
use App\Enums\FindingSeverity;

/**
 * One thing the audit noticed, in a sentence a business owner can act on.
 *
 * `29` §6.2: findings are '"12 reviews have no reply"-style … each mapped to a
 * plain sentence + severity'. That example is the whole specification of tone —
 * a number, a noun, and a consequence, with no vocabulary from inside the
 * system. `22`'s outcome-language rule says the same thing as a rule.
 *
 * `code` IS THE STABLE PART AND `sentence` IS NOT. The sentence is copy; it will
 * be rewritten, and it has to be translatable (decision 158 — Spanish is a
 * launch commitment). The code is what slice H matches on when it pre-fills the
 * wizard, what the first-week engine reuses as "we found and fixed N things"
 * (`29` §6.2's third use), and what any later comparison of two audits joins on.
 * Never key logic on the sentence.
 *
 * NOTHING HERE IS PERSONAL DATA. Findings land in `public_audits.findings`,
 * which has no tenant, a shareable URL and a 90-day life. Decision 213 already
 * dropped review text on arrival; the same restraint applies to everything a
 * finding carries — counts and facts about a public listing, never a customer's
 * words and never a competitor's name (decision 196).
 */
final readonly class Finding
{
    /**
     * @param  string  $code  Stable identifier, `check.condition` — never rendered.
     * @param  string  $sentence  What the owner reads. Plain, specific, no jargon.
     * @param  array<string, int|float|string|bool|null>  $context
     *                                                              The numbers behind the sentence, for slice G's
     *                                                              rendering and slice H's pre-fill. Scalars only:
     *                                                              this is JSONB that outlives the code that wrote it.
     */
    public function __construct(
        public AuditCheckKey $check,
        public string $code,
        public FindingSeverity $severity,
        public string $sentence,
        public array $context = [],
    ) {}

    /**
     * @param  array<string, int|float|string|bool|null>  $context
     */
    public static function critical(AuditCheckKey $check, string $code, string $sentence, array $context = []): self
    {
        return new self($check, $code, FindingSeverity::Critical, $sentence, $context);
    }

    /**
     * @param  array<string, int|float|string|bool|null>  $context
     */
    public static function attention(AuditCheckKey $check, string $code, string $sentence, array $context = []): self
    {
        return new self($check, $code, FindingSeverity::Attention, $sentence, $context);
    }

    /**
     * @param  array<string, int|float|string|bool|null>  $context
     */
    public static function healthy(AuditCheckKey $check, string $code, string $sentence, array $context = []): self
    {
        return new self($check, $code, FindingSeverity::Healthy, $sentence, $context);
    }

    /**
     * The JSONB shape. Flat and scalar-valued, so a reader six months from now
     * can query it without knowing this class exists.
     *
     * @return array{check: string, code: string, severity: string, sentence: string, context: array<string, int|float|string|bool|null>}
     */
    public function toArray(): array
    {
        return [
            'check' => $this->check->value,
            'code' => $this->code,
            'severity' => $this->severity->value,
            'sentence' => $this->sentence,
            'context' => $this->context,
        ];
    }
}
