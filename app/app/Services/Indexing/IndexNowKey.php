<?php

declare(strict_types=1);

namespace App\Services\Indexing;

/**
 * One tenant host's IndexNow key, and where that host serves it from.
 *
 * ⛔ **THIS IS A SHARED SECRET AND THE PROTOCOL SAYS SO**: *"Only you and the
 * search engines should know the key and your file key location"*
 * (`indexnow.org/documentation`, fetched 2026-08-20). It never reaches a log, an
 * exception message or an `indexing_submissions.response` blob — the submitter's
 * recorded response carries the HTTP status and nothing that was sent.
 *
 * ⚠️ **NOTHING IN THIS BUILD CONSTRUCTS ONE OUTSIDE A TEST**, and no table
 * stores one. {@see UnhostedIndexNowKeys} is the only binding and it answers
 * with a refusal every time, because the thing that would serve the file is the
 * WordPress plugin (F2). ⛔ **Minting and storing a key now would be worse than
 * not having one**: it is a secret at rest, with a rotation story and an erasure
 * story, held for a feature that cannot use it — `CLAUDE.md`'s *less stored
 * data* tiebreaker pointing at a column with no reader (272).
 *
 * ## The format is the vendor's and is contradictory in the vendor's own text
 *
 * ⚠️ IndexNow says the key *"should have a minimum of 8 and a maximum of 128
 * hexadecimal characters"* and then, in the next clause, that it *"can contain
 * only … lowercase characters (a-z), uppercase characters (A-Z), numbers (0-9),
 * and dashes (-)"* — two different alphabets in one sentence.
 * {@see self::isWellFormed()} enforces the **narrower** reading plus the stated
 * length, so a key we ever mint satisfies both readings at once. That is the
 * only choice here that cannot be wrong, and it is why the rule is a method
 * rather than a comment.
 */
final readonly class IndexNowKey
{
    private function __construct(
        public string $host,
        public string $key,
        public ?string $keyLocation,
    ) {}

    /**
     * @param  ?string  $keyLocation  Option 2's absolute URL of the key file,
     *                                or null when it sits at the host root.
     *                                ⚠️ **Option 1 is the one the protocol
     *                                "strongly recommended"**, because a key
     *                                anywhere else can only submit URLs beneath
     *                                its own directory.
     */
    public static function of(string $host, string $key, ?string $keyLocation = null): self
    {
        return new self($host, $key, $keyLocation);
    }

    /**
     * Both halves of the vendor's stated format, taken at their narrowest.
     */
    public function isWellFormed(): bool
    {
        return preg_match('/^[a-f0-9]{8,128}$/', $this->key) === 1;
    }
}
