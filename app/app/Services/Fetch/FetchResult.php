<?php

declare(strict_types=1);

namespace App\Services\Fetch;

use App\Enums\FetchOutcome;
use App\Enums\FetchRefusalReason;
use App\Enums\FetchTier;

/**
 * What came back from a fetch attempt, including the ones that never happened.
 *
 * A returned value rather than an exception for refusals and blocks, because
 * `40` §6.4 makes the *reason* a fetch failed into what the owner is told:
 * "checked {date}" for a degraded source, a guided-fix packet for a
 * `guided_only` one, and never "current" for either. An exception flattens all
 * of that into a catch block.
 *
 * `body` is null unless the outcome is Ok. There is no partial success here —
 * a challenge page has a 200 and a body, and treating that body as content is
 * how a bot-check page ends up parsed as a business's NAP data.
 *
 * ⚠️ **`headers` IS AN ALLOWLIST AND IS EMPTY FOR EVERY OUTCOME BUT `Ok`**
 * (5545). Row 9 slice B needs to know whether a tenant's site sits behind a
 * Cloudflare proxy, and that is a fact only the response headers carry — `server:
 * cloudflare` and `cf-ray`, neither of which appears anywhere in the HTML. The
 * alternative was a second fetch of `/cdn-cgi/trace`, which asks a stranger's
 * origin for a path their robots.txt may well disallow in order to learn
 * something the first response already said.
 *
 * ⛔ **AN ALLOWLIST RATHER THAN THE WHOLE BAG, ON `CLAUDE.md`'s SECOND
 * TIE-BREAKER.** A response from somebody else's website carries `set-cookie`
 * among other things; keeping only the four names something in this application
 * reads means nothing else can end up in a queue payload, a failed-job row or an
 * exception message. {@see DirectFetchGateway::RECORDED_HEADERS}
 * is the list and the argument for each entry.
 */
final readonly class FetchResult
{
    /**
     * @param  array<string, string>  $headers  The allowlisted response headers —
     *                                          see {@see DirectFetchGateway::RECORDED_HEADERS}.
     */
    public function __construct(
        public FetchOutcome $outcome,
        public FetchTier $tier,
        public ?string $body = null,
        public ?int $status = null,
        public ?FetchRefusalReason $refusalReason = null,
        public array $headers = [],
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public static function ok(FetchTier $tier, string $body, int $status, array $headers = []): self
    {
        return new self(FetchOutcome::Ok, $tier, $body, $status, headers: $headers);
    }

    /**
     * One allowlisted response header, lower-cased, or null.
     *
     * ⚠️ **A READER RATHER THAN A PUBLIC ARRAY WALK**, because the header set is
     * an allowlist and a caller reaching for a name that is not on it should get
     * null rather than a subtly wrong answer from a differently-cased key. HTTP
     * header names are case-insensitive and PHP array keys are not.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * The gateway declined on policy.
     *
     * ⚠️ **A TYPE RATHER THAN A SHORT CODE SINCE 9480–9499.** It was a bare
     * string, hand-copied into `AuthorByline::ROBOTS_REFUSAL` and held there by
     * a lint over raw source. It is still never a sentence and never the URL —
     * these reach logs — and {@see FetchRefusalReason}'s values are the strings
     * that were already being written, so an old log line still matches.
     */
    public static function refused(FetchTier $tier, FetchRefusalReason $reason): self
    {
        return new self(FetchOutcome::Refused, $tier, refusalReason: $reason);
    }

    public static function failed(FetchOutcome $outcome, FetchTier $tier, ?int $status = null): self
    {
        return new self($outcome, $tier, status: $status);
    }

    public function successful(): bool
    {
        return $this->outcome->isSuccess() && $this->body !== null;
    }

    /**
     * Whether the caller should present this as "we did not look" rather than
     * "we looked and found nothing" — a distinction `40` §6.4 requires the owner
     * surface to preserve.
     */
    public function wasRefused(): bool
    {
        return $this->outcome === FetchOutcome::Refused;
    }
}
