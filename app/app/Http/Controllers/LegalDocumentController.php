<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LegalDocumentType;
use App\Services\Legal\LegalDocuments;
use App\Services\Legal\LegalMarkdown;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The legal documents the consent disclosure links to.
 *
 * WHY THIS EXISTS IN A REVIEW-ENGINE SLICE. `24` 3.2 lists live links to Terms
 * and Privacy among the non-negotiable details of a TCPA disclosure, and
 * /legal/{doc} did not exist — the same class of gap as row 2 slice F's missing
 * resolver path and slice G2's missing /start. A dead link inside a disclosure
 * is worse than a placeholder page.
 *
 * NOW READS FROM `legal_documents`, which is what this docblock previously said
 * would happen "when it lands". The private allowlist it described has become
 * `LegalDocumentType`, and the placeholder text it rendered unconditionally is
 * now the fallback for a document with no published version.
 *
 * ⚠️ THE PLACEHOLDER PATH IS NOT DEAD CODE AND MUST NOT BE DELETED. `29` 6.1
 * specifies these as "placeholders until counsel per prelaunch gate 1", and on
 * the day this ships **every one of them is unpublished** — counsel has
 * four drafts and has published none. A controller that 404'd instead would take
 * the Terms and Privacy links inside a live consent disclosure with it, which is
 * the exact failure this route was created to prevent. It also stays correct
 * afterwards: a document added to the enum before its text is written
 * lands here rather than breaking a link.
 *
 * ⚠️ TWO DOCUMENTS ARE DELIBERATELY NOT SERVED HERE, FOR UNRELATED REASONS.
 * `LegalDocumentType::isPublic()` excludes both, so `/legal/baa` and
 * `/legal/internal-refunds` are 404s like any unknown slug. A BAA is an
 * agreement executed with one named Covered Entity; publishing the template at
 * an unauthenticated URL invites it being cited as if in force for somebody who
 * never signed it. R53's internal refund policy carries a standing law on its
 * own face — never marketed, never printed on a customer surface — so it is a
 * staff document that happens to be versioned here.
 *
 * ⚠️ **THE REFUSAL IS DRIVEN AGAINST A PUBLISHED, NON-PLACEHOLDER ROW**, in
 * `InternalLegalDocumentTest`. Asking for one of these against an empty table
 * 404s whether or not this method checks anything, which is 398's shape at the
 * one gate that stops an internal policy reaching a customer.
 */
final class LegalDocumentController extends Controller
{
    public function __invoke(string $doc, LegalDocuments $documents): View
    {
        $type = LegalDocumentType::tryFrom($doc);

        // A route parameter reaching storage is a surface, so it resolves
        // through the enum first and an unknown value never becomes a query.
        if ($type === null || ! $type->isPublic()) {
            throw new NotFoundHttpException;
        }

        $document = $documents->current($type);

        return view('legal.document', [
            // `->` rather than `?->`: null coalescing already suppresses the
            // property read on a null document, and PHPStan rejects the
            // redundant nullsafe.
            'title' => $document->title ?? $type->title(),
            'document' => $document,

            // ⚠️ **THE BODY IS CONVERTED HERE RATHER THAN IN THE TEMPLATE**
            // (5710). The view prints it unescaped, and a `{!! Str::markdown(…) !!}`
            // in a Blade file puts the two options that make that safe where the
            // next person editing the layout can drop one without noticing.
            // `LegalMarkdown` holds them with the argument attached.
            'bodyHtml' => $document === null ? '' : LegalMarkdown::toHtml($document->body),

            // ⚠️ ALWAYS `/legal/{doc}`, NEVER `url()->current()`. Two of these
            // documents are served at a second, shorter address as well — the
            // ones a 10DLC campaign registration files — and a canonical that
            // echoed the request would name whichever address the crawler
            // happened to reach, which is not a canonical at all.
            'canonicalUrl' => route('legal.document', ['doc' => $type->value]),
        ]);
    }
}
