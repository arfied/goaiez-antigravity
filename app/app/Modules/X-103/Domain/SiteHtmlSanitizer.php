<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use Dom\Comment;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

/**
 * Makes AI-written page HTML safe to show and publish. An allowlist, never a blocklist: an element or attribute that
 * is not named here is removed. No script runs (no <script>, no on* attribute, no javascript: link), nothing loads from
 * outside the page (no external image, font, stylesheet, frame or CSS url()), and a picture can only be one of the
 * owner's own, addressed as [[image:N]] and swapped for the real file by whoever renders it.
 */
final class SiteHtmlSanitizer
{
    /** Elements kept (with their allowed attributes). Anything else is removed together with everything inside it. */
    public const TAGS = [
        'main', 'section', 'header', 'footer', 'nav', 'article', 'aside', 'div', 'span',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'br', 'hr',
        'img', 'figure', 'figcaption', 'blockquote', 'cite', 'small', 'address', 'time', 'details', 'summary',
        'svg', 'g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse',
    ];

    public const ATTRIBUTES = [
        'class', 'id', 'href', 'src', 'alt', 'title', 'width', 'height', 'loading', 'role', 'aria-label', 'aria-hidden',
        'style', 'datetime', 'open',
        'viewBox', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'd', 'cx', 'cy', 'r', 'rx', 'ry',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'points', 'transform', 'opacity', 'fill-rule', 'clip-rule',
    ];

    public static function isSafeHref(string $value): bool
    {
        return preg_match('#^(https?://|tel:|mailto:|\#)#i', trim($value)) === 1;
    }

    public static function isImageToken(string $value): bool
    {
        return preg_match('#^\[\[image:\d{1,2}\]\]$#', trim($value)) === 1;
    }

    /** CSS with every way to reach outside the page or run code turned off. */
    public static function css(string $css): string
    {
        // CSS escapes can spell url( without the letters (u\72 l), so no backslash survives.
        $css = str_replace('\\', '', $css);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $css = (string) preg_replace('#@import[^;]*;?#i', '', $css);
        $css = (string) preg_replace('#@font-face\s*\{[^}]*\}#i', '', $css);
        $css = (string) preg_replace('#url\s*\(#i', 'blocked(', $css);
        $css = (string) preg_replace('#(-webkit-)?image-set\s*\(|\bimage\s*\(#i', 'blocked(', $css);
        $css = (string) preg_replace('#expression\s*\(#i', 'blocked(', $css);
        $css = (string) preg_replace('#javascript\s*:#i', 'blocked:', $css);
        $css = (string) preg_replace('#-moz-binding|behavior\s*:#i', 'blocked', $css);

        return str_ireplace('</style', '', $css);
    }

    /**
     * @return array{style: string, html: string}
     */
    public function clean(string $raw): array
    {
        $doc = HTMLDocument::createFromString('<!doctype html><html><head></head><body>'.$raw.'</body></html>', LIBXML_NOERROR);
        $body = $doc->body;
        if ($body === null) {
            return ['style' => '', 'html' => ''];
        }

        $style = '';
        foreach (iterator_to_array($doc->getElementsByTagName('style')) as $node) {
            $style .= self::css((string) $node->textContent)."\n";
            $node->remove();
        }

        $this->walk($body);

        return ['style' => trim($style), 'html' => trim($body->innerHTML)];
    }

    private function walk(Node $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof Comment) {
                $node->remove();

                continue;
            }
            if (! $node instanceof Element) {
                continue;
            }

            $tag = strtolower($node->localName);
            if (! in_array($tag, self::TAGS, true)) {
                $node->remove();

                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = $attr->localName;
                $value = (string) $attr->value;
                $keep = in_array($name, self::ATTRIBUTES, true) && $attr->namespaceURI === null;
                if ($keep && $name === 'href') {
                    $keep = $tag === 'a' && self::isSafeHref($value);
                }
                if ($keep && $name === 'src') {
                    $keep = $tag === 'img' && self::isImageToken($value);
                }
                if ($keep && $name === 'style') {
                    $value = self::css($value);
                }
                if ($keep) {
                    $node->setAttribute($attr->name, $value);
                } else {
                    $node->removeAttribute($attr->name);
                }
            }

            if ($tag === 'img' && ! $node->hasAttribute('src')) {
                $node->remove();

                continue;
            }

            $this->walk($node);
        }
    }
}
