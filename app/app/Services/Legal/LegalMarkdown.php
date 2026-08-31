<?php

declare(strict_types=1);

namespace App\Services\Legal;

use Illuminate\Support\Str;

/**
 * Counsel writes Markdown; the page rendered the characters.
 *
 * ⛔ **`legal/document.blade.php` PRINTED THE STORED BODY THROUGH `{{ }}` WITH
 * `whitespace-pre-line`, SO `## Heading` AND `**bold**` REACHED THE READER AS
 * THOSE LITERAL CHARACTERS** (5710). `legal_documents.body` is a plain text
 * column and the admin screen stores whatever is pasted into it; the drafts in
 * `LEGAL-DRAFTS-V1.md` are Markdown. Nothing in the path ever converted one to
 * the other — the view was built for plain text and the content was never plain
 * text. It is not a regression; it never worked.
 *
 * ⚠️ **THIS CLASS EXISTS BECAUSE THE FIX IS `{!! !!}`, WHICH IS A DECISION AND
 * NOT A ONE-LINE EDIT.** Unescaped output on an unauthenticated page that every
 * consent disclosure links to is an injection surface, so the two options that
 * make it safe live in one place with the argument attached rather than being
 * re-derived at each call site:
 *
 * - **`html_input: 'strip'`** — raw HTML in the stored body is discarded rather
 *   than passed through. CommonMark's default is `allow`, which would forward a
 *   `<script>` tag from the document body straight into the page.
 * - **`allow_unsafe_links: false`** — `javascript:`, `data:` and `vbscript:`
 *   hrefs are dropped from links and images.
 *
 * ⛔ **NEITHER IS OPTIONAL AND BOTH ARE DRIVEN BY MUTATION** in
 * `LegalMarkdownTest`: removing either one turns a test red with a planted
 * payload, because a safety option nobody can see fail is decoration (256).
 *
 * ⚠️ **WHO CAN WRITE A BODY IS NOT THE ARGUMENT.** Only staff reach
 * `Admin\LegalDocuments`, and a published body is frozen by the schema's own
 * trigger — so the realistic threat is a paste from counsel's own document
 * carrying markup nobody inspected, not an attacker. The controls are the same
 * either way, and the cheap ones are worth having before the expensive question
 * is asked.
 *
 * ✅ **RENDERING CHANGES NO RECORD.** Consent rows reference the stored wording
 * and its version; this converts that text for display and never writes. The
 * immutability trigger and every consent record are untouched by it.
 */
final class LegalMarkdown
{
    /**
     * The safety options, in one place because both are load-bearing.
     *
     * @var array<string, mixed>
     */
    private const OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ];

    /**
     * Render a stored legal document body as HTML that is safe to print
     * unescaped.
     *
     * ⚠️ **AN EMPTY OR WHITESPACE-ONLY BODY RETURNS AN EMPTY STRING** rather
     * than an empty `<p></p>`, so the view can decide whether there is anything
     * to show. A published document with no body is not a state this schema
     * permits, but a converter that answers "some markup" for "nothing" is how
     * an empty section renders as a blank box that looks broken.
     */
    public static function toHtml(string $body): string
    {
        if (trim($body) === '') {
            return '';
        }

        return Str::markdown($body, self::OPTIONS);
    }
}
