<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * The one place a legal document is written or resolved.
 *
 * Same shape as `DestinationSettings` and `FeedbackPages`, and enforced the same
 * way: an `ArchitectureTest` lint names this as the only file allowed to touch
 * `LegalDocument::query()`. A screen that writes its own row bypasses the rules
 * below, and every one of them protects something that cannot be repaired
 * afterwards.
 *
 * ⚠️ WHAT "EDITABLE ANYTIME" HAD TO MEAN. The instruction that produced this
 * slice was that the documents be stored in admin and editable at any time. That
 * is honoured — for drafts. It could not be honoured for published text, because
 * `consent_records.disclosure_version` is the only pointer from a stored consent
 * back to the words a customer read (decision 330), and `29` §2 rule 7 requires
 * producing those words on demand. Editing published text does not change a
 * document; it silently changes what thousands of stored records claim somebody
 * agreed to, and nothing anywhere remembers the old wording. So "edit" mints a
 * new version and the old one stays addressable forever.
 *
 * THE VERSION IS THE CALLER'S, NOT GENERATED. `39` seeds its drafts as `0.9` and
 * counsel's convention for a revision is counsel's to set. What this class
 * enforces is that a version, once published, names exactly one text.
 */
final class LegalDocuments
{
    /**
     * The version served at `/legal/{doc}` — the newest published one.
     *
     * ⚠️ `orderByDesc('published_at')` would put a NULL first on Postgres, which
     * is the hazard an `ArchitectureTest` lint exists for: 40 of 43 tables carry
     * a nullable timestamp and `latest()` on one sorts undated rows ahead of
     * every dated one. Here the `whereNotNull` makes that unreachable — and the
     * ordering is on `id` anyway, which is the lint's one permitted column and
     * the only monotonic thing on the row.
     */
    public function current(LegalDocumentType $type): ?LegalDocument
    {
        return LegalDocument::query()
            ->where('doc_type', $type)
            ->whereNotNull('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The version that was current on a given day — the newest one published on
     * or before it.
     *
     * ⚠️ WHY THIS IS NOT `current()`. An execution names the words somebody
     * actually signed, and a signature is a fact about a piece of paper dated
     * before it reached us (`BaaRecords::recordExecution()` takes that date, and
     * the screen validates it as `before_or_equal:today`). If counsel publishes
     * v1.1 on Monday and staff record a paper signature dated the week before,
     * `current()` would name v1.1 — a version that did not exist when the tenant
     * signed. The record would then assert a covered entity agreed to words they
     * were never shown, which is the one thing this whole surface exists to make
     * true.
     *
     * Harmless while exactly one non-placeholder version has ever been published,
     * which is why nothing catches it until version history exists — and version
     * history is the reason `legal_documents` exists at all (decisions 417–420).
     *
     * ⚠️ COMPARED BY DAY, NOT BY INSTANT. `published_at` is a timestamp and the
     * signing date is a calendar date with no time on it, so a version published
     * at 10:00 on the day somebody signed is a version they could have signed —
     * a strict `<=` against midnight would refuse same-day execution, which is
     * the ordinary case on the day a version is published.
     *
     * ⚠️ AND "DAY" MEANS UTC, BECAUSE `config('app.timezone')` IS UTC AND THE
     * COLUMN IS `timestamp without time zone`. `whereDate` casts the stored
     * value to a date in the database, so both sides are UTC and agree with
     * `BaaRecords::refuseUnusableVersion()`, which compares the same two values
     * in PHP. The day the two would disagree is the day somebody sets a local
     * application timezone: the PHP side would shift and the SQL side would not,
     * and the symptom would be a version accepted by the resolver and refused by
     * the service for signatures dated within a few hours of midnight.
     *
     * `orderByDesc('id')` for the reason `current()` gives: `published_at` is
     * nullable, Postgres sorts NULL first on a DESC ordering, and an
     * `ArchitectureTest` lint permits no other column.
     *
     * ⚠️ AND `id` IS A FAITHFUL PROXY FOR PUBLICATION ORDER ONLY BECAUSE
     * `startDraft()` PERMITS ONE OPEN DRAFT PER TYPE. That serialises the two:
     * a version cannot be created until the previous one is published, so a
     * higher id was published later and "newest published on or before this
     * date" and "highest id published on or before this date" name the same
     * row. **It is a rule of this service, not of the schema** — nothing in the
     * database stops a second draft, and a factory or a repair script inserting
     * rows with back-dated `published_at` breaks it immediately (this file's own
     * tests do exactly that on purpose). What keeps it true is
     * `LegalDocumentVersioningTest`'s *"returns the open draft rather than
     * starting a second one"* — so whoever relaxes that rule makes this ordering
     * wrong at the same moment, in a method whose failure mode is naming the
     * wrong words on a signed agreement.
     */
    public function currentAsOf(LegalDocumentType $type, CarbonInterface $on): ?LegalDocument
    {
        return LegalDocument::query()
            ->where('doc_type', $type)
            ->whereNotNull('published_at')
            ->whereDate('published_at', '<=', $on->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * One exact version, whatever its state.
     *
     * This is the method a consent-proof lookup uses, so it deliberately does
     * **not** filter on published: a version that was published and later
     * superseded must still resolve, forever. Nothing supersedes by deletion —
     * the trigger makes sure of it.
     */
    public function version(LegalDocumentType $type, string $version): ?LegalDocument
    {
        return LegalDocument::query()
            ->where('doc_type', $type)
            ->where('version', $version)
            ->first();
    }

    /**
     * Every version of one document, newest first, for the admin screen.
     *
     * @return Collection<int, LegalDocument>
     */
    public function history(LegalDocumentType $type): Collection
    {
        return LegalDocument::query()
            ->where('doc_type', $type)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Where every document stands, in one query.
     *
     * ⚠️ THE READER `is_placeholder` NEVER HAD. `39`'s checklist step 5 makes
     * that column the input to a launch gate — *"wide launch stays locked while
     * any served doc is a placeholder"* — and until this method nothing in this
     * application could see more than one document at a time, so the gate had no
     * possible answer. `BaaRecords` consults the flag for the BAA alone.
     *
     * ONE QUERY RATHER THAN TWENTY-SIX. `current()` and `draft()` per type would
     * be two round trips each for a screen whose whole job is the overview, and
     * decision 519's home page went from 5 queries to 15 exactly this way. The
     * table is small by construction — thirteen documents and a handful of
     * versions apiece — so the whole of it is cheaper than the loop.
     *
     * Full rows rather than a narrowed select: a `LegalDocumentState` holding a
     * model with no `body` is a trap for whatever renders one next, and the size
     * saved is a few hundred kilobytes on an admin screen.
     *
     * `orderByDesc('id')` for the reason `current()` gives, and it is what makes
     * "the first published row" and "the newest published row" the same row.
     *
     * @return list<LegalDocumentState>
     */
    public function library(): array
    {
        $rows = LegalDocument::query()->orderByDesc('id')->get();

        return array_map(
            function (LegalDocumentType $type) use ($rows): LegalDocumentState {
                $versions = $rows->where('doc_type', $type);

                return new LegalDocumentState(
                    type: $type,
                    published: $versions->first(fn (LegalDocument $d): bool => $d->isPublished()),
                    draft: $versions->first(fn (LegalDocument $d): bool => ! $d->isPublished()),
                    versions: $versions->count(),
                );
            },
            LegalDocumentType::cases(),
        );
    }

    /**
     * The newest draft of one document, if one is open.
     *
     * At most one draft per type is a rule of this service rather than of the
     * schema: two open drafts of the Privacy Policy is a state where "publish"
     * is ambiguous, and the ambiguity would be resolved by whichever admin
     * clicked first.
     */
    public function draft(LegalDocumentType $type): ?LegalDocument
    {
        return LegalDocument::query()
            ->where('doc_type', $type)
            ->whereNull('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Start a new draft, or return the one already open.
     *
     * The body is seeded from the current published version so that "edit" reads
     * as editing rather than as starting from nothing — which is what an admin
     * expects, and what stops a new version quietly losing clauses.
     */
    public function startDraft(LegalDocumentType $type, string $version): LegalDocument
    {
        $existing = $this->draft($type);

        if ($existing !== null) {
            return $existing;
        }

        $this->refuseTakenVersion($type, $version);

        $current = $this->current($type);

        return LegalDocument::query()->create([
            'doc_type' => $type,
            'version' => $version,
            'title' => $type->title(),
            // `->` rather than `?->`: `??` already handles the null document,
            // and PHPStan rejects the redundant nullsafe.
            'body' => $current->body ?? '',
            'is_placeholder' => $current->is_placeholder ?? true,
        ]);
    }

    /**
     * Edit an open draft.
     *
     * @throws InvalidArgumentException when the document is published
     */
    public function saveDraft(LegalDocument $document, string $body, bool $isPlaceholder): LegalDocument
    {
        $this->refusePublished($document, 'edited');

        $document->update([
            'body' => $body,
            'is_placeholder' => $isPlaceholder,
        ]);

        return $document;
    }

    /**
     * Record that a named reviewer has read this draft.
     *
     * Separate from publishing on purpose. `39`'s checklist step 3 makes review
     * and publication two acts by two people — "counsel checkbox (named
     * reviewer) → admin publish" — and collapsing them into one button would
     * make the reviewer's name a field the publisher types about themselves.
     *
     * @throws InvalidArgumentException when the document is published
     */
    public function recordReview(LegalDocument $document, string $reviewer): LegalDocument
    {
        $this->refusePublished($document, 'reviewed');

        if (trim($reviewer) === '') {
            throw new InvalidArgumentException(
                'A review needs a named reviewer. The name is the record that the review happened.'
            );
        }

        $document->update([
            'reviewed_by' => trim($reviewer),
            'reviewed_at' => now(),
        ]);

        return $document;
    }

    /**
     * Publish a draft, freezing it forever.
     *
     * ⚠️ REVIEW IS REQUIRED FIRST, AND THIS IS THE ONLY GATE ON IT. `39`'s
     * checklist has counsel review preceding publication for every document, and
     * an unreviewed published document is worse than an unpublished one: it is
     * served to the public and cited in a disclosure while nobody has read it.
     *
     * ⚠️ AND THE SMS TERMS CARRY ONE MORE CONDITION, CHECKED LAST. A version of
     * that one document has to quote the opt-in notice this application actually
     * shows, because `database/seeders/legal/sms-terms.md` §10 says the two must
     * match verbatim and, until `SmsTermsAlignment` existed, said it only to a
     * reader. It is checked **after** the three refusals above rather than
     * before them, so those keep their precedence and their messages: an
     * unreviewed draft is told it is unreviewed, not that its wording disagrees
     * with a page. It throws `SmsTermsMisaligned`, a subclass, so a test can
     * prove this check is what refused rather than one of the guards upstream
     * (decision 398).
     *
     * @throws InvalidArgumentException when already published or unreviewed
     * @throws SmsTermsMisaligned when the SMS terms do not quote the live notice
     */
    public function publish(LegalDocument $document, string $publisher): LegalDocument
    {
        $this->refusePublished($document, 'published again');

        if ($document->reviewed_at === null) {
            throw new InvalidArgumentException(
                'This version has not been reviewed. Record the reviewer before publishing.'
            );
        }

        if (trim($publisher) === '') {
            throw new InvalidArgumentException(
                'Publishing needs an actor. This row is the audit record of the act.'
            );
        }

        if ($document->doc_type === LegalDocumentType::SmsTerms) {
            SmsTermsAlignment::refuseUnquotedNotice($document->version, $document->body);
        }

        $document->update([
            'published_at' => now(),
            'published_by' => trim($publisher),
        ]);

        return $document;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function refusePublished(LegalDocument $document, string $verb): void
    {
        if ($document->isPublished()) {
            throw new InvalidArgumentException(
                "Version {$document->version} is published and cannot be {$verb}. "
                .'Start a new version instead — the published text stays exactly as it was.'
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function refuseTakenVersion(LegalDocumentType $type, string $version): void
    {
        if ($this->version($type, $version) !== null) {
            throw new InvalidArgumentException(
                "Version {$version} of {$type->title()} already exists. A version names one exact text."
            );
        }
    }
}
