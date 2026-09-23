<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OwnerNotificationKind;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Services\AuditService;
use App\Services\Sms\OwnerNotifications;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * What this platform texted one business's own account holder, and what they
 * said back — wave 41 lane E, decision 11110.
 *
 * ⛔ **THE READER FOUR COLUMNS WERE WRITTEN FOR AND DID NOT HAVE.**
 * `owner_notifications.kind`, `.occasion` and `.provider_message_id`, and
 * `owner_replies.in_reply_to_notification_id`, had writers and **no reader
 * anywhere in `app/`** — 272's shape, four columns wide. 10843 named this screen
 * as cheap and deliberately unbuilt (a different lane owned this directory that
 * wave) and said what it would render: *"we texted them about X at T; they said
 * Y at T+4m."* That sentence is the whole specification and this is it.
 *
 * ⚠️ **IT IS *PART* OF 10548's OWED SCREEN AND NOT THE WHOLE OF IT.** That row
 * names *"a dedicated screen for reading an owner's own inbound replies"* as
 * owed, and does not say whose eyes. **This is platform staff's.** The account
 * holder themselves still sees one activity-feed line saying *"You texted us
 * back"* and nothing else — 10843's *"the owner is still told nothing back"* is
 * unchanged by this screen, and a tenant-facing surface is a different slice
 * with different questions in it.
 *
 * ## ⛔ Three things this screen must not be read as saying
 *
 * ⛔ **A ROW IS A SEND, NEVER A DELIVERY** (10823, and 9371's general shape: a
 * column that records a dispatch is not one that records a delivery, and the two
 * are one method name apart). `owner_notifications.provider_message_id` is
 * filled after the carrier answers, so a row cannot exist for a send that was
 * never made — and nothing in `app/` reads an owner-channel delivery receipt, so
 * a row is not evidence the phone got it. The page says so in those words.
 *
 * ⛔ **THE LINK BETWEEN A REPLY AND A SEND IS AN INFERENCE FROM RECENCY**
 * (10829). No owner-directed message carries a reply token, a thread id or a
 * numbered option — 10832 refuses that grammar and says what has to exist first
 * — so recency inside
 * {@see OwnerNotifications::CORRELATION_WINDOW_HOURS} is the only signal there
 * is. **Null is the common answer**, and it means *"we do not know what this
 * answers"* rather than *"this is not a reply"*. A screen that rendered the link
 * as a fact would make the inference look stronger than it is, which is the one
 * way this page could do harm.
 *
 * ⛔ **NOTHING ACTS ON A REPLY** (10722, unchanged). No automation resumes, no
 * campaign is triggered, no `AgentTurns` call is made. An operator who assumed
 * otherwise would leave an account holder waiting for an answer nobody is
 * writing, so the page says it rather than implying it.
 *
 * ## ⚠️ What it shows, and what it does not
 *
 * ⛔ **IT SHOWS AND IT DOES NOTHING ELSE** — {@see OwnerNotifyConsents}' rule.
 * No reply box, no resend, no correction. Both tables refuse `updating` in the
 * model, and the one control that would matter — texting the account holder
 * back — belongs to a sender with a permit, not to a page.
 *
 * ⚠️ **IT RENDERS AN ACCOUNT HOLDER'S OWN WORDS, PLAINTEXT, BEHIND THE
 * PLATFORM-STAFF GATE.** That is the point of the screen: *"they said Y"* is
 * half of the sentence 10843 asked for, and a screen that showed only that a
 * reply existed would answer nothing anybody could act on. Nothing new is
 * stored, the read is audited in the looked-up tenant's own log, and the words
 * are never in a URL, a toast or a log line.
 *
 * ⚠️ **IT LOOKS A BUSINESS UP BY NUMBER**, {@see OwnerNotifyConsents}',
 * {@see TermsAcceptances}' and {@see PhiTenants}' reason in the same words:
 * `businesses` carries two RLS policies and neither admits platform staff, so
 * the runtime role cannot enumerate businesses at all.
 *
 * ⚠️ **NO PERSONAL DATA REACHES A TOAST** (decision 104). The only toasts are
 * "enter a number" and "there is no business N".
 */
final class OwnerChannelTexts extends Component
{
    /**
     * The business number an admin typed. A string, because it comes from a
     * text input and an unparseable one has to be answerable rather than fatal.
     */
    public string $lookup = '';

    /**
     * The business in view, once one has resolved.
     *
     * ⚠️ `#[Locked]` FOR {@see OwnerNotifyConsents::$businessId}'s EXACT REASON,
     * and it is sharper here. Without it this is an ordinary public property
     * arriving in the update payload, so anybody who could reach this component
     * could set it to any integer and have `render()` read that tenant's account
     * holder's own messages with {@see self::lookUp()} never called — which
     * means **with nothing written to any tenant's audit log**. The lock is what
     * makes the audit unskippable rather than customary.
     */
    #[Locked]
    public ?int $businessId = null;

    /**
     * Which of the eleven `business.viewed_by_staff` surfaces this is.
     *
     * ⚠️ **A CONSTANT SO THAT A TEST CAN NAME IT WITHOUT RETYPING IT** — a lint
     * or a fixture holding its own copy of the value it checks is 8460's shape
     * even while both copies are correct.
     */
    public const AUDIT_SURFACE = 'owner_channel_texts';

    public function mount(): void
    {
        // Repeated on the component rather than left to the route's `can:`
        // middleware — `TermsAcceptances`' reason: a route-gate test passes
        // while `mount()` is wide open, because `can:` refuses during route
        // matching and the component never runs.
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Put a business in view, and record the read against it.
     *
     * ⚠️ IT CAN THROW, WHICH IS CORRECT — the audit write is not wrapped and
     * `$this->businessId` is set after it, so an unwritable `audit_log` fails
     * the whole action instead of showing an account holder's messages with no
     * trace that anybody looked.
     */
    public function lookUp(AuditService $audit): void
    {
        // ⛔ **ITS OWN `authorize()`, AND THE HARNESS CANNOT PROVE IT.** A
        // Livewire authorization test driven through the harness proves
        // `render()`'s guard and never the action's own: measured in wave 40,
        // the action's `authorize()` deleted and the test stayed green at one
        // assertion, because `render()` runs after every action. The only test
        // that isolates this calls the method directly with no render after it.
        $this->authorize(AdminAccess::GATE);

        $id = (int) trim($this->lookup);

        if ($id <= 0) {
            $this->businessId = null;
            Toaster::error('Enter a business number.');

            return;
        }

        $business = $this->resolve($id);

        if (! $business instanceof Business) {
            $this->businessId = null;
            Toaster::error("There is no business {$id}.");

            return;
        }

        Tenancy::actingAs(
            $id,
            // ⛔ **THE ACTION STRING IS THE CONVENTION'S AND IS DELIBERATELY NOT
            // SPLIT — 11180.** Eleven surfaces file `business.viewed_by_staff`,
            // and decision 622's reason is that the audit explorer should show
            // them *"under one search rather than two vocabularies for one
            // event"*. ⛔ **A per-screen action here would make the MOST
            // sensitive read of the eleven invisible to the query that exists to
            // find reads**: anybody grepping or filtering
            // `business.viewed_by_staff` would silently exclude the one screen
            // whose subject is a named person's own message bodies. **A taxonomy
            // split fails open on the new screen**, which is the wrong direction
            // for the one entry that is about looking at somebody's words.
            //
            // ✅ **WHAT WAS MISSING IS THE `surface` KEY, AND IT IS NOT NEW** —
            // {@see \App\Services\Support\AccountDirectory::open()} has filed
            // `['surface' => 'account_360', 'matched_by' => …]` since 622.
            // `AuditService::record()`'s own docblock names `metadata` as where
            // *what happened* goes, and this is that. ⚠️ **The key is SPARSE**:
            // nine other surfaces still file none, so its absence means *not yet
            // named* and never *not a message-body screen*.
            //
            // ⛔ **AND IT IS THE SURFACE, NEVER THE SUBJECT.** No message body,
            // no owner name and no phone number goes in here — `audit_log` is
            // append-only forever and nothing prunes it, which is
            // `AccountDirectory`'s own reason for recording *how* an account was
            // matched rather than *what was typed*.
            fn (): AuditLogEntry => $audit->record(
                'business.viewed_by_staff',
                $this->actor(),
                $business,
                ['surface' => self::AUDIT_SURFACE],
            ),
        );

        $this->businessId = $id;
    }

    public function render(OwnerNotifications $notifications): View
    {
        $this->authorize(AdminAccess::GATE);

        $id = $this->businessId;

        if ($id === null) {
            return view('livewire.admin.owner-channel-texts', [
                'business' => null,
                'timeline' => [],
                'truncated' => false,
                'windowHours' => $notifications->correlationWindowHours(),
                'limit' => $notifications->ledgerLimit(),
            ]);
        }

        $business = Tenancy::actingAs($id, fn (): ?Business => Business::query()->find($id));

        $ledger = $notifications->ledgerFor($id);

        return view('livewire.admin.owner-channel-texts', [
            'business' => $business,
            'timeline' => $this->timeline($ledger['sends'], $ledger['replies']),
            // ⚠️ **SAID OUT LOUD RATHER THAN LEFT TO BE NOTICED.** A capped list
            // an operator has not been told is capped is a list they will read
            // as complete, and this one is the record of what an account holder
            // was told.
            'truncated' => count($ledger['sends']) === $notifications->ledgerLimit()
                || count($ledger['replies']) === $notifications->ledgerLimit(),
            'windowHours' => $notifications->correlationWindowHours(),
            'limit' => $notifications->ledgerLimit(),
        ]);
    }

    /**
     * The two halves as one list, newest first.
     *
     * ⛔ **ONE TIMELINE AND NOT TWO LISTS**, {@see NumberLookup::timeline()}'s
     * argument on a different subject: *"we texted them about X at T; they said
     * Y at T+4m"* is a sequence, and two side-by-side lists let an operator read
     * either alone and answer the wrong question.
     *
     * ⚠️ **BY TIMESTAMP, WHICH IS WEAKER THAN THAT FILE'S SORT BY KEY, AND THE
     * DIFFERENCE IS FORCED.** These are two tables with two independent
     * sequences, so there is no shared monotonic key to order by. The tie-break
     * is that a reply never precedes the send it answers, so at an identical
     * instant the send is rendered as the earlier event.
     *
     * @param  list<array{id: int, kind: OwnerNotificationKind, occasion: string, carrierReference: string, at: CarbonImmutable}>  $sends
     * @param  list<array{id: int, answering: ?int, body: string, at: ?CarbonImmutable}>  $replies
     * @return list<array{key: string, direction: string, headline: ?string, detail: ?string, body: ?string, reference: ?string, when: ?string, sortAt: int, rank: int}>
     */
    private function timeline(array $sends, array $replies): array
    {
        $sentAtOf = [];

        foreach ($sends as $sent) {
            $sentAtOf[$sent['id']] = $sent['at'];
        }

        $rows = [];

        foreach ($sends as $sent) {
            $rows[] = [
                'key' => 'sent-'.$sent['id'],
                'direction' => 'sent',
                // ⛔ **THE ENUM'S OWN SENTENCE, NOT A SECOND ONE HERE** (11117).
                // A `match` in this component would be two vocabularies for one
                // enum with the uncalled one going stale, and
                // `OwnerNotificationKind::label()` was written for exactly this
                // reader — *"outcome language, for an operator reading a record
                // of what was sent"* — and had no caller anywhere until this
                // screen. Its `match` has no default, so a second case must be
                // given a sentence rather than inheriting a plausible wrong one.
                'headline' => $sent['kind']->label(),
                // ⚠️ **THE OCCASION IS OURS AND NOT THEIRS.** It is the sender's
                // own idempotency key — for the one kind that exists, the
                // carrier's inbound message id — so it is an operator's handle
                // on which event this was, never anything a person wrote.
                'detail' => 'Our reference for the event: '.$sent['occasion'],
                'body' => null,
                'reference' => $sent['carrierReference'],
                'when' => $sent['at']->format('j F Y, H:i'),
                'sortAt' => $sent['at']->getTimestamp(),
                'rank' => 0,
            ];
        }

        foreach ($replies as $reply) {
            $rows[] = [
                'key' => 'reply-'.$reply['id'],
                'direction' => 'reply',
                // ⛔ **NO HEADLINE, AND READING THE RENDERED PAGE AS TEXT IS
                // WHAT FOUND IT.** This said *"They texted back"* under a line
                // already reading *"They replied — 28 August 2026, 07:54"*, so
                // every reply carried the same sentence twice and the words the
                // account holder actually wrote were the third thing on the
                // row. A send has a headline because `kind` is real
                // information; a reply's information is the quote.
                'headline' => null,
                'detail' => $this->answersSentence($reply['answering'], $sentAtOf),
                'body' => $reply['body'],
                'reference' => null,
                'when' => $reply['at']?->format('j F Y, H:i'),
                'sortAt' => $reply['at']?->getTimestamp() ?? 0,
                'rank' => 1,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['sortAt'], $b['rank']] <=> [$a['sortAt'], $a['rank']]);

        return $rows;
    }

    /**
     * What a reply appears to be answering, in words that keep the inference an
     * inference.
     *
     * ⛔ **NEVER "REPLYING TO", WHICH WOULD BE A CLAIM ABOUT WHAT THEY MEANT.**
     * The column is filled from *"the most recent thing we texted this account
     * holder about"* and nothing else (10829).
     *
     * @param  array<int, CarbonImmutable>  $sentAtOf
     */
    private function answersSentence(?int $answering, array $sentAtOf): string
    {
        if ($answering === null) {
            // ⚠️ **TWO REASONS FOR THE NULL AND THE COLUMN RECORDS NEITHER**,
            // so both are said. *"Nothing recent enough had been sent"* alone
            // is a claim this row cannot support: 10829 names the other
            // population by name — every account holder whose reply predates
            // the table that would have answered it.
            return 'We do not know what this answers. Either nothing had been texted to them '
                .'recently enough for us to guess, or this arrived before we kept this record.';
        }

        if (! isset($sentAtOf[$answering])) {
            // Older than this page shows, or swept by the retention period an
            // operator set. Saying "we do not know" here would be wrong in the
            // other direction: we do know, it is simply not on this page.
            return 'It arrived soon after a message older than the ones listed here.';
        }

        return 'It arrived soon after the message we sent on '
            .$sentAtOf[$answering]->format('j F Y, H:i')
            .'. That is our guess from the timing, not something they said.';
    }

    /**
     * The business a typed number resolves to, or null.
     *
     * Its own tenant context, because `businesses` is FORCE ROW LEVEL SECURITY
     * and a lookup with the admin's own tenant established would answer "no" for
     * every business but their own.
     */
    private function resolve(int $id): ?Business
    {
        return Tenancy::actingAs(
            $id,
            fn (): ?Business => Business::query()->whereKey($id)->first(),
        );
    }

    /**
     * Who is reading this, for the audit entry — {@see OwnerNotifyConsents}' and
     * {@see NumberLookup}' convention: automation is a first-class actor in this
     * log, so a user foreign key would have nothing to point at for most
     * entries.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
