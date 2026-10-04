<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/**
 * The products a business's own online store publishes about itself, read from the page's schema.org data (the
 * `<script type="application/ld+json">` blocks Shopify, WooCommerce, Squarespace and Wix write for search engines). Only
 * what the store states is kept — a name, a price as the store gives it, a short description, a picture address and the
 * product's own page — so a products section is filled from real products, never from the AI (the 2026-10-04 ruling).
 */
final class SiteProductSignals
{
    public const MAX_PER_PAGE = 24;

    private const SYMBOLS = ['USD' => '$', 'CAD' => 'CA$', 'AUD' => 'A$', 'NZD' => 'NZ$', 'GBP' => '£', 'EUR' => '€', 'PHP' => '₱'];

    /**
     * @return list<array{name: string, url: string, price_text?: string, description?: string, image_url?: string}>
     */
    public static function read(string $html, string $pageUrl): array
    {
        if (preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m) < 1) {
            return [];
        }

        $products = [];
        foreach ($m[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5), true);
            if (! is_array($data)) {
                continue;
            }
            foreach (self::nodes($data) as $node) {
                $product = self::product($node, $pageUrl);
                if ($product !== null && ! isset($products[mb_strtolower($product['name'])])) {
                    $products[mb_strtolower($product['name'])] = $product;
                }
                if (count($products) >= self::MAX_PER_PAGE) {
                    break 2;
                }
            }
        }

        return array_values($products);
    }

    /**
     * Every object in a JSON-LD document that could be a product: the top level, a list, an @graph, and an ItemList's items.
     *
     * @param  array<mixed>  $data
     * @return list<array<string, mixed>>
     */
    private static function nodes(array $data, int $depth = 0): array
    {
        if ($depth > 4) {
            return [];
        }
        if (array_is_list($data)) {
            $out = [];
            foreach ($data as $item) {
                if (is_array($item)) {
                    $out = array_merge($out, self::nodes($item, $depth + 1));
                }
            }

            return $out;
        }
        $out = [$data];
        foreach (['@graph', 'itemListElement'] as $key) {
            if (is_array($data[$key] ?? null)) {
                $out = array_merge($out, self::nodes($data[$key], $depth + 1));
            }
        }
        if (is_array($data['item'] ?? null)) {
            $out = array_merge($out, self::nodes($data['item'], $depth + 1));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{name: string, url: string, price_text?: string, description?: string, image_url?: string}|null
     */
    private static function product(array $node, string $pageUrl): ?array
    {
        $types = (array) ($node['@type'] ?? []);
        if (! in_array('Product', $types, true)) {
            return null;
        }
        $name = self::text($node['name'] ?? null, 120);
        if ($name === null) {
            return null;
        }

        $url = self::text($node['url'] ?? null, 500) ?? $pageUrl;
        $product = ['name' => $name, 'url' => self::safeUrl($url, $pageUrl) ?? $pageUrl];

        $offer = $node['offers'] ?? null;
        if (is_array($offer) && array_is_list($offer)) {
            $offer = is_array($offer[0] ?? null) ? $offer[0] : null;
        }
        if (is_array($offer)) {
            $currency = strtoupper((string) ($offer['priceCurrency'] ?? ''));
            $price = $offer['price'] ?? null;
            $low = $offer['lowPrice'] ?? null;
            if (is_numeric($price)) {
                $product['price_text'] = self::money((float) $price, $currency);
            } elseif (is_numeric($low)) {
                $product['price_text'] = 'from '.self::money((float) $low, $currency);
            }
        }

        $description = self::text(strip_tags((string) (is_scalar($node['description'] ?? null) ? $node['description'] : '')), 240);
        if ($description !== null) {
            $product['description'] = $description;
        }

        $image = $node['image'] ?? null;
        if (is_array($image)) {
            $image = array_is_list($image) ? ($image[0] ?? null) : ($image['url'] ?? null);
            if (is_array($image)) {
                $image = $image['url'] ?? null;
            }
        }
        $imageUrl = is_string($image) ? self::safeUrl($image, $pageUrl) : null;
        if ($imageUrl !== null) {
            $product['image_url'] = $imageUrl;
        }

        return $product;
    }

    private static function money(float $amount, string $currency): string
    {
        $number = fmod($amount, 1.0) === 0.0 ? number_format($amount, 0) : number_format($amount, 2);
        $symbol = self::SYMBOLS[$currency] ?? null;

        return $symbol !== null ? $symbol.$number : trim($currency.' '.$number);
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5)));

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    /** An http(s) address, made absolute against the page; anything else (javascript:, data:) is refused. */
    private static function safeUrl(string $url, string $pageUrl): ?string
    {
        $url = trim($url);
        if (str_starts_with($url, '//')) {
            $url = (parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https').':'.$url;
        } elseif (str_starts_with($url, '/')) {
            $scheme = parse_url($pageUrl, PHP_URL_SCHEME);
            $host = parse_url($pageUrl, PHP_URL_HOST);
            if (! is_string($scheme) || ! is_string($host)) {
                return null;
            }
            $url = $scheme.'://'.$host.$url;
        }

        return preg_match('#^https?://[^\s"<>]+$#i', $url) === 1 ? $url : null;
    }
}
