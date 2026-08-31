<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * A URL as a scannable QR code, drawn as inline SVG.
 *
 * ⚠️ **NO NEW DEPENDENCY, AND THE ENCODER IS NOT OURS.**
 * `bacon/bacon-qr-code` arrives with Fortify — {@see
 * \App\Http\Controllers\Auth\TwoFactorSetupController} already renders a QR
 * server-side through it — so the hard half here (ISO/IEC 18004: version
 * selection, Reed-Solomon, mask choice) is the vendor's. What this class adds
 * is the drawing.
 *
 * ⛔ **WHY THE DRAWING IS OURS WHEN THE VENDOR SHIPS `SvgImageBackEnd`.** That
 * renderer emits one `fill-rule="evenodd"` path traced around the outline of
 * the whole symbol. It renders beautifully and **it cannot be read back**:
 * recovering modules from an even-odd polygon means point-in-polygon over a
 * self-intersecting path. The load-bearing test for a printed sign is *"this
 * image encodes this location's URL and not another's"*, and an image that
 * renders is not an image that is correct — so the artefact has to be decodable
 * by the suite. `tests/Support/QrDecoder.php` reads the output of this class
 * back to a module matrix and decodes it the way a phone does, and that is only
 * possible because the geometry here is one axis-aligned rectangle per run of
 * dark modules.
 *
 * ⚠️ **ONE `<path>` RATHER THAN MANY `<rect>`s, AND THAT IS ABOUT PRINTING.**
 * Two rectangles that share an edge are two shapes to a rasteriser, and a
 * printer driver antialiasing each separately leaves a white hairline along the
 * seam — visible at 60mm and enough to cost a scan on a marginal camera. Every
 * run is a subpath of a single filled path, so the symbol is one region and has
 * no internal seams. `shape-rendering="crispEdges"` is the second half of the
 * same argument.
 *
 * ⚠️ **ERROR CORRECTION IS `Q` (25%) RATHER THAN THE USUAL `M` (15%).** This
 * code is printed once and lives on a counter for a year: it will be scuffed,
 * splashed and part-covered by a card reader, and none of those is recoverable
 * by re-rendering the way a screen is. The cost is one QR version — a 41-char
 * URL is 29 modules at `M` and 33 at `Q` — which at 60mm is a 1.8mm module
 * either way, far above the ~0.4mm a phone camera needs.
 *
 * ⚠️ **`ISO-8859-1` IS THE DEFAULT BYTE ENCODING AND IS DELIBERATE.** Asking
 * for UTF-8 makes the encoder prepend an ECI header that some older scanners
 * mishandle, and buys nothing: a URL built from a scheme, our host and a slug
 * of lowercase base36 and hyphens is ASCII, which the two encodings agree on
 * byte for byte.
 */
final class QrCodeSvg
{
    /**
     * The mandatory light margin around the symbol, in modules.
     *
     * ISO/IEC 18004 §6.3.8 requires four, and a scanner that cannot find the
     * finder patterns without it simply fails — so this is not padding and must
     * not be tuned down to make a panel look tighter. The sign's own white card
     * supplies more of it in practice; this guarantees it wherever the SVG ends
     * up.
     */
    public const int QUIET_ZONE = 4;

    /**
     * The byte encoding handed to the encoder — see the class docblock.
     */
    private const string CHARSET = 'ISO-8859-1';

    /**
     * Draw `$text` as an SVG document, sized by its viewBox alone.
     *
     * No `width` or `height` attribute, on purpose: the caller sizes it in CSS,
     * which is what lets the same markup be a thumbnail on screen and 60mm on
     * paper. `preserveAspectRatio` defaults to `xMidYMid meet`, so it can never
     * be stretched into an unscannable rectangle.
     *
     * `$label` becomes the accessible name. A QR code is unreadable to a
     * screen-reader user, so the panel prints the address as text as well — the
     * same reasoning that puts the typed secret beside the 2FA QR — and this
     * name says what the picture is for rather than reciting the URL.
     */
    public function render(string $text, string $label): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::Q(), self::CHARSET)->getMatrix();

        $modules = $matrix->getWidth();
        $side = $modules + (self::QUIET_ZONE * 2);

        $path = '';

        for ($y = 0; $y < $modules; $y++) {
            $x = 0;

            while ($x < $modules) {
                if ($matrix->get($x, $y) !== 1) {
                    $x++;

                    continue;
                }

                $run = 0;

                while ($x + $run < $modules && $matrix->get($x + $run, $y) === 1) {
                    $run++;
                }

                // The one shape the decoder is written against: an origin, a
                // horizontal run, one module of height, closed. Changing this
                // spelling breaks tests/Support/QrDecoder.php, which is why it
                // is pinned here rather than composed loosely.
                $path .= sprintf(
                    'M%d %dh%dv1h-%dz',
                    $x + self::QUIET_ZONE,
                    $y + self::QUIET_ZONE,
                    $run,
                    $run,
                );

                $x += $run;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" '
            .'shape-rendering="crispEdges" role="img" aria-label="%s">'
            .'<path fill="#ffffff" d="M0 0h%dv%dh-%dz"/>'
            .'<path fill="#000000" d="%s"/>'
            .'</svg>',
            $side,
            $side,
            e($label),
            $side,
            $side,
            $side,
            $path,
        );
    }
}
