<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\CredentialEnvironment;
use App\Services\Config\CredentialSource;
use App\Services\Config\CredentialStore;
use App\Services\Consent\IdentifierHashEpochs;
use App\Services\Mail\MailDrivers;
use App\Support\Admin\AdminAccess;
use App\Support\CredentialManifest;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Ops → Platform → Credentials — the Credentials Manager's screen (`38` Part 1).
 *
 * `38` Part 1: "per vendor — masked value (last-4 visible), **Set/Rotate**
 * (paste, never displayed again), … last-rotated, last-used, environment badge
 * (live/test)."
 *
 * ## The confirm comes before the paste, not after
 *
 * ⚠️ **THIS IS A LIVEWIRE CONSTRAINT WITH A SECURITY CONSEQUENCE, AND THE
 * OBVIOUS ORDER IS THE WRONG ONE.** `38` asks for "set/rotate = super_admin +
 * one confirm", which reads as: paste the key, press Save, confirm. That order
 * requires the pasted secret to survive a round trip — and a Livewire public
 * property lives in the component snapshot embedded in the page, so the vendor
 * key would sit in the DOM, in plaintext, for as long as the confirmation dialog
 * was open.
 *
 * So the order is inverted: pressing **Rotate** opens the confirmation *first*,
 * stating what is about to happen, and the field to paste into appears inside
 * it. The value reaches the server once, in the request that writes it, and
 * `save()` clears the property before the response is rendered — so the returned
 * snapshot never contains it either. `wire:model` is deferred by default in
 * Livewire 3+, which is what makes that true rather than approximately true.
 *
 * A test asserts the rendered HTML after a save contains no part of the pasted
 * value.
 *
 * ## What is not built, and why
 *
 * ⚠️ **NO "TEST CONNECTION" BUTTON**, which `38` Part 1 asks for ("runs the
 * vendor's health probe, shows plain pass/fail"). Every probe crosses a vendor
 * boundary: a Places call is billed at 9.2¢ against a metered daily budget
 * (decisions 253–257), and an AI probe spends tokens against a per-tenant cap
 * that has no tenant on this screen. CLAUDE.md routes vendor work to the
 * `integration-builder` agent, which is required to read live vendor docs first,
 * precisely so that a probe hits the endpoint the vendor documents today rather
 * than the one somebody remembers. Built from memory, a green light that proves
 * nothing is worse than no light.
 *
 * The half that needs no vendor is built and is the load-bearing half:
 * {@see CredentialSource} answers "exactly which key is
 * absent", which is the sentence `38` writes the health board for, and the
 * manifest's `degradation` line says what stops working when one is.
 *
 * ⚠️ **NO SECOND ROLE GATE.** `38` Part 1 splits `support_agent` (status lights
 * only) from `super_admin` (set/rotate). `UserRole::isPlatformStaff()` is
 * `SuperAdmin` alone today, and `28` §9.1's internal-staff roles — `ops_admin`,
 * `billing_admin`, `support_lead`, `support_agent`, `cs_readonly` — do not
 * exist. A second gate would therefore be a check that no user in the system can
 * fail, which is decision 256's vacuous lint wearing an authorization costume.
 * The split lands with those roles; the screen already asks the one gate that
 * exists, on mount and on every action.
 */
final class Credentials extends Component
{
    /**
     * The key whose rotation is being confirmed, or empty when none is.
     *
     * Locked: the client may not move it. It only ever selects among keys the
     * manifest declares — the store refuses anything else — so tampering is
     * harmless, and locking it says that is by design rather than by luck.
     */
    #[Locked]
    public string $rotating = '';

    /**
     * The key whose clearing is being confirmed.
     */
    #[Locked]
    public string $clearing = '';

    /**
     * The pasted secret, alive for exactly one request.
     *
     * ⚠️ NEVER POPULATED FROM THE STORE, IN ANY BRANCH. `38`: "paste, never
     * displayed again". There is no code path in this application that reads a
     * credential back out to a screen, and this property is the only one that
     * could become one.
     */
    public string $draft = '';

    /**
     * Which account the pasted key opens. Declared, never inferred — see
     * {@see CredentialEnvironment}.
     */
    public string $environment = 'live';

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Open the confirmation for a rotation. No value is involved yet.
     */
    public function confirmRotate(string $key): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->rotating = $key;
        $this->clearing = '';
        $this->draft = '';
        $this->environment = 'live';
    }

    public function confirmClear(string $key): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->clearing = $key;
        $this->rotating = '';
        $this->draft = '';
    }

    public function cancel(): void
    {
        $this->rotating = '';
        $this->clearing = '';
        $this->draft = '';
    }

    /**
     * Write the pasted credential, then forget it.
     *
     * `cancel()` runs before the toast and before the render, which is what
     * keeps the secret out of the response snapshot. Every early return goes
     * through a path that also clears the draft — an error branch that left the
     * value in place would put it in the snapshot for as long as the operator
     * left the screen open, which is precisely the case where something has just
     * gone wrong and they are reading the message.
     */
    public function save(CredentialStore $store): void
    {
        $this->authorize(AdminAccess::GATE);

        $key = $this->rotating;
        $value = $this->draft;

        if ($key === '') {
            return;
        }

        $environment = CredentialEnvironment::tryFrom($this->environment);

        if ($environment === null) {
            $this->cancel();
            Toaster::error('Choose whether this key opens the live or the test account.');

            return;
        }

        try {
            $store->set($key, $value, $this->actor(), $environment);
        } catch (InvalidArgumentException $e) {
            $this->cancel();
            Toaster::error($e->getMessage());

            return;
        }

        $this->cancel();

        // Outcome language (`22`), and no fragment of the value — not even the
        // last four. A toast is not the record; the change log is.
        Toaster::success('Credential saved');
    }

    public function clear(CredentialStore $store): void
    {
        $this->authorize(AdminAccess::GATE);

        $key = $this->clearing;

        if ($key === '') {
            return;
        }

        try {
            $store->clear($key, $this->actor());
        } catch (InvalidArgumentException $e) {
            $this->cancel();
            Toaster::error($e->getMessage());

            return;
        }

        $this->cancel();

        Toaster::success('Credential cleared');
    }

    /**
     * ⛔ **`IdentifierHashEpochs` IS READ HERE AND NEVER OBSERVED** — decision
     * 9640, and `Architecture/ConsentTest`'s *only the consent write paths
     * observe an identifier hash epoch* is what keeps it that way. An epoch is
     * recorded when a durable hash is **written** and never when one is read;
     * a screen that filed what it found would, on the first send after a
     * rotation, record the new key as the epoch and conclude everything was
     * current — the fail-open the table exists to remove, rebuilt inside a
     * render. Three read-only calls, and nothing else.
     *
     * ⚠️ **THE SERVICE IS `scoped()`, SO THE THREE CALLS ARE ONE QUERY.**
     * `status()` memoises the live fingerprints per resolved instance; the
     * headline, the predicate and the sentence all ask it.
     */
    public function render(CredentialStore $store, MailDrivers $drivers, IdentifierHashEpochs $epochs): View
    {
        return view('livewire.admin.credentials', [
            'board' => $store->board(),
            'environments' => CredentialEnvironment::cases(),
            'history' => $this->rotating === '' ? [] : $store->historyFor($this->rotating, 5),
            // ⛔ **9374's OWED COMPANION LINE** (9444). An operator standing at
            // this board reads it as *the list*, and on an SMTP transport the
            // credential that decides whether any email leaves — a sign-in link
            // included — is not on it and cannot be. ⚠️ **The same derivation
            // `Admin\MailSending` uses, called rather than re-read**: a second
            // reading of `mail.mailers.*` here would be two copies of one rule,
            // agreeing until one of them moved. **`MailDrivers::active()`
            // rather than `config('mail.default')` for the same reason.**
            'mailerCredential' => CredentialManifest::mailerCredential($drivers->active()),

            // ⛔ **9449(c), AND IT IS NOT A CREDENTIALS FACT.** On an `APP_KEY`
            // rotation `ConsentService::decide()` refuses every send on the
            // platform, for every tenant and on every channel. This screen used
            // to be able only to point at `php artisan consent:hash-epoch`,
            // because stating it would have been a claim about another feature
            // that nothing here could falsify (8861). **The epoch check is that
            // falsifier**, and reading a method is not re-typing a rule.
            //
            // ⛔ **ONE VALUE AND NOT THREE** (9645). The first draft passed the
            // predicate and the two sentences separately and a mutation proved
            // they could disagree — a headline saying every send was refused
            // above a panel withholding the remedy, with every test green.
            'registers' => $epochs->readability(),
        ]);
    }

    /**
     * Who made the change, for `rotated_by` and the change log.
     *
     * An actor label rather than a user id, the same choice `PlatformSettings`
     * and `audit_log.actor` make: a console command and a staff member both
     * write here and only one of them has a user id.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
