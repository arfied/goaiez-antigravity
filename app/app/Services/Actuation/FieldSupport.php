<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Contracts\CmsAdapter;
use App\Services\Actuation\WordPress\WordPressAdapter;

/**
 * Which of the fields a change set wants to write this adapter can actually
 * write, and why not the others.
 *
 * ⛔ **IT EXISTS SO THE PIPELINE ASKS INSTEAD OF ASSUMING** (5753(a), ruled at
 * 5772). `Publishing` named `meta_description` in every change set it built and
 * {@see WordPressAdapter} refuses a set
 * containing any unwritable field **whole and by name** (5591, 5532's rule) — so
 * every live publish would have opened a `site_changes` row and failed. The two
 * halves were each correct: core REST genuinely cannot write a meta description,
 * and a partly-applied change set genuinely must be refused. What was missing
 * was the question.
 *
 * ⚠️ **A REFUSAL IS RECORDED, NEVER DROPPED.** {@see self::$refused} is carried
 * into {@see ChangeSet::$withheld}, persisted on `site_changes.withheld_fields`
 * and written into the audit entry — because a field that silently did not get
 * set is indistinguishable from one nobody asked for, and 1222's rule is that a
 * refusal nothing surfaces is not a refusal.
 *
 * ⚠️ **ANSWERED WITHOUT TOUCHING THE NETWORK** — 5599's reasoning, one verb
 * along. {@see CmsAdapter::health()} is the live question and costs two requests
 * to a customer's website; this is a property of the *adapter*, asked on every
 * publish, and a round trip inside it would put a stranger's server on the
 * critical path of deciding what to write.
 */
final readonly class FieldSupport
{
    /**
     * @param  list<string>  $writable  In the order they were asked for.
     * @param  array<string, string>  $refused  Field name to a fixed reason.
     */
    public function __construct(
        public array $writable,
        public array $refused = [],
    ) {}

    /**
     * The answer of an adapter that can write none of them, for one reason.
     *
     * @param  list<string>  $fields
     */
    public static function none(array $fields, string $reason): self
    {
        $refused = [];

        foreach ($fields as $field) {
            $refused[$field] = $reason;
        }

        return new self([], $refused);
    }

    public function permits(string $field): bool
    {
        return in_array($field, $this->writable, true);
    }
}
