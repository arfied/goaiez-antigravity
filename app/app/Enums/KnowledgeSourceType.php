<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a piece of the Business Brain came from (DATA-MODEL §5.9).
 *
 * A STRING COLUMN CAST HERE, NEVER A DATABASE ENUM (`CLAUDE.md` §Critical
 * rules). `knowledge_sources.type` shipped as a `string` in its own migration
 * and had **no reader at all** until this slice — the column, the table and its
 * sibling `knowledge_chunks` were decision 272's shape, an isolation test
 * passing perfectly against tables nothing wrote.
 *
 * ⚠️ **`Url` HAS NO WRITER YET AND IS KEPT ANYWAY.** It is the crawler's value,
 * the factory already generates it, and removing it would silently narrow what
 * a re-crawl can record. Naming it here as unwritten is the honest form of the
 * thing this slice exists to stop happening quietly.
 */
enum KnowledgeSourceType: string
{
    /** A file the owner uploaded on the Brain screen. The only writer today. */
    case Upload = 'upload';

    /** A page the crawler fetched. ⚠️ No writer yet — see the class docblock. */
    case Url = 'url';

    /** A PDF. ⚠️ Accepted by the schema, refused at ingest — see KnowledgeIngestor. */
    case Pdf = 'pdf';

    /** Text somebody typed rather than uploaded. ⚠️ No writer yet. */
    case Manual = 'manual';

    /**
     * Outcome language (`22`): what the owner sees, never how we store it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Uploaded file',
            self::Url => 'Page on your website',
            self::Pdf => 'PDF',
            self::Manual => 'Typed in',
        };
    }
}
