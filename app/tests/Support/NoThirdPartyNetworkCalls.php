<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * FPR-01's "no third-party network calls" acceptance criterion, checked
 * against rendered markup rather than trusted, and shared so the check is
 * identical wherever a page makes the same claim — a fix applied to one
 * caller is not a gap left in another.
 *
 * SCANS THREE THINGS, BECAUSE A THIRD PARTY CAN ARRIVE THROUGH ANY OF THEM.
 * An attribute-only scan (`src`/`href`/`srcset`) catches a pasted script or
 * stylesheet tag, but misses exactly where a font family would actually
 * land: `@fonts` emits an inline `<style>` block whose every rule is
 * `src: url("...")` — a CSS declaration, not an attribute. And the linked
 * build CSS is never opened by an attribute scan either, so an
 * `@import url(https://...)` inside the compiled output would pass silently.
 * This class reads that file off `public/build` and scans it too.
 *
 * BOTH QUOTE STYLES, and the host comparison is port-aware. An attribute is
 * legally single-quoted, and `parse_url(..., PHP_URL_HOST)` alone ignores the
 * port — which would classify `http://localhost:5173/...` as internal against
 * `APP_URL=http://localhost`, a different origin wearing the same hostname.
 */
final class NoThirdPartyNetworkCalls
{
    /**
     * Every URL found in `$html` that resolves to a different origin than
     * `config('app.url')`. An empty list is the assertion this class exists
     * to make possible.
     *
     * @return list<string>
     */
    public static function externalUrlsIn(string $html): array
    {
        $urls = self::candidateUrls($html);

        foreach (self::linkedBuildCssFiles($html) as $cssContent) {
            array_push($urls, ...self::candidateUrls($cssContent));
        }

        return array_values(array_unique(array_filter($urls, self::isExternal(...))));
    }

    /**
     * Every URL-shaped string in `$html`: attribute values first, then any
     * bare `url(...)` and `@import "..."` wherever they appear in the body —
     * which is where a CSS rule inside an inline `<style>` block puts one.
     *
     * @return list<string>
     */
    private static function candidateUrls(string $html): array
    {
        $urls = [];

        // srcset packs one or more "url descriptor" pairs into a single
        // attribute value, comma-separated — src/href never do, but it costs
        // nothing to handle both the same way.
        preg_match_all('/(?:src|href|srcset)\s*=\s*(["\'])(.*?)\1/i', $html, $attributeMatches);

        foreach ($attributeMatches[2] as $attributeValue) {
            foreach (explode(',', $attributeValue) as $candidate) {
                $url = strtok(trim($candidate), " \t\n");

                if ($url !== false && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        preg_match_all('/url\(\s*(["\']?)([^)"\']*)\1\s*\)/i', $html, $urlFunctionMatches);
        array_push($urls, ...array_filter($urlFunctionMatches[2], fn (string $url): bool => $url !== ''));

        preg_match_all('/@import\s+(["\'])([^"\']+)\1/i', $html, $importMatches);
        array_push($urls, ...$importMatches[2]);

        return $urls;
    }

    /**
     * The contents of every same-origin `.css` file this markup links to,
     * read straight off `public/build` — the file `@vite` points at, which
     * nothing else in a feature test ever opens.
     *
     * @return list<string>
     */
    private static function linkedBuildCssFiles(string $html): array
    {
        preg_match_all('/href\s*=\s*(["\'])([^"\']+\.css)\1/i', $html, $matches);

        $files = [];

        foreach ($matches[2] as $href) {
            $path = parse_url($href, PHP_URL_PATH);

            if (! is_string($path) || ! str_starts_with($path, '/build/')) {
                continue;
            }

            $absolute = public_path(ltrim($path, '/'));

            if (is_file($absolute)) {
                $files[] = (string) file_get_contents($absolute);
            }
        }

        return $files;
    }

    /**
     * Whether `$url` reaches a different origin than `config('app.url')`.
     *
     * A raw string prefix (`str_starts_with($url, config('app.url'))`) would
     * classify `http://localhost.evil.tld/x` as internal to `http://localhost`
     * — comparing parsed host and port is what actually answers "same origin".
     * A relative or root-relative path (no `//`) is always internal: it cannot
     * name another host at all.
     */
    private static function isExternal(string $url): bool
    {
        if (preg_match('#^(?:https?:)?//#i', $url) !== 1) {
            return false;
        }

        $appUrl = (string) config('app.url');

        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $appPort = parse_url($appUrl, PHP_URL_PORT);

        return $host !== $appHost || $port !== $appPort;
    }
}
