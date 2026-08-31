<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Enums\OauthProvider;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\ProviderRequestFailed;
use App\Models\Business;

/**
 * Microsoft Graph, through the vault.
 *
 * WHAT THIS IS FOR, AND WHAT IT IS NOT. Microsoft 365 is the current email
 * sender (CLAUDE.md §Vendors), and this is the transport underneath that. It is
 * *not* the bulk mailer. Exchange Online throttles per-mailbox recipients per
 * day and messages per minute, and Microsoft's terms do not contemplate bulk
 * application mail through mailboxes — Graph's own sendMail reference is
 * explicit that a 202 "doesn't indicate that the request processing has
 * completed" and that "delivery of the message is subject to Exchange Online
 * limitations and throttling"
 * (https://learn.microsoft.com/en-us/graph/api/user-sendmail, updated
 * 2026-06-19).
 *
 * The live per-mailbox limits are open question **G** in docs/DECISIONS.md and
 * are unresolved. Nothing here assumes a number, and no campaign should use this
 * path until they are verified. Azure Communication Services is the volume path,
 * which is why outbound mail belongs behind a mailer driver from the first send.
 *
 * BOUNCES. Graph returns 202 and nothing else. There is no structured bounce or
 * complaint notification — failures come back as NDRs to the sending mailbox,
 * which is open question **H**. Suppression handling therefore cannot be built
 * on this class's return value, and this class does not pretend otherwise.
 */
final class MicrosoftGraphService extends ProviderClient
{
    protected function provider(): OauthProvider
    {
        return OauthProvider::Microsoft;
    }

    /**
     * Send one message as the connected mailbox.
     *
     * POST https://graph.microsoft.com/v1.0/me/sendMail, delegated permission
     * `Mail.Send` — the least-privileged permission for this call, with no
     * higher-privileged alternative offered (permissions reference updated
     * 2026-07-28). Success is **202 Accepted with an empty body**, so there is
     * no message id to record and no way to correlate a later NDR from the
     * response alone.
     *
     * `$message` is Graph's own Message resource shape:
     *   ['subject' => …, 'body' => ['contentType' => 'HTML', 'content' => …],
     *    'toRecipients' => [['emailAddress' => ['address' => …]]]]
     *
     * It carries recipient addresses and message content, so it is never logged
     * — VendorLog records status and timing only, and this method passes it
     * nothing else.
     *
     * @param  array<string, mixed>  $message
     *
     * @throws ProviderNotConnected
     * @throws ProviderRequestFailed
     */
    public function sendMail(
        Business $business,
        array $message,
        bool $saveToSentItems = true,
    ): bool {
        $url = rtrim((string) config('oauth.providers.microsoft.graph_endpoint'), '/')
            .'/me/sendMail';

        $response = $this->send(
            $business,
            'POST',
            $url,
            fn () => $this->authorized($business)->post($url, [
                'message' => $message,
                'saveToSentItems' => $saveToSentItems,
            ]),
        );

        return $response->status() === 202;
    }
}
