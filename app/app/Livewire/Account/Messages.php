<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\PlatformMailer;
use App\Services\Messaging\MessageLog;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything we have sent on the owner's behalf (`28` §3.4).
 *
 * ⚠️ **THE DOOR ON A STORE THAT HAD NONE.** `outreach_messages` has been
 * collecting rows since row 4's email half landed and nothing could open it
 * (940). A reader without a screen would be the same defect one layer up, which
 * is why this ships in the same slice — 848's rule, and the fourth owner surface
 * this worktree has had to build for that reason after `/account`, the import
 * screen and Home.
 *
 * ## Normal only
 *
 * §3.4 splits the screen: *"Normal = one chronological list (contact, one-line
 * preview, status icon); Advanced = filters (channel, direction, status, date),
 * full thread view, CSV export."* This is Normal. Advanced is deferred whole
 * rather than half-built, and one of its filters — direction — cannot be built
 * at all, because `outreach_messages` is outbound-only and nothing inbound
 * exists to filter for (see `MessageLog`).
 *
 * ⚠️ **A "status icon" is not built as an icon alone.** `22`'s rule is that
 * colour and glyph are never the sole indicator, so the state is carried by
 * words and the icon would be decoration — so the words ship and the icon does
 * not.
 *
 * ## ⛔ The day-one sentence and the failure state shared one appearance (10042)
 *
 * `$neverSent` reads *"When we start asking your customers for reviews, every
 * message will appear here."* — a future-tense promise, correct on the day an
 * account is opened. **It was also what a tenant read while every one of their
 * review invites was being refused**, because a refused email rolls back the
 * `outreach_messages` row that would have recorded it (correctly: no message
 * went), so the log stayed empty and the screen went on promising.
 *
 * ⚠️ **THE TWO STATES ARE NOT DISTINGUISHABLE FROM THIS TABLE AND NEVER WILL
 * BE** — the table's whole job is to hold messages that happened. So the second
 * question is asked of the thing that actually decides it: whether this platform
 * may mail somebody else's customer *right now*. {@see self::inviteEmailsHeld()}.
 */
#[Layout('components.account.layout')]
final class Messages extends Component
{
    use WithPagination;

    public function render(MessageLog $log, PlatformMailer $mailer, DefaultsRegistry $defaults): View
    {
        // Refused rather than resolved when there is no tenant, for the reason
        // `Settings` and `Home` give: internal staff belong to no business by
        // design (`28` §9.1), so a signed-in support agent typing this URL is
        // the ordinary way to arrive here with nothing resolved.
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.messages', [
            'messages' => $log->page(),
            'neverSent' => $log->isEmpty(),
            'inviteEmailsHeld' => $this->inviteEmailsHeld($mailer, $defaults),
        ]);
    }

    /**
     * Whether review invites by email would be refused if one came up now.
     *
     * ⛔ **TWO CONDITIONS, AND DROPPING EITHER MAKES THIS DISHONEST IN A
     * DIFFERENT DIRECTION.**
     *
     *   the switch    `review_invite.email_enabled` seeds **false**, so on a
     *                 deployment that has not turned the channel on nothing is
     *                 attempted at all — and *"when we start asking your
     *                 customers"* is then exactly true. Warning there would be
     *                 telling somebody something is broken when nothing has
     *                 begun. ⚠️ Read `=== true` and not truthily, which is
     *                 `ReviewInviteSender::send()`'s own reading of the same key
     *                 and for its reason: anything malformed means do not send.
     *
     *   the transport {@see PlatformMailer::customerMailRefusal()} — the same
     *                 method the sender asks one gate before it writes anything,
     *                 rather than a second reading of the three conditions
     *                 behind it. A guard holding its own twin of the pattern its
     *                 subject reads is 8460's shape even when both copies are
     *                 correct today.
     *
     * ⛔ **THE REFUSAL STRING IS NEVER RETURNED AND NEVER RENDERED.** It names
     * our mailer, a registry row and open question H; it is written for the
     * operator reading `Admin\MailSending`. `MessageLog::explain()` already
     * settles the rule for this screen — the stored vendor string stays in the
     * row, and the owner reads words we wrote for them.
     *
     * ⚠️ **AND IT IS A CLAIM ABOUT NOW, NOT A HISTORY.** A tenant whose invites
     * were refused last week and whose platform is healthy today reads nothing
     * here, because nothing in this application recorded those attempts —
     * `automation_runs` carries them and is not this screen's. Stating that
     * limit is 10044; building the history is not this slice.
     *
     * ⚠️ **EMAIL ONLY, AND THE COPY SAYS SO.** `send()` falls through to SMS
     * when email declines, so on a tenant with both channels on the invites are
     * still going — by text. A notice reading *"review invites aren't going
     * out"* would be false for exactly that tenant.
     */
    private function inviteEmailsHeld(PlatformMailer $mailer, DefaultsRegistry $defaults): bool
    {
        if ($defaults->value('review_invite.email_enabled') !== true) {
            return false;
        }

        return $mailer->customerMailRefusal() !== null;
    }
}
