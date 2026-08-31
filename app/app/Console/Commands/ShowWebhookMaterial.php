<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WebhookVerification;
use App\Services\Config\CredentialStore;
use App\Services\Ops\PlatformHealthChecks;
use App\Services\Ops\WebhookMaterialCensus;
use App\Services\Ops\WebhookMaterialLine;
use App\Support\CredentialManifest;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * Can each webhook endpoint tell a genuine delivery from a forgery — today, on
 * this box, with nothing having arrived (11640–11651)?
 *
 * ## ⛔ The gap this closes, stated exactly
 *
 * ⛔ **EVERY VERIFIER BEHIND A `webhooks/` ROUTE FAILS CLOSED ON MISSING
 * MATERIAL, AND EVERY ONE OF THEM ONLY FINDS OUT WHEN A REQUEST ARRIVES.** With
 * `infobip_webhook_secret` unset — **the state of the running install until
 * 2026-08-29** (12276) — inbound
 * deliveries are answered 401 before the body is read, so **a customer who
 * texts STOP is never recorded and never suppressed**, and nothing in this
 * application could say so: the fault counter that would ring
 * {@see PlatformHealthChecks} is written by a **read** of the
 * key, and the only reader is the verifier the request reaches. **An instrument
 * that only looks when its subject is exercised cannot report a subject that is
 * never exercised.**
 *
 * ⛔ **THIS DOES NOT SET ANYTHING AND MUST NOT BE READ AS HAVING FIXED IT.** The
 * secret is the owner's to paste into Ops → Platform → Credentials. What changes
 * is that the absence is now legible at rest instead of costing the first person
 * who texts STOP.
 *
 * ⚠️ **THE KEY WAS SET ON 2026-08-29 AND THE EXAMPLE ABOVE IS KEPT, BECAUSE IT
 * BECAME A BETTER ONE RATHER THAN AN OBSOLETE ONE** (12276). This census now
 * reports all three Infobip endpoints `set` — and **inbound still arrives at
 * nothing**, because the account's inbound subscription belongs to a different
 * product and was already claiming those events. ⛔ **That is this command's own
 * *set is not arriving* caveat below, demonstrated on the very endpoint it was
 * written for** — and the reason that caveat is not decoration.
 *
 * ## ⛔ Why a report at deploy and not a bell every five minutes
 *
 * Four remedies were weighed and three lost:
 *
 *   ⛔ **A seventh arm on `PlatformHealthChecks::sweepCounters()`** — the shape
 *   is right (its own docblock already argues for a check that reads a *state*
 *   with no counter and no threshold) and it needs one thing this application
 *   cannot honestly supply: **whether a vendor is in use**. An endpoint nobody
 *   has connected is not a fault, and a bell that rings on every fresh install
 *   about a working vendor is 511 — *tuned until it stops crying wolf is tuned
 *   until it catches nothing*. The only at-rest in-use signal that exists is
 *   `sms.enabled`, and it speaks for one vendor of four. **Refused rather than
 *   guessed at, and the question is the owner's** (11663).
 *
 *   ⛔ **A fourth {@see WebhookVerification} case** — it would make
 *   the *internal* counter honest about *"a configuration this install does not
 *   have"*, which is a real and separate finding, and it is **traffic-fired by
 *   construction**. It cannot answer the question this command exists for, and
 *   the response an outsider sees may not vary either way.
 *
 *   ⛔ **Widening {@see CredentialStore::board()}'s reach**
 *   — the board is the one thing in the tree that reads every stored row with no
 *   traffic, and **its only caller is a Livewire screen**: it needs a person to
 *   open it, it reports stored-ness rather than *"this endpoint's material"*,
 *   and it cannot see the two `config()`-only gates at all.
 *
 * ✅ **What is left is {@see ShowOperatorAlertChannels}' shape** — reads the
 * running install, prints, and **always exits zero**. Composer halts on the
 * first non-zero script, so a check that failed on a fresh install would strand
 * a deployment after `queue:restart` had already run. **A bell may not be a
 * brake, and a deploy step that reports one is still not a brake.**
 *
 * ## ⚠️ What it cannot claim
 *
 * ⛔ **SET IS NOT CORRECT.** A rotated secret, a topic ARN for the wrong AWS
 * account, and a signature scheme this notification profile does not use all
 * read as ready here and always will. ⛔ **AND SET IS NOT ARRIVING** — nothing
 * in this application knows an inbound message was ever supposed to come, so an
 * endpoint that is configured and silent is indistinguishable from one nobody is
 * posting to. Both are stated in the output rather than left to be discovered.
 */
#[AsCommand(name: 'ops:webhook-material')]
final class ShowWebhookMaterial extends Command
{
    protected $description = 'Show whether every webhook endpoint holds the material it checks its callers with';

    public function handle(WebhookMaterialCensus $census): int
    {
        try {
            $this->report($census);
        } catch (Throwable $e) {
            // R25's outermost net, in the shape a deploy step needs it. An
            // unreadable store or an unbootable router must not be the reason a
            // deployment stops, and must not be reported as "nothing is
            // configured" either, because that is a different fact.
            $this->warn('Could not read the webhook endpoints ('.$e::class.'). '
                .'Check Ops, Platform, Credentials by hand.');
        }

        return self::SUCCESS;
    }

    private function report(WebhookMaterialCensus $census): void
    {
        $lines = $census->lines();

        $this->line('Webhook endpoints — whether this install holds what each one checks its callers with.');
        $this->line('Every one of them refuses a delivery it cannot verify, including a genuine one, before the body is read.');

        if ($lines === []) {
            // ⚠️ **256's GUARD, IN THE CONSOLE.** An empty report and a healthy
            // one look identical, and this is the branch a route-file that never
            // loaded would land in.
            $this->warn('No webhook endpoint was found at all. That is not a healthy platform, it is a '
                .'router this command could not read — every one of them is a live, CSRF-exempt route.');

            return;
        }

        // ⚠️ **A TABLE RATHER THAN PADDED `line()` CALLS, AND THAT IS A FACT
        // ABOUT THE FRAMEWORK RATHER THAN TASTE.** `Command::line()` goes
        // through `SymfonyStyle`, which collapses runs of whitespace, so a
        // `str_pad()` column reads perfectly in the source and arrives as one
        // space. Measured, not assumed.
        //
        // ⛔ **`set` / `NOT SET` IS THE WORD AND THE COLUMN IS THE SECOND
        // INDICATOR** — nothing here is carried by colour alone, which is this
        // codebase's rule on a screen and is no weaker in a console an operator
        // reads over ssh.
        $this->table(
            ['Endpoint', 'Verifies with', 'Material'],
            array_map(static fn (WebhookMaterialLine $line): array => [
                $line->uri,
                $line->isReady() ? 'set' : 'NOT SET',
                implode(', ', $line->isReady() ? $line->present : $line->missing),
            ], $lines),
        );

        $this->reportWhatIsMissing($lines);
        $this->reportUndeclared($census);
        $this->reportStrays($census);
    }

    /**
     * @param  list<WebhookMaterialLine>  $lines
     */
    private function reportWhatIsMissing(array $lines): void
    {
        $blind = array_values(array_filter($lines, static fn (WebhookMaterialLine $line): bool => ! $line->isReady()));

        if ($blind === []) {
            $this->line('Every endpoint holds its material.');

            // ⛔ **THE CAVEAT RIDES WITH THE HEALTHY LINE AND NOT ONLY WITH THE
            // BLIND ONE.** *Set* is not *correct* and is not *arriving*; a
            // clean report without these two clauses is the most misleading
            // output this command could produce.
            $this->line('That is not a delivery being accepted: a wrong or rotated value reads exactly like a '
                .'right one from here, and nothing in this application knows an inbound message was ever '
                .'supposed to arrive.');

            return;
        }

        $this->warn(count($blind).' of '.count($lines).' endpoints cannot check who is calling them, so every '
            .'delivery to them is refused. Nothing retries and nothing else reports it.');

        // ⛔ **THE CONSEQUENCE SENTENCE IS `CredentialManifest`'s AND IS NOT
        // RE-TYPED HERE** (8460). It is the same `degradation` line the Ops
        // board renders under *"While this is unset:"*, so the two surfaces
        // cannot come to say different things about one key. ⚠️ **The two
        // `config()`-only gates have no manifest row and therefore no sentence**
        // — the header above is what is true of them, and inventing a second
        // one here is what this comment exists to refuse.
        $manifest = CredentialManifest::credentials();

        // ⚠️ **ONCE PER KEY AND NOT ONCE PER ENDPOINT.** Three Infobip routes
        // share one secret, so the row-per-route above is right and printing
        // its consequence three times is not — a paragraph repeated verbatim
        // reads as three faults and is one paste.
        $said = [];

        foreach ($blind as $line) {
            foreach ($line->missing as $name) {
                if (! isset($manifest[$name]) || isset($said[$name])) {
                    continue;
                }

                $said[$name] = true;

                $this->line('  '.$name.': '.$manifest[$name]['degradation']);
            }
        }

        $this->line('Set them in Ops, Platform, Credentials — or, for a platform_mail.* row, in .env, '
            .'which is the authority for that sequence.');
    }

    /**
     * ⛔ **THE COMPLETENESS HALF, AND IT IS THE HALF THAT ROTS.** Every row above
     * being non-empty says nothing about whether every endpoint produced a row.
     * A ninth webhook verifying inline would simply be absent from the list and
     * the output would read healthy — the reassuring direction, which is the
     * direction these failures always take.
     */
    private function reportUndeclared(WebhookMaterialCensus $census): void
    {
        $undeclared = $census->undeclared();

        if ($undeclared === []) {
            return;
        }

        $this->warn('These webhook routes reach no verifier that says what it checks callers with, so '
            .'nothing above is a statement about them at all:');

        foreach ($undeclared as $uri) {
            $this->line('  '.$uri);
        }

        $this->line('A verifier declares App\Contracts\VerifiesWebhookSenders.');
    }

    /**
     * ⛔ **THE OTHER COMPLETENESS HALF, AND IT WAS COMPUTED ON EVERY DEPLOY AND
     * PRINTED NOWHERE** (12080). {@see WebhookMaterialCensus::strays()} shipped
     * with a test and no operator surface, while {@see self::reportUndeclared()}
     * — its sibling, whose docblock makes the same *"this is the half that
     * rots"* argument — got both. **An answer this application computes and does
     * not say is an answer nobody has**: with CI dead the only reader of that
     * assertion is a developer's own `git push`, so an operator on the running
     * install had no way to learn a stray exists.
     *
     * ⚠️ **THE TWO CONSEQUENCES ARE DIFFERENT AND BOTH ARE STATED**, because an
     * operator meeting one line here has to know what to go and look at: the
     * census never asked about that endpoint's material, **and** the CSRF
     * exemption list in `bootstrap/app.php` names one route at a time and
     * deliberately grants no prefix, so a route missing from it is refused
     * before its verifier ever runs.
     */
    private function reportStrays(WebhookMaterialCensus $census): void
    {
        $strays = $census->strays();

        if ($strays === []) {
            return;
        }

        $this->warn('These routes check their callers the way a webhook does and do not sit under '
            .WebhookMaterialCensus::PREFIX.', so nothing above is a statement about them:');

        foreach ($strays as $uri) {
            $this->line('  '.$uri);
        }

        $this->line('Two things to check for each one. Whether it holds the material it verifies '
            .'with, which nothing here has asked. And whether bootstrap/app.php exempts it from the '
            .'CSRF token — that list names one route at a time and grants no prefix, so a delivery '
            .'to a route missing from it is refused before the verifier ever runs.');
    }
}
