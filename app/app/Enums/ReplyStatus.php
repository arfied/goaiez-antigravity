<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a reply to a review (DATA-MODEL §5.1 `reply_status`).
 *
 * AI writes replies — never reviews. Draft is machine-written and unsent;
 * Suggested is waiting on a person; **Approved carries a recorded decision to
 * publish**; Posted has reached the platform; Failed keeps its error for retry.
 *
 * ⚠️ `Approved` IS NOT COSMETIC AND IT IS THE STATE THIS ENUM SHIPPED WITHOUT.
 * `ReviewReplies::approve()` used to write `Suggested` — the same value an
 * untouched AI draft carries — so nothing downstream could tell an owner's
 * decision from a machine's guess. Three things rode on that one value:
 * `PostReplyJob` had no way to refuse a draft nobody had read, the approval
 * queue kept re-listing the card the owner had just cleared, and the nav badge
 * never decremented. A publish gate that cannot represent "approved" is not a
 * gate — it is a status field with an optimistic name.
 *
 * ⚠️ AND AN AUTO-POST DECISION IS ALSO `Approved`, WITH A SYSTEM ACTOR. Rule 37
 * lets a location auto-publish above its rating threshold, and that is still a
 * decision — taken by configuration rather than by a person. Recording it as
 * `Approved` + `approved_by = 'system:auto_post'` keeps one predicate for
 * "may this be published" instead of two, and keeps the answer to "who decided"
 * on the row rather than inferred from settings that may since have changed.
 */
enum ReplyStatus: string
{
    case Draft = 'draft';
    case Suggested = 'suggested';
    case Approved = 'approved';
    case Posted = 'posted';
    case Failed = 'failed';
}
