<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Exceptions\StaffChangeRefused;
use App\Models\User;
use App\Services\Staff\StaffDirectory;
use App\Support\Admin\AdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Ops → Platform → *"internal users & roles"* (`28` §9.2), and §9.1's RBAC given
 * the writer it never had.
 *
 * ⚠️ **BEFORE THIS SCREEN, NOTHING IN `app/` COULD PUT A ROLE ON A USER**
 * (decision 740). Slice 1 shipped `28` §9.1's five internal roles, the support
 * gate, both impersonation modes and their build-failing tests; slice 2 shipped
 * the audit explorer behind `AdminAccess::GATE`; slice 3 made two-factor
 * mandatory for internal accounts. All three read `users.role`, and the only
 * value that column had ever held was the migration's `default('owner')` — so
 * the entire Ops Console was openable by nobody on a fresh install and the only
 * way in was editing the database of a live tenant system.
 *
 * ## Why this is hand-rolled rather than composed from `AdminTable`
 *
 * Decision 632, applied a second time and reaching the same answer: the shell's
 * `rows()` opens with `Tenancy::idOrFail()`, which is what makes reuse *safe* on
 * a tenant-scoped Ops screen and is exactly what a platform-scoped one cannot
 * satisfy. `users` is not tenant-owned — a user exists before a business does —
 * so there is no tenant to name, and asking the trait to tolerate one would
 * remove that fail-closed line from all six screens that have it.
 *
 * ## `super_admin` alone, on decision 623's reasoning
 *
 * `28` §9.2 places *"internal users & roles"* in the **Platform** section, so it
 * takes `AdminAccess::GATE` and not `SupportAccess::GATE`: a `support_lead`
 * cannot open it, and `28` §9.1 gives even `ops_admin` "no platform-global
 * settings". A screen that hands out standing access to every customer account
 * is the last one to widen on a reading the document did not make.
 *
 * ## What it does not do
 *
 * ⚠️ **No password field, in either form** (747) — an operator minting somebody
 * else's first credential has to transmit it, and the account it opens holds
 * standing access to every tenant. New accounts sign in by link and meet slice
 * 3's enrolment screen before they reach anything.
 *
 * ⚠️ **No delete.** `impersonation_sessions.agent_id` and
 * `staff_events.subject_user_id` are both `restrictOnDelete`, so a staff member
 * who has ever touched a customer's account is undeletable by design — the
 * record of the access must outlive the person's employment.
 * `UserRole::None` is what revocation is, and it keeps the history attached to a
 * name rather than to an orphaned id.
 */
final class InternalUsers extends Component
{
    /**
     * The account whose role is being changed, or 0 when none is.
     *
     * Locked: the client may not move it. Every action re-reads the user from
     * the database and the service re-checks every rule, so tampering changes
     * nothing — locking says that is by design rather than by luck.
     */
    #[Locked]
    public int $editing = 0;

    public string $newRole = '';

    public string $changeReason = '';

    public bool $creating = false;

    public string $name = '';

    public string $email = '';

    public string $createRole = '';

    public string $createReason = '';

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * The roles this screen may assign.
     *
     * ⚠️ Derived from `isTenantRole()` rather than listed, so a role added to
     * the enum appears here without anybody remembering to. The service refuses
     * a tenant role regardless — this list is the affordance, not the guard, and
     * decision 391's rule applies: a control that is not rendered is still
     * callable.
     *
     * @return array<int, UserRole>
     */
    public function assignableRoles(): array
    {
        return collect(UserRole::cases())
            ->reject(fn (UserRole $role): bool => $role->isTenantRole())
            ->values()
            ->all();
    }

    public function startChange(int $userId): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->editing = $userId;
        $this->creating = false;
        $this->newRole = '';
        $this->changeReason = '';
    }

    public function cancel(): void
    {
        $this->editing = 0;
        $this->creating = false;
        $this->newRole = '';
        $this->changeReason = '';
        $this->resetCreateForm();
    }

    public function startCreate(): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->creating = true;
        $this->editing = 0;
        $this->resetCreateForm();
    }

    /**
     * ⚠️ Authorize before validate, on `PhiTenants`' rule: `validate()` would
     * otherwise answer an ungated caller with a field list, which tells them the
     * action exists and what it wants.
     */
    public function changeRole(StaffDirectory $staff): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->validate([
            'newRole' => ['required', Rule::in($this->assignableValues())],
            // Bounded because of where it lands: `staff_events` is append-only
            // forever, so an unbounded box lets an operator paste a document
            // into a table nothing can edit. The service and a CHECK both
            // enforce the floor; this enforces it in the form, where the
            // operator can see which box is wrong.
            'changeReason' => ['required', 'string', 'min:10', 'max:500'],
        ], attributes: [
            'newRole' => 'role',
            'changeReason' => 'reason',
        ]);

        $subject = User::query()->find($this->editing);

        if (! $subject instanceof User) {
            Toaster::error('That account no longer exists.');

            $this->cancel();

            return;
        }

        $actor = $this->actor();

        try {
            $staff->assign(
                $subject,
                UserRole::from($this->newRole),
                $this->changeReason,
                $actor,
            );
        } catch (StaffChangeRefused $e) {
            Toaster::error($e->getMessage());

            return;
        }

        Toaster::success($subject->name.' is now '.UserRole::from($this->newRole)->label().'.');

        $this->cancel();
    }

    public function createAccount(StaffDirectory $staff): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'createRole' => ['required', Rule::in($this->assignableValues())],
            'createReason' => ['required', 'string', 'min:10', 'max:500'],
        ], attributes: [
            'createRole' => 'role',
            'createReason' => 'reason',
        ]);

        try {
            $created = $staff->create(
                name: trim($this->name),
                email: mb_strtolower(trim($this->email)),
                role: UserRole::from($this->createRole),
                reason: $this->createReason,
                actor: $this->actor(),
            );
        } catch (StaffChangeRefused $e) {
            Toaster::error($e->getMessage());

            return;
        }

        Toaster::success(
            $created->name.' can now sign in with a link to '.$created->email
            .' and will be asked to set up two-factor authentication.'
        );

        $this->cancel();
    }

    public function render(StaffDirectory $staff): View
    {
        return view('livewire.admin.internal-users', [
            'staff' => $staff->staff(),
            'roles' => $this->assignableRoles(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function assignableValues(): array
    {
        return array_map(
            fn (UserRole $role): string => $role->value,
            $this->assignableRoles(),
        );
    }

    /**
     * ⚠️ The signed-in operator, never a property.
     *
     * The one thing on this screen that must not be client-supplied: an actor
     * taken from the request is an actor the request can choose, and the actor
     * is what the append-only record attributes the grant to.
     */
    private function actor(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function resetCreateForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->createRole = '';
        $this->createReason = '';
    }
}
