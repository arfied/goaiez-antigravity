<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * What we can and cannot get words out of.
 *
 * ⛔ **PDF IS REFUSED, DELIBERATELY, AND THIS IS THE PLACE THAT SAYS SO.**
 * Extracting text from a PDF needs a parser — `smalot/pdfparser` is the usual
 * choice — and `CLAUDE.md` forbids adding a dependency without approval. The
 * refusal is *named* rather than silent for the reason
 * `KnowledgeSourceStatus::Unreadable` exists: an owner whose PDF sits at
 * "pending" forever files a ticket, and an owner told "we cannot read PDFs yet,
 * paste the text instead" gets on with their day.
 *
 * ⚠️ **A SILENT ACCEPTANCE WOULD HAVE BEEN WORSE THAN A REFUSAL, NOT MERELY
 * EQUAL TO IT.** A PDF read as raw bytes is not empty — it is tens of kilobytes
 * of `%PDF-1.7`, stream markers and compressed binary, which chunks happily,
 * embeds happily, stores happily and then surfaces as retrieved "knowledge"
 * against a customer's question. Every layer would report success. That is why
 * the check is a signature test on the bytes rather than trust in the file
 * extension: a `.txt` containing a PDF is exactly the upload that would sail
 * through an extension check.
 */
final class DocumentText
{
    /**
     * The extensions the upload screen offers, and the only ones ingest reads.
     *
     * @var list<string>
     */
    public const array ACCEPTED_EXTENSIONS = ['txt', 'md', 'markdown'];

    /**
     * Leading bytes that mean "this is not text, whatever it is called".
     *
     * Not an exhaustive binary sniff and not meant to be — these are the
     * formats somebody actually tries to upload as a business document, plus
     * the one this class exists to refuse by name.
     *
     * @var array<string, string>
     */
    private const array SIGNATURES = [
        '%PDF-' => 'pdf',
        // ZIP container: .docx, .xlsx, .pptx and an ordinary .zip all start here.
        "PK\x03\x04" => 'office_or_zip',
        // Legacy OLE2 compound file: .doc, .xls, .ppt.
        "\xD0\xCF\x11\xE0" => 'legacy_office',
        "\x89PNG" => 'image',
        "\xFF\xD8\xFF" => 'image',
    ];

    /**
     * The document's text, or null if this is not something we can read.
     *
     * Null is the `Unreadable` answer — see the class docblock. An empty string
     * is a *different* answer (a genuinely empty text file) and is returned as
     * an empty string so the caller can say so precisely.
     */
    public function extract(string $bytes): ?string
    {
        if ($this->looksBinary($bytes)) {
            return null;
        }

        // ⚠️ INVALID UTF-8 IS REFUSED RATHER THAN REPAIRED. A Latin-1 export
        // "repaired" by dropping bytes silently mangles every accented name in
        // a tenant's own document, and the result embeds and retrieves as if it
        // were correct. Telling them the file is not readable is honest;
        // guessing an encoding is not.
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            return null;
        }

        return $bytes;
    }

    /**
     * The reason this file was refused, for the record and for the owner.
     */
    public function refusalFor(string $bytes): string
    {
        foreach (self::SIGNATURES as $signature => $kind) {
            if (str_starts_with($bytes, $signature)) {
                return $kind;
            }
        }

        return 'not_utf8_text';
    }

    private function looksBinary(string $bytes): bool
    {
        foreach (array_keys(self::SIGNATURES) as $signature) {
            if (str_starts_with($bytes, $signature)) {
                return true;
            }
        }

        // A NUL byte does not occur in UTF-8 text. Catches the binary formats
        // not listed above without pretending to be a full content sniffer.
        return str_contains($bytes, "\0");
    }
}
