<?php

declare(strict_types=1);

namespace App\Services\Actuation\WordPress;

/**
 * One site's REST root and the login that opens it, in the shape every request
 * is built from.
 *
 * ⛔ **THE ONLY OBJECT IN `app/` THAT HOLDS A TENANT'S WORDPRESS PASSWORD IN
 * PLAINTEXT, AND IT IS BUILT AS LATE AS POSSIBLE AND HELD AS BRIEFLY.**
 * {@see WordPressCredentials} constructs one per call and hands it straight to
 * {@see WordPressRestClient}; nothing stores it, nothing returns it, and no
 * value object above it carries one.
 *
 * ⚠️ **`__debugInfo()` IS NOT DECORATION.** Symfony's var-dumper — which is what
 * `dd()`, `dump()`, Laravel's exception page and Ignition all use — honours it,
 * so the one accident this class actually has to survive is somebody dumping a
 * request that failed. It is a second layer under the model's `$hidden`, not a
 * replacement for it: `$hidden` closes serialization, this closes inspection,
 * and `PlatformCredential`'s docblock is right that the inspection path is the
 * likelier one.
 *
 * ⛔ **AND IT IS NOT A GUARANTEE.** `var_export()`, `serialize()`, a manually
 * built array and a step debugger all still see the property. What closes those
 * is that only two classes ever hold one of these, which is a chokepoint lint
 * rather than a promise.
 *
 * ⚠️ **THE PASSWORD IS AN {@see ApplicationPassword} RATHER THAN A `string`, AND
 * THAT IS THE THIRD LAYER.** A PHP stack trace prints scalar arguments; it
 * prints an object as its class name. See that class for the measurement.
 */
final readonly class WordPressSite
{
    public function __construct(
        public string $restRoot,
        public string $username,
        public ApplicationPassword $applicationPassword,
    ) {}

    /**
     * The absolute URL of a REST route on this site, query string included.
     *
     * ⚠️ **BOTH DISCOVERY SHAPES ARE ROOTS AND ONLY ONE OF THEM IS A PATH.**
     * WordPress's discovery documentation gives `https://example.com/wp-json/`
     * for a site with pretty permalinks and `https://example.com/?rest_route=/`
     * for one without, and tells clients to *"ensure that both routes can be
     * handled seamlessly"*. Both are roots you append a route to, which is why
     * one line handles them — but the query string is not, and that is the trap:
     * on the second shape a `?context=edit` produces a URL with two `?`, and the
     * route parameter is the half that gets dropped.
     *
     * ⛔ **AND THE QUERY IS BUILT HERE RATHER THAN PASSED TO THE HTTP CLIENT FOR
     * EXACTLY THAT REASON.** Guzzle's `query` request option is documented to
     * *overwrite* the query string already in the URI, so
     * `Http::get($this->route('wp/v2/posts'), ['slug' => 'x'])` would throw the
     * site's own `rest_route` away and ask for the front page. The failure is a
     * `200 text/html` rather than an error, on the sites least likely to have
     * anybody who can diagnose it.
     *
     * @param  array<string, scalar>  $query
     */
    public function route(string $path, array $query = []): string
    {
        $root = str_ends_with($this->restRoot, '/') ? $this->restRoot : $this->restRoot.'/';

        $url = $root.ltrim($path, '/');

        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'restRoot' => $this->restRoot,
            'username' => '[redacted]',
            'applicationPassword' => '[redacted]',
        ];
    }
}
