<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Contracts\AuditCheck;
use App\Enums\AuditCheckKey;
use App\Enums\FetchOutcome;
use App\Services\Audit\AuditContext;
use App\Services\Audit\CheckResult;
use App\Services\Audit\Finding;
use App\Services\Audit\PageText;
use App\Services\Fetch\FetchResult;

/**
 * `29` §6.2's fourth check: "site basics: reachable, HTTPS, title/schema
 * presence, mobile viewport, fetch-timing speed hint".
 *
 * THE HARD PART IS TELLING "BROKEN" FROM "WE WERE NOT ALLOWED TO LOOK", and this
 * check is where that distinction has consequences. `40` §6.4 requires the two
 * to stay separate all the way to the owner's screen, and here they invert the
 * finding:
 *
 *   no website on the listing   a finding — the worst one this check can make
 *   5xx, 4xx, empty body        a finding — the site genuinely did not serve
 *   robots disallow, kill       unavailable — the site is fine, our policy said no
 *   403/429, bot challenge      unavailable — the site is up and declined *us*
 *
 * Reporting a Cloudflare challenge as "your website is down" would be a false
 * accusation about working infrastructure, and it is the single most likely way
 * this check embarrasses us — bot protection on a small-business site is normal
 * and its owner has no idea it is there.
 *
 * THE SPEED HINT IS A HINT AND SAYS SO. One fetch from one server on one network
 * is not a performance measurement, and `29` §6.2 calls it a "fetch-timing speed
 * hint" for that reason. The threshold is deliberately far out, where the answer
 * is not ambiguous.
 */
final class SiteBasicsCheck implements AuditCheck
{
    /**
     * Slower than this and something is wrong that a visitor will feel. Well
     * past any reasonable connection variance, because one sample is all we have.
     */
    private const float SLOW_SECONDS = 4.0;

    public function key(): AuditCheckKey
    {
        return AuditCheckKey::SiteBasics;
    }

    public function run(AuditContext $context): CheckResult
    {
        if ($context->place === null) {
            // No listing means we never learned whether there is a website, so
            // "your listing has no website" would be a claim about a listing we
            // could not read. The order of these two guards is the difference
            // between an honest gap and an invented finding.
            return CheckResult::unavailable(
                $this->key(),
                $context->placeUnavailableReason ?? 'no_place',
            );
        }

        if ($context->siteUrl === null) {
            // Not an inability to look — there is nothing to look at, and that
            // is itself the most consequential thing this check can report.
            return CheckResult::ran($this->key(), [
                Finding::critical(
                    $this->key(),
                    'site.none_listed',
                    'Your Google listing has no website. Every person who wants to check you out before calling has nowhere to go.',
                ),
            ]);
        }

        $fetch = $context->siteFetch;

        if (! $fetch instanceof FetchResult) {
            return CheckResult::unavailable($this->key(), 'not_fetched');
        }

        if (! $fetch->successful()) {
            return $this->unsuccessfulFetch($fetch);
        }

        $body = $fetch->body ?? '';
        $findings = [
            Finding::healthy(
                $this->key(),
                'site.reachable',
                'Your website loaded when we checked.',
            ),
            $this->httpsFinding($context->siteUrl),
            $this->titleFinding($body),
            $this->schemaFinding($body),
            $this->viewportFinding($body),
        ];

        $speed = $this->speedFinding($context->siteFetchSeconds);

        if ($speed instanceof Finding) {
            $findings[] = $speed;
        }

        return CheckResult::ran($this->key(), $findings);
    }

    /**
     * A fetch that did not return a page — split into the site's fault and ours.
     */
    private function unsuccessfulFetch(FetchResult $fetch): CheckResult
    {
        if ($fetch->wasRefused()) {
            return CheckResult::unavailable($this->key(), 'fetch_refused');
        }

        return match ($fetch->outcome) {
            // The site is up and chose not to serve us. Saying anything about
            // its health from this would be wrong.
            FetchOutcome::Blocked, FetchOutcome::Challenge => CheckResult::unavailable(
                $this->key(),
                'fetch_'.$fetch->outcome->value,
            ),

            FetchOutcome::Empty => CheckResult::ran($this->key(), [
                Finding::critical(
                    $this->key(),
                    'site.empty',
                    'Your website answered but returned a blank page. To a search engine that is the same as having no website.',
                ),
            ]),

            default => CheckResult::ran($this->key(), [
                Finding::critical(
                    $this->key(),
                    'site.unreachable',
                    $fetch->status !== null
                        ? sprintf('Your website returned an error (%d) when we checked. Customers clicking through from Google are seeing the same thing.', $fetch->status)
                        : 'Your website did not respond when we checked. Customers clicking through from Google are seeing the same thing.',
                    $fetch->status !== null ? ['status' => $fetch->status] : [],
                ),
            ]),
        };
    }

    private function httpsFinding(string $url): Finding
    {
        return str_starts_with(strtolower($url), 'https://')
            ? Finding::healthy(
                $this->key(),
                'site.https',
                'Your website is served over a secure connection.',
            )
            : Finding::critical(
                $this->key(),
                'site.no_https',
                'Your website is not served over a secure connection, so browsers show visitors a "Not secure" warning in the address bar.',
            );
    }

    private function titleFinding(string $body): Finding
    {
        $title = PageText::title($body);

        return $title !== null
            ? Finding::healthy(
                $this->key(),
                'site.title_present',
                'Your homepage has a title, which is the line people read in search results.',
            )
            : Finding::critical(
                $this->key(),
                'site.title_missing',
                'Your homepage has no title, so search engines invent the line people read about you in results.',
            );
    }

    private function schemaFinding(string $body): Finding
    {
        return PageText::hasStructuredData($body)
            ? Finding::healthy(
                $this->key(),
                'site.schema_present',
                'Your homepage carries structured data, which is how search engines read your hours and location.',
            )
            : Finding::attention(
                $this->key(),
                'site.schema_missing',
                'Your homepage has no structured data, so search engines have to guess your hours, address and phone number from the page text.',
            );
    }

    private function viewportFinding(string $body): Finding
    {
        return PageText::hasViewportMeta($body)
            ? Finding::healthy(
                $this->key(),
                'site.viewport_present',
                'Your homepage is set up to resize for phones.',
            )
            : Finding::critical(
                $this->key(),
                'site.viewport_missing',
                'Your homepage is not set up to resize for phones, and most people finding you on Google are on one.',
            );
    }

    private function speedFinding(?float $seconds): ?Finding
    {
        if ($seconds === null) {
            return null;
        }

        return $seconds > self::SLOW_SECONDS
            ? Finding::attention(
                $this->key(),
                'site.slow',
                sprintf('Your homepage took %.1f seconds to answer us. That is long enough that some visitors leave before it appears.', $seconds),
                ['seconds' => round($seconds, 2)],
            )
            : Finding::healthy(
                $this->key(),
                'site.responsive_timing',
                'Your homepage answered quickly.',
                ['seconds' => round($seconds, 2)],
            );
    }
}
