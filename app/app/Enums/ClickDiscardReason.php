<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a fetch of one of our public links was not counted as a person opening it.
 *
 * ⚠️ **TWO ROUTES USE THIS AND ONLY ONE OF THEM STORES IT** (2924, 2925). The
 * short-link redirector writes the discarded fetch with its reason;
 * `/f/{slug}/to/{destination}` writes nothing at all, because
 * `destination_clicks` deliberately has nowhere to put one — decision 307's
 * schema test forbids any column readable as an outcome, and every row in that
 * table is read as "a customer opened Google". The enum is shared regardless:
 * one vocabulary for one classifier is what stops the second surface growing a
 * second, quietly different idea of what a machine is.
 *
 * ⛔ **THE REASON IS STORED BECAUSE THE FILTER IS A HEURISTIC AND WILL BE
 * ARGUED WITH.** When a tenant asks why a campaign shows forty opens and their
 * own phone is not one of them, the answer has to be readable off the rows
 * rather than reconstructed from what the code did that week. It is also how a
 * filter that has started discarding real people is noticed: a sudden mass of
 * one reason is a bug with a name.
 *
 * ⚠️ **EVERY CASE HERE IS SOMETHING THE REQUEST DECLARED ABOUT ITSELF.** None of
 * them is an inference from behaviour, and none is assembled from device signals
 * — `CLAUDE.md` forbids concatenating those into anything stable, and a
 * "suspicious timing" heuristic is exactly the fingerprint-shaped thing that ban
 * exists to prevent.
 */
enum ClickDiscardReason: string
{
    /**
     * The client asked for the link without intending to show it — Chrome's
     * `Purpose: prefetch`, Safari's `X-Purpose: preview`, and the link-preview
     * fetch every messaging app makes when it renders a bubble.
     *
     * ⚠️ **THIS ONE IS THE COMMON CASE AND IT ARRIVES BEFORE THE PERSON DOES.**
     * A preview fetch happens when the message is *delivered*; counting it makes
     * every delivered message look opened within seconds.
     */
    case Prefetch = 'prefetch';

    /**
     * A HEAD request, or anything else that is not a GET.
     *
     * Nobody reads a web page with HEAD. Link checkers use it precisely because
     * it costs the destination nothing, which is also what makes it a clean
     * signal here.
     */
    case NotAGet = 'not_a_get';

    /**
     * A user agent that says it is a bot, a crawler, a scanner or a preview
     * service.
     *
     * ⚠️ **SELF-DECLARED, AND THAT IS THE WHOLE OF ITS RELIABILITY.** A scanner
     * presenting a browser's user agent is indistinguishable from a person at
     * this layer, and this application does not pretend to catch it. What this
     * removes is the traffic that announces itself, which is most of it.
     */
    case DeclaredBot = 'declared_bot';
}
