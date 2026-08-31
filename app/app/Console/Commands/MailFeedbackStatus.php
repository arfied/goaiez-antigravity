<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlatformHealthSignal;
use App\Services\Ops\PlatformHealth;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The reading half `mail:probe-ses-simulator` needs, and the one instrument
 * that answers whether the whole platform — not just a probe — would notice a
 * silent SES `Notification` type (10280–10289).
 *
 * ⛔ **WHAT IT DOES NOT DO, STATED BEFORE WHAT IT DOES.** This does not read
 * `suppression_list` or `opt_outs` — those tables are written and read only
 * through `App\Services\Consent`, and a lint in
 * `tests/Feature/Architecture/ConsentTest.php` fails the build on any other
 * file in `app/` querying that model directly, so a second reader here would
 * be a second copy of a chokepoint that already has one. **If you need to know
 * whether a specific address is suppressed, that is a question for whoever
 * owns the consent lane** — this command answers a narrower one: did an SES
 * `Notification` arrive at all, of which type, and when.
 *
 * ## What it reads
 *
 * A heartbeat per event type — `ses.notification.bounce`,
 * `.complaint`, `.delivery`, `.other` — written by `SesFeedbackController`
 * only after `SnsMessageVerifier::verify()` answers `Verified`, so a beat is
 * proof a genuine, AWS-signed event of that type was understood. Beside it, the
 * two failure-only counters `SesFeedbackController::refuse()` already writes:
 * `WebhookSignature` (a bad signature, an unknown topic, or no allowlist at
 * all) and `WebhookKeyUnavailable` (AWS's own certificate host would not
 * answer, so nothing was judged either way).
 *
 * ⛔ **THE THREE READINGS TOGETHER ARE WHAT TELL "NOTHING HAS BEEN SENT" APART
 * FROM "EVERYTHING IS BEING REFUSED".** A platform with no heartbeats and no
 * failures has not been tested. A platform with no heartbeats and rising
 * `WebhookSignature` failures has a broken topic allowlist or a rotated
 * signing arrangement. A platform with a `complaint` heartbeat and no
 * `delivery` heartbeat — with `WebhookSignature` and `WebhookKeyUnavailable`
 * both flat — has exactly the failure `.env.example`'s "delivery is not
 * optional" warning describes: the identity's Delivery checkbox came
 * unchecked while Bounce and Complaint stayed on. That third case is the one
 * no existing bell can see — `SendingRates::trafficWithoutOutcomes()` and
 * `PlatformComplaintRate`'s two silence bells all read `delivered + failed`
 * as one number, so a healthy bounce feed masks a dead delivery feed at
 * every one of them.
 *
 * ⛔ **THIS COMMAND ACCUSED A CORRECT SES ACCOUNT ON ITS FIRST CONTACT WITH
 * REALITY, AND THE BUG WAS TREATING `bounce` AND `complaint` AS EQUALLY
 * DIAGNOSTIC — CORRECTED 2026-08-27 (10460–10479).** A message to
 * `bounce@simulator.amazonses.com` bounces, so it is **never delivered** —
 * by AWS's own design, not by anything this account controls — so a `bounce`
 * heartbeat with no `delivery` heartbeat proves nothing about whether
 * Delivery is subscribed: there was never anything for a Delivery
 * notification to report. **A `complaint` heartbeat is different in kind**:
 * a complaint is filed on a message that reached an inbox, so an identity
 * with Delivery subscribed publishes both — verified against real AWS bytes
 * on 2026-08-26, where a single `complaint@` probe produced a `Complaint`
 * and a `Delivery` `Notification` **sharing one timestamp**. `complaint`
 * heartbeating with `delivery` silent is therefore the genuine signal this
 * class exists to surface; `bounce` heartbeating with `delivery` silent is
 * the ordinary, expected shape of a bounce probe and must not be read as a
 * fault. See {@see self::handle()}'s three branches, in that order.
 *
 * ⛔ **THIS IS ON-DEMAND AND DELIBERATELY NOT A BELL.** It answers a question
 * when asked; it pages nobody on its own. Turning the genuine pattern above
 * into something that rings without being asked belongs beside
 * `PlatformHealthChecks`'s other rate-with-no-threshold checks
 * (`checkCredentialFaults()`'s shape), and that file sits closer to the
 * messaging/consent lane's own alerting machinery (`SendingGuard`,
 * `PlatformComplaintRate`) than to this one — reported as owed rather than
 * reached for.
 */
#[Signature('mail:feedback-status
    {--minutes=1440 : How far back to sum WebhookSignature / WebhookKeyUnavailable failures (default 24h)}')]
#[Description('Report whether an SES Notification has verified, of which type, and whether verification has been failing')]
final class MailFeedbackStatus extends Command
{
    /**
     * @var list<string>
     */
    private const array EVENT_SOURCES = ['bounce', 'complaint', 'delivery', 'other'];

    public function handle(PlatformHealth $health): int
    {
        $minutes = max(1, (int) $this->option('minutes'));

        $beats = [];

        foreach (self::EVENT_SOURCES as $source) {
            $beats[$source] = $health->lastBeat('ses.notification.'.$source);
        }

        $this->line('Last verified SES Notification, by type:');
        $this->line('');

        foreach ($beats as $source => $at) {
            $this->line('  '.str_pad($source, 15).': '.$this->describe($at));
        }

        $this->line('');

        $signature = $health->totals(PlatformHealthSignal::WebhookSignature, $minutes, 'ses');
        $keys = $health->totals(PlatformHealthSignal::WebhookKeyUnavailable, $minutes, 'ses');

        $this->line("Verification failures since {$signature['since']->toIso8601String()}:");
        $this->line('  refused as unverified (bad signature / unknown topic / empty allowlist): '.$signature['total']);
        $this->line('  could not fetch AWS\'s certificate at all (nothing judged either way): '.$keys['total']);
        $this->line('');

        // ⚠️ **`other` IS DELIBERATELY EXCLUDED FROM THIS CHECK.** An
        // unrecognised event type verifying proves the transport and the
        // signature work; it says nothing about Bounce, Complaint or Delivery
        // specifically, and counting it here would let it satisfy the "at
        // least one type has verified" branch below without delivery ever
        // having been among them.
        $anyMeaningfulBeat = $beats['bounce'] !== null || $beats['complaint'] !== null || $beats['delivery'] !== null;

        if (! $anyMeaningfulBeat) {
            $this->components->warn('No SES Notification of any kind has ever verified on this deployment.');

            if ($signature['total'] > 0 || $keys['total'] > 0) {
                $this->line('  And verification has been failing in the window above — that is very');
                $this->line('  likely the reason, not "nothing has arrived". Check the allowlist');
                $this->line('  (SES_SNS_TOPIC_ARNS) and the SNS subscription state (mail:sns-subscriptions)');
                $this->line('  before assuming the feed is simply untested.');
            } else {
                $this->line('  And nothing has failed to verify either, which is consistent with the');
                $this->line('  ordinary explanation: nothing has been sent through SES yet, or the SNS');
                $this->line('  subscription is still Pending confirmation. Run');
                $this->line('    php artisan mail:probe-ses-simulator bounce@simulator.amazonses.com');
                $this->line('  to find out which.');
            }

            return self::FAILURE;
        }

        // ⛔ **THE ONE PATTERN THIS COMMAND EXISTS TO SURFACE.** Complaint has
        // verified at least once and delivery never has, with verification
        // itself not failing — the shape a topic subscribed to Bounce and
        // Complaint only, or one whose Delivery checkbox came unchecked,
        // produces. `bounce` is deliberately NOT one of this branch's two
        // triggers — see the class docblock's 2026-08-27 correction: a bounce
        // is never delivered by design, so its absence of a paired Delivery
        // event says nothing about whether Delivery is subscribed.
        if ($beats['delivery'] === null && $beats['complaint'] !== null) {
            $this->components->warn('Complaint notifications have verified. Delivery notifications never have.');
            $this->line('  This is the pattern `.env.example`\'s "delivery is not optional" warning is');
            $this->line('  about: the per-tenant complaint trip divides complaints by DELIVERIES, so a');
            $this->line('  denominator that never grows makes that containment unable to fire — while');
            $this->line('  every existing silence bell reads this platform as reporting normally, because');
            $this->line('  they all treat "delivered" and "failed" as one combined signal. A complaint is');
            $this->line('  filed on a message that reached an inbox, so this is not the ordinary shape of');
            $this->line('  a bounce probe — Check the identity\'s notification settings (or the');
            $this->line('  configuration set\'s event destination) in the SES console: Delivery must be');
            $this->line('  selected alongside Bounce and Complaint.');

            return self::FAILURE;
        }

        // ⚠️ **BOUNCE VERIFIED, DELIVERY DID NOT — AND THAT IS NOT A FAULT.**
        // A bounce is never delivered by AWS's own design, so a bounce
        // heartbeat with no delivery heartbeat is the ordinary, expected
        // shape of the very first probe in `.env.example`'s own documented
        // sequence — it proves nothing about the SES console either way.
        if ($beats['delivery'] === null) {
            $this->components->info('Bounce notifications have verified. Delivery has not, but a bounce is '
                .'never delivered by design, so this does not indicate a misconfigured console.');
            $this->line('  A bounce probe cannot produce a Delivery event regardless of the identity\'s');
            $this->line('  notification settings — there is nothing for one to report. To test Delivery');
            $this->line('  directly, probe a scenario that does deliver:');
            $this->line('    php artisan mail:probe-ses-simulator success@simulator.amazonses.com');
            $this->line('    php artisan mail:probe-ses-simulator complaint@simulator.amazonses.com');

            return self::SUCCESS;
        }

        $this->components->info('At least one Notification type has verified, and delivery is among them.');
        $this->line('');
        $this->line('  What this proves: a genuine, AWS-signed SES event of each type shown above has');
        $this->line('  reached /webhooks/ses and verified. It does NOT prove every event since is still');
        $this->line('  arriving — only that it has, at least once, as recently as shown.');

        return self::SUCCESS;
    }

    private function describe(?CarbonImmutable $at): string
    {
        return $at === null
            ? 'never'
            : $at->diffForHumans().' ('.$at->toIso8601String().')';
    }
}
