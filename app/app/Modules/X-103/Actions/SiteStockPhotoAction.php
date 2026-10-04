<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Services\Images\PixabayClient;
use Illuminate\Support\Facades\Storage;

/**
 * A stock photo for a site template's banner when the business has no wide photo of its own (the boss's template brief,
 * 2026-10-04: the business's own photos first, "a vertical-specific approved stock set" next, never a made-up picture passed off
 * as the business). Each template searches for a general scene of its trade — tools, a treatment room, a plate, a desk — and a
 * photo without people is preferred, so no stranger is ever presented as the business's staff. The photo is downloaded to our own
 * storage (Pixabay forbids hotlinking) and its alt text says it is a stock photo.
 */
final readonly class SiteStockPhotoAction
{
    /** What each template's banner searches Pixabay for. */
    public const QUERIES = [
        'trades-pro' => 'plumbing tools pipes',
        'trades-clean' => 'home repair tools',
        'trades-heritage' => 'carpentry workshop tools',
        'calm-spa' => 'spa stones towels candles',
        'spa-luxe' => 'spa candles massage room',
        'spa-bright' => 'spa towels flowers',
        'polish-bar' => 'nail polish bottles',
        'nail-studio' => 'manicure nail polish',
        'nail-pop' => 'colorful nail polish',
        'maker-market' => 'handmade pottery shelf',
        'corner-boutique' => 'boutique shop interior',
        'shop-bold' => 'retail store shelves',
        'bistro-table' => 'restaurant table dinner',
        'cafe-corner' => 'coffee cafe pastry',
        'grill-house' => 'grilled food barbecue',
        'garage-pro' => 'car engine repair tools',
        'detail-studio' => 'car wash foam',
        'hometown-auto' => 'auto repair shop',
        'counsel' => 'office desk documents',
        'clear-office' => 'modern office workspace',
        'main-street' => 'office desk coffee',
    ];

    /** Tags that mean a person is in the photo. */
    private const PEOPLE = ['woman', 'women', 'man', 'men', 'girl', 'boy', 'people', 'person', 'child', 'lady', 'guy', 'businessman',
        'businesswoman', 'female', 'male', 'model', 'portrait', 'face', 'couple', 'family', 'worker', 'team', 'mechanic', 'chef', 'waiter', 'barista'];

    public function __construct(private PixabayClient $pixabay) {}

    /** Whether a stock photo can be looked for at all (the Pixabay key is set). */
    public function available(): bool
    {
        return $this->pixabay->configured();
    }

    /**
     * @return array{status: 'stored', path: string, alt: string, width: int, height: int, page_url: string}|array{status: 'skipped'|'none', reason: string}
     */
    public function handle(int $businessId, string $templateId): array
    {
        $query = self::QUERIES[$templateId] ?? null;
        if ($query === null) {
            return ['status' => 'none', 'reason' => 'no_query'];
        }
        if (! $this->pixabay->configured()) {
            return ['status' => 'skipped', 'reason' => 'credential_not_configured'];
        }

        // A banner wants a wide photo.
        $hits = array_values(array_filter($this->pixabay->search($query, $businessId),
            static fn (array $hit): bool => $hit['height'] > 0 && $hit['width'] >= $hit['height'] * 1.3));
        if ($hits === []) {
            return ['status' => 'none', 'reason' => 'no_results'];
        }
        $scenes = array_values(array_filter($hits, static fn (array $hit): bool => ! self::showsPeople($hit['tags'])));
        $pool = $scenes !== [] ? $scenes : $hits;
        // Neighbouring businesses of one kind do not all get the same photo.
        $hit = $pool[$businessId % count($pool)];

        $bytes = $this->pixabay->download($hit['url'], $businessId);
        if ($bytes === null) {
            return ['status' => 'none', 'reason' => 'download_failed'];
        }
        $size = @getimagesizefromstring($bytes);
        if (! is_array($size)) {
            return ['status' => 'none', 'reason' => 'not_an_image'];
        }
        $ext = match ($size['mime']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($ext === null) {
            return ['status' => 'none', 'reason' => 'not_an_image'];
        }

        $path = "site-inventory/{$businessId}/stock-pixabay-{$hit['id']}.{$ext}";
        Storage::disk('local')->put($path, $bytes);
        $tags = array_slice(array_values(array_filter(array_map('trim', explode(',', $hit['tags'])), static fn (string $t): bool => $t !== '')), 0, 3);

        return [
            'status' => 'stored',
            'path' => $path,
            'alt' => mb_substr(($tags === [] ? 'Photo' : ucfirst(implode(', ', $tags))).' (stock photo)', 0, 120),
            'width' => (int) $size[0],
            'height' => (int) $size[1],
            'page_url' => $hit['page_url'],
        ];
    }

    private static function showsPeople(string $tags): bool
    {
        foreach (array_map(static fn (string $t): string => mb_strtolower(trim($t)), explode(',', $tags)) as $tag) {
            foreach (explode(' ', $tag) as $word) {
                if (in_array($word, self::PEOPLE, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
