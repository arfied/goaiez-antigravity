<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\SiteSnapshotState;
use App\Services\Actuation\WordPress\WordPressAdapter;

/**
 * What a page looked like before we touched it — or the fact that there was no
 * page there at all.
 *
 * ⛔ **FOUR ANSWERS, NOT TWO, AND DECISION 5753 IS WHY** (5770). This carried an
 * array of fields and nothing else, so *"we looked and there is no page at this
 * URL"*, *"we could not read the site"* and *"nothing looked"* were one value:
 * `[]`. The first of those is what a **creation** starts from, and the other two
 * are what must never be built on. {@see SiteSnapshotState} is the distinction.
 *
 * ⚠️ **AN EMPTY SNAPSHOT IS STILL A LEGITIMATE ANSWER AND IS STILL REFUSED** —
 * {@see LogCmsAdapter} returns {@see SiteSnapshotState::Unread}, and
 * {@see SiteChanges::open()} refuses a change set whose `before` is empty
 * exactly as it did before. **Nothing about rule 32's guard is relaxed here**;
 * what changed is that a creation now arrives carrying a *recorded absence*
 * rather than nothing at all (see {@see ChangeSet::creating()}).
 *
 * ⛔ **THE CONSTRUCTOR IS PRIVATE ON PURPOSE.** The state and the fields have to
 * agree — a `Read` with no fields is a lie in the dangerous direction — and a
 * public constructor is where a future adapter would pair them wrongly. The four
 * named constructors are the only shapes that exist.
 */
final readonly class SiteSnapshot
{
    /**
     * @param  array<string, mixed>  $fields
     */
    private function __construct(
        public SiteSnapshotState $state,
        public array $fields = [],
    ) {}

    /**
     * The page is there, and this is what it says.
     *
     * ⚠️ **AN EMPTY FIELD MAP IS DOWNGRADED TO `Unreadable` RATHER THAN
     * ACCEPTED.** An adapter that reports a successful read of nothing has not
     * read the page, and `open()` would refuse it anyway — this makes the state
     * say so too, so that the two can never disagree.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function read(array $fields): self
    {
        return $fields === []
            ? self::unreadable()
            : new self(SiteSnapshotState::Read, $fields);
    }

    /**
     * ⚠️ **WE LOOKED AND THERE IS NO PAGE AT THIS URL.** Only an adapter that
     * genuinely asked the site may answer this — see
     * {@see WordPressAdapter::snapshot()},
     * where a `404` from the API is deliberately **not** this answer.
     */
    public static function absent(): self
    {
        return new self(SiteSnapshotState::Absent);
    }

    /**
     * We could not see the site.
     */
    public static function unreadable(): self
    {
        return new self(SiteSnapshotState::Unreadable);
    }

    /**
     * Nothing looked — decision 5528's honest answer from a driver that
     * transmits nothing.
     */
    public static function unread(): self
    {
        return new self(SiteSnapshotState::Unread);
    }

    public function isAbsent(): bool
    {
        return $this->state === SiteSnapshotState::Absent;
    }

    /**
     * Does this snapshot hold exactly these values?
     *
     * ⛔ **THE QUESTION A REVERT HAS TO ASK BEFORE IT WRITES** (6042, closed at
     * 6142). A rollback puts a page's `before` values back fourteen to thirty
     * days after we wrote its `after`, and the owner who disliked our edit is the
     * **most** likely person to have rewritten that page in the meantime. What
     * this answers is *"is the page still as we left it"*; when it is not, the
     * honest thing is to leave their words alone and say so.
     *
     * ⛔ **A SNAPSHOT THAT IS NOT `Read` NEVER MATCHES.** Absent, unreadable and
     * unread all mean *we are not looking at the page*, and treating any of them
     * as agreement would put the guard's answer the dangerous way round.
     * ⚠️ **AND THAT CLAUSE CANNOT BE DRIVEN RED TODAY, WHICH IS WRITTEN DOWN
     * RATHER THAN LEFT TO BE DISCOVERED** (352/397). The constructor is private
     * and `read()` is the only named constructor that carries fields, so the
     * other three always hold an empty map and are refused one line below by the
     * key comparison. It is a defence against a fifth constructor pairing a
     * state with fields that disagree — the thing the private constructor exists
     * to prevent — and `RollbackFidelityTest` says so where it would otherwise
     * read as proven.
     *
     * ⚠️ **EXACT KEYS BOTH WAYS.** A page holding two of the three fields we
     * wrote is not the page we left, and comparing only the keys we happen to
     * have asked about is how a partial read reads as a match.
     *
     * @param  array<string, mixed>  $expected
     */
    public function matches(array $expected): bool
    {
        if ($this->state !== SiteSnapshotState::Read || $expected === []) {
            return false;
        }

        $found = array_keys($this->fields);
        $wanted = array_keys($expected);

        sort($found);
        sort($wanted);

        // ⚠️ **SORTED, BECAUSE KEY ORDER IS AN ACCIDENT OF WHOEVER BUILT THE
        // ARRAY** and a comparison that turned on it would refuse a revert for a
        // reason no owner could ever be told.
        if ($found !== $wanted) {
            return false;
        }

        foreach ($expected as $name => $value) {
            if (! is_string($value) || ! is_string($this->fields[$name] ?? null)) {
                return false;
            }

            if (! self::sameValue((string) $this->fields[$name], $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether two field values are the same value.
     *
     * ⛔ **ONE SPELLING OF THE RULE, AND IT LIVES HERE BECAUSE TWO CLASSES ASK
     * IT** (6142). `WordPressRestClient::matches()` asks it of a write it has
     * just made — *did what we sent land* — and `WordPressAdapter` asks it of a
     * page it is about to put back — *is this still what we left*. **Two
     * spellings would be two ideas of "the same", and the second one would be
     * the one nobody drives.**
     *
     * ⛔ **LINE ENDINGS AND OUTER WHITESPACE ARE NORMALISED, AND NOTHING ELSE
     * IS** (5984). WordPress and its editors move a trailing newline around
     * freely, and a comparison that failed on one would be 5590's
     * always-failing automation for a difference no visitor can see. A stripped
     * tag, a rewritten attribute or an injected block is **not** normalised —
     * every one of them is the silent filtering this comparison exists to catch,
     * and softening it further turns the check back into a shape check, which is
     * 5528's whole argument one layer up.
     */
    public static function sameValue(string $a, string $b): bool
    {
        $flatten = static fn (string $value): string => trim(str_replace("\r\n", "\n", $value));

        return $flatten($a) === $flatten($b);
    }
}
