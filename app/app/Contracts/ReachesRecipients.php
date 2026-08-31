<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * A transport that hands a message to something able to carry it to a person.
 *
 * ⛔ **IT DECLARES A CAPABILITY AND IT IS DECLARED BY THE THING THAT HAS IT.**
 * {@see Texter}'s own contract already says a send that did not happen *"must
 * not be indistinguishable from one that did"* — and there is one
 * implementation for which that is false by construction: the log driver
 * returns a `SentText` for a message that reaches nobody, on purpose, because
 * `BUILD-PLAN` §2.10.4 makes it the thing that lets every other slice be
 * tested without a carrier. **That is correct for the customer channel and it
 * is not correct everywhere.** This interface is how a caller that has to tell
 * the difference asks, without asking a config string what it is bound to.
 *
 * ⛔ **AN ALLOWLIST AND NEVER A DENYLIST, WHICH IS THE WHOLE OF THE SHAPE**
 * (11300). The obvious repair is `PlatformMailer::UNDELIVERABLE`'s — a list of
 * driver *names* that cannot deliver — and it is the answer that reads best on
 * a diff. It is refused here: a denylist over a string is decision 511's shape,
 * it is a second source of truth beside `AppServiceProvider::smsDriver()`'s
 * `match`, and it fails **open** on the next non-delivering driver, which is
 * exactly the direction that costs somebody an urgent page. A driver that has
 * not said it reaches anybody is treated as not reaching anybody.
 *
 * ⚠️ **SO A NEW DRIVER THAT FORGETS THIS REFUSES OWNER-CHANNEL SENDS, AND
 * THAT IS THE INTENDED COST.** The alternative — a marker on the driver that
 * *cannot* deliver — is one line shorter and fails the other way: a forgotten
 * marker there reports a page to somebody nobody paged.
 *
 * ⛔ **IT IS A MARKER AND DELIBERATELY HAS NO METHOD.** A
 * `reachesRecipients(): bool` would let an implementation declare the
 * capability and then answer `false` — *"a control described as present and
 * off reads as a control somebody could turn on"* — and it would put the
 * question one indirection away from the type system. Implementing this **is**
 * the claim.
 *
 * ⚠️ **AND IT IS NOT A SECOND TRANSPORT CONTRACT.** There is no method here, so
 * nothing can be sent through it, and the messaging lint that confines
 * {@see Texter} to five files is untouched by anybody naming this one.
 *
 * ⚠️ **`true` HERE IS STILL NOT A DELIVERY.** It says the bound transport can
 * reach a carrier at all — the same narrowness `PlatformMailer::canDeliver()`
 * documents about itself. A message an accepting carrier then filters, because
 * the sending number is not the one the 10DLC campaign was registered against,
 * is still invisible to this application; `App\Services\Sms\SentText` says so at
 * the value it returns.
 */
interface ReachesRecipients
{
    //
}
