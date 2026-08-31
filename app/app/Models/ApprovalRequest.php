<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A CONFIRM prompt to the owner (DATA-MODEL §5.12), normally answered by
 * text. For gated actions, expiry never means "proceed".
 *
 * ⛔ **NOTHING IN `app/` HAS EVER WRITTEN OR READ THIS MODEL, AND THE SENTENCE
 * ABOVE IS A BEHAVIOURAL CLAIM ABOUT A MECHANISM THAT DOES NOT EXIST — 8550.**
 * There is no code that mints an approval, none that waits for one, none that
 * answers one, and **nothing anywhere reads `expires_at` or
 * `auto_action_on_expiry`**. *"Expiry never means proceed"* is therefore true
 * only in the way any statement about an empty set is true, and it is
 * 314-316's exact shape: the paragraph asserting a protection is what stops the
 * next reviewer looking for the mechanism.
 *
 * ⚠️ **THE SENTENCE IS KEPT AND DATED RATHER THAN DELETED** (4368's rule). It
 * is the *requirement* whoever builds the first writer inherits, and it is the
 * one design constraint `29` states about CONFIRM that is not merely
 * *"waits for a reply"*.
 *
 * ⛔ **THIS ABSENCE HAS BEEN RECORDED SIX TIMES IN `DECISIONS.md` — 1326, 1687,
 * 3485, 5673, 5926, 6029 — AND ONCE IN `ReviewReplies`, AND IT CHANGED
 * NOTHING**, because every one of those is prose and none of them can go false.
 * `tests/Feature/ConfirmIsUnbuiltTest.php` is the assertion that can.
 *
 * ⚠️ **AND THE RECORDING IS PART OF THE PROBLEM.** The only occurrence of
 * `approval_requests` outside `app/Models` is the `ReviewReplies` comment
 * explaining that the table is unused — so **every grep over this tree scores
 * this table as referenced**. Fourteen columns in this schema are in the same
 * state; `php artisan db:column-readers` separates them.
 *
 * ⛔ **DO NOT GIVE THIS A WRITER WITHOUT THE RULING AT 8570.** `29` names three
 * CONFIRM categories and **no document in this repository says what CONFIRM
 * does** — no SMS template, no recording shape, no behaviour on silence (6029).
 * Building the first writer answers that unasked question on the most sensitive
 * surface in the product: GBP identity fields, money, and the first send of a
 * campaign type.
 */
final class ApprovalRequest extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
