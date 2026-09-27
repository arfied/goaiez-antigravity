<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Contracts\FetchGateway;
use App\Models\Competitor;
use App\Models\CompetitorSiteNote;
use App\Models\Location;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use DOMDocument;

/**
 * The one reader/writer of competitor_site_notes. For each nearby peer that
 * Google lists a website for, one honest F0 fetch under the `competitor_site`
 * source (robots consulted, its own rate budget), and a NOTE: title, meta
 * description, the first headings. Nothing else leaves the response — no body
 * text, no image, no link. A refusal is recorded with its reason, never retried
 * inside the window, and never described to anyone as the peer telling us "no".
 */
final class CompetitorSiteNotes
{
    public const SOURCE = 'competitor_site';

    public const MAX_HEADINGS = 20;

    public const HEADING_MAX_CHARS = 160;

    public const FRESH_DAYS = 7;

    public function __construct(private readonly FetchGateway $fetcher) {}

    /** Notes every peer of the location that has a website and no fresh note. Returns how many were fetched. */
    public function refreshForLocation(Location $location): int
    {
        Tenancy::idOrFail();
        $fetched = 0;
        $peers = Competitor::query()->where('location_id', $location->id)->whereNotNull('website_url')->get();
        foreach ($peers as $peer) {
            $existing = CompetitorSiteNote::query()->where('competitor_id', $peer->id)->first();
            if ($existing !== null && $existing->fetched_at->greaterThan(CarbonImmutable::now()->subDays(self::FRESH_DAYS))) {
                continue;
            }
            $this->note($peer);
            $fetched++;
        }

        return $fetched;
    }

    public function note(Competitor $peer): CompetitorSiteNote
    {
        $url = (string) $peer->website_url;
        $result = $this->fetcher->fetch(self::SOURCE, $url);

        $values = [
            'business_id' => $peer->business_id,
            'url' => mb_substr($url, 0, 2048),
            'fetched_at' => now(),
            'refusal_reason' => null,
        ];

        if (! $result->successful()) {
            $values['status'] = $result->wasRefused() ? 'refused' : 'failed';
            $values['refusal_reason'] = $result->refusalReason->value ?? $result->outcome->value;
            $values['title'] = null;
            $values['description'] = null;
            $values['headings'] = null;
        } else {
            $values['status'] = 'noted';
            $values = array_merge($values, $this->extract((string) $result->body));
        }

        return CompetitorSiteNote::query()->updateOrCreate(['competitor_id' => $peer->id], $values);
    }

    /**
     * The notes worth showing a model as reference: the newest five that were
     * actually read, each with the peer's name. Reference only — a caller puts
     * these in a prompt as "what businesses like this cover", never into a page.
     *
     * @return list<array{name: string, title: ?string, description: ?string, headings: list<string>}>
     */
    public function notesFor(int $businessId): array
    {
        return CompetitorSiteNote::query()
            ->with('competitor')
            ->where('business_id', $businessId)
            ->where('status', 'noted')
            ->orderByDesc('fetched_at')
            ->limit(5)
            ->get()
            ->map(fn (CompetitorSiteNote $n): array => [
                'name' => (string) ($n->competitor->name ?? 'A nearby business'),
                'title' => $n->title,
                'description' => $n->description,
                'headings' => is_array($n->headings) ? array_values($n->headings) : [],
            ])
            ->all();
    }

    /** The prompt section built from notesFor(), or '' when there are none. */
    public function referenceBlock(int $businessId): string
    {
        $notes = $this->notesFor($businessId);
        if ($notes === []) {
            return '';
        }
        $lines = ['What nearby businesses like this one cover on their websites — REFERENCE ONLY. Use it to know what to cover; never reuse their names, sentences or wording:'];
        foreach ($notes as $n) {
            $parts = array_filter([$n['title'], $n['description'], implode(' | ', $n['headings'])], fn ($p) => $p !== null && $p !== '');
            $lines[] = '- '.$n['name'].': '.implode(' — ', $parts);
        }

        return implode("\n", $lines);
    }

    /**
     * What the nearby peers' sites cover, for an owner screen that must not name
     * them (decision 196): a count and the de-duplicated heading topics.
     *
     * @return array{read: int, topics: list<string>}
     */
    public function topicsFor(int $businessId, int $maxTopics = 12): array
    {
        $notes = $this->notesFor($businessId);
        $topics = [];
        foreach ($notes as $n) {
            foreach ($n['headings'] as $h) {
                $key = mb_strtolower(trim($h));
                if ($key !== '' && ! isset($topics[$key])) {
                    $topics[$key] = mb_substr(trim($h), 0, 80);
                }
            }
        }

        return ['read' => count($notes), 'topics' => array_slice(array_values($topics), 0, $maxTopics)];
    }

    /** @return array{title: ?string, description: ?string, headings: list<string>} */
    private function extract(string $html): array
    {
        $dom = new DOMDocument;
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);

        $title = trim((string) ($dom->getElementsByTagName('title')->item(0)->textContent ?? ''));
        $description = '';
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower((string) $meta->getAttribute('name')) === 'description') {
                $description = trim((string) $meta->getAttribute('content'));
                break;
            }
        }

        $headings = [];
        for ($level = 1; $level <= 3 && count($headings) < self::MAX_HEADINGS; $level++) {
            foreach ($dom->getElementsByTagName('h'.$level) as $node) {
                $text = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');
                if ($text !== '') {
                    $headings[] = mb_substr($text, 0, self::HEADING_MAX_CHARS);
                }
                if (count($headings) >= self::MAX_HEADINGS) {
                    break;
                }
            }
        }

        return [
            'title' => $title === '' ? null : mb_substr($title, 0, 300),
            'description' => $description === '' ? null : mb_substr($description, 0, 600),
            'headings' => $headings,
        ];
    }
}
