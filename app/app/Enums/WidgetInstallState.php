<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What we can honestly say about a tenant's widget install (3091).
 *
 * ⚠️ **TWO OF THE THREE ARE NEGATIVE, AND THAT IS THE WHOLE VALUE.** A
 * verification that can only ever say "yes" is worth nothing: the state that
 * earns this feature is {@see self::Stopped} — a widget that worked and does not
 * any more, because a redesign dropped the footer, a caching layer stripped the
 * tag, or a theme update ate it. It is reachable only because the positive
 * **expires** (`41` §3.3's 72 hours); without that, this would record for ever
 * that every install which ever worked is still working.
 *
 * ⚠️ **`NotSeenYet` IS NOT "NOT INSTALLED", AND NOTHING MAY RENDER IT AS THAT**
 * (3084). The mechanism observes a real visitor loading a real page (3080), so a
 * perfectly pasted snippet on a page nobody has opened yet looks exactly like no
 * snippet at all. The copy names the thing the owner can do about it — open
 * their own website — rather than accusing them of a failure we cannot see.
 *
 * `22`: colour is never the sole indicator, so every case carries a label and a
 * sentence, and the Blade pairs them with an icon.
 */
enum WidgetInstallState: string
{
    /**
     * Nothing has ever fetched this feed from an allowed website.
     *
     * Either the line is not on the page, or it is and nobody has been there
     * since. **We cannot tell those apart** and must not pretend to.
     */
    case NotSeenYet = 'not_seen_yet';

    /** Seen inside the window. The one positive. */
    case Working = 'working';

    /**
     * Seen once, and not lately. The snippet was working and has stopped
     * answering — the state a "mark installed" checkbox could never produce.
     */
    case Stopped = 'stopped';

    /**
     * The short label beside the icon. Outcome language (`22`): what is true for
     * the owner, never how it is determined.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotSeenYet => 'Not seen yet',
            self::Working => 'Showing on your website',
            self::Stopped => 'Stopped showing',
        };
    }

    /**
     * The sentence under the label.
     *
     * ⚠️ **THE FIRST ONE IS THE CAREFUL ONE.** It has to be honest that we have
     * not seen the widget without claiming the owner has not installed it, and
     * it has to name the one action that resolves the ambiguity.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::NotSeenYet => 'We have not seen your reviews load on your website yet. '
                .'If you have added the line, open your website in a browser — '
                .'that is what confirms it.',
            self::Working => 'Your reviews loaded on your website recently.',
            self::Stopped => 'Your reviews were loading on your website and have not '
                .'lately. The line may have been removed by a redesign or a plugin, '
                .'or the website may simply have had no visitors.',
        };
    }

    /**
     * The pill this renders as (`29` §5.4).
     *
     * ⚠️ **`NotSeenYet` IS `Unknown`, AND THAT IS THE HONEST MAPPING RATHER THAN
     * A CAUTIOUS ONE.** `SignalState::Unknown` exists for "we cannot say", which
     * is exactly the state: the widget may be installed perfectly on a page
     * nobody has opened. Rendering it as `Alert` would tell an owner who did the
     * work correctly that something is wrong (3084), and rendering it as `Ok`
     * would claim a verification we have not made.
     */
    public function signal(): SignalState
    {
        return match ($this) {
            self::NotSeenYet => SignalState::Unknown,
            self::Working => SignalState::Ok,
            self::Stopped => SignalState::Attention,
        };
    }

    /** Whether this state is the one that needs somebody to do something. */
    public function needsAttention(): bool
    {
        return $this === self::Stopped;
    }
}
