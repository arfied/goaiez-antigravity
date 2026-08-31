<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Exceptions\StaffChangeRefused;
use App\Models\User;
use App\Services\Staff\StaffDirectory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Give somebody an internal role from the command line — `28` §9.1.
 *
 * ⚠️ **THE IGNITION, AND THE SLICE DOES NOT WORK WITHOUT IT** (decision 748).
 * `Livewire\Admin\InternalUsers` is behind `AdminAccess::GATE`, which is
 * `super_admin` alone. On a fresh install **no account holds any internal role
 * at all** — the column's default is `owner` — so the screen that grants the
 * first one cannot be opened by anybody. This is the only door that is not
 * behind the door it opens.
 *
 * It is also what remains if decision 744's refusal is ever wrong about
 * something: the last super admin cannot be demoted through the screen, and a
 * platform that somehow ends up with none has shell access and nothing else.
 *
 * **No gate here, and that is what a console command is.** Running it needs a
 * shell on the production box and the application's own credentials; anybody
 * with those already has `psql`, which is what this command exists to stop being
 * the answer. Every grant it makes is recorded with actor `console`, so the
 * trail distinguishes it from a change somebody made on a screen.
 */
#[Signature('staff:grant
    {email : The account to grant it to. Created if no user has this address}
    {role : Internal role — super_admin, ops_admin, billing_admin, support_lead, support_agent, cs_readonly, or none to revoke}
    {reason : Why, in a sentence. Recorded against the change}
    {--name= : Display name, used only when the account is being created}')]
#[Description('Grant, change or revoke an internal role')]
final class GrantStaffRole extends Command
{
    public function handle(StaffDirectory $staff): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $reason = (string) $this->argument('reason');

        $role = UserRole::tryFrom((string) $this->argument('role'));

        if (! $role instanceof UserRole) {
            $this->error('Unknown role. Internal roles are: '.$this->internalRoleList().'.');

            return self::FAILURE;
        }

        // ⚠️ Case-insensitively, because this table holds both spellings.
        // `MagicLinkService` and the SSO callback fold an address on the way in;
        // Fortify's `CreateNewUser` does not. A `where('email', $lowercased)`
        // would miss a mixed-case account, fall through to the create branch,
        // and die on the unique index with a SQLSTATE instead of moving the
        // role the operator asked to move.
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        try {
            if ($user instanceof User) {
                $before = $user->role;

                $staff->assignFromConsole($user, $role, $reason);

                $this->info($user->name.' moved from '.$before->label().' to '.$role->label().'.');

                return self::SUCCESS;
            }

            $name = $this->option('name');

            $created = $staff->createFromConsole(
                name: is_string($name) && trim($name) !== '' ? trim($name) : $email,
                email: $email,
                role: $role,
                reason: $reason,
            );

            $this->info('Created '.$created->email.' as '.$role->label().'.');

            // Said out loud because a new account with no password reads as
            // half-created to whoever runs this, and the next thing they would
            // do is look for a way to set one — which is the thing decision 747
            // is about.
            $this->line(
                'No password was set. They sign in with "Email me a sign-in link" and will be '
                .'asked to set up two-factor authentication before they reach anything.'
            );

            return self::SUCCESS;
        } catch (StaffChangeRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function internalRoleList(): string
    {
        return collect(UserRole::cases())
            ->reject(fn (UserRole $role): bool => $role->isTenantRole())
            ->map(fn (UserRole $role): string => $role->value)
            ->implode(', ');
    }
}
