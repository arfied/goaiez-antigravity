<?php

declare(strict_types=1);

namespace App\Services\Team;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Notifications\TeamInviteEmail;
use App\Services\AuditService;
use App\Services\Mail\PlatformMailer;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class TeamInvites
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function invite(int $businessId, string $email, string $name, User $actor, UserRole $role = UserRole::Staff): array
    {
        if ($role !== UserRole::Staff && $role !== UserRole::Manager) {
            return [
                'ok' => false,
                'message' => 'Choose Staff or Manager.',
            ];
        }

        $email = trim(strtolower($email));
        $user = User::where('email', $email)->first();
        if ($user !== null) {
            $previousUserId = Tenancy::userId();
            Tenancy::setUser($user->id);

            $ownsOne = Business::withoutGlobalScopes()
                ->where('owner_user_id', $user->id)
                ->exists();

            $hasMembership = BusinessMembership::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->exists();

            if ($previousUserId !== null) {
                Tenancy::setUser($previousUserId);
            } else {
                Tenancy::forgetUser();
            }

            if ($ownsOne || $hasMembership) {
                return [
                    'ok' => false,
                    'message' => 'That person already belongs to a business, so they cannot join yours.',
                ];
            }

            $user->role = $role;
            $user->save();
        } else {
            $user = new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make(Str::random(64));
            $user->role = $role;
            $user->save();
        }

        $membership = BusinessMembership::create([
            'business_id' => $businessId,
            'user_id' => $user->id,
            'role' => $role->value,
            'invited_at' => now(),
        ]);

        $this->sendInvite($user, $businessId);

        $emailDomain = explode('@', $user->email)[1] ?? '';

        app(AuditService::class)->record(
            'team.invited',
            'user:'.$actor->id,
            $membership,
            ['email_domain' => $emailDomain, 'role' => $role->value]
        );

        return [
            'ok' => true,
            'message' => 'Invited '.$name.'. We emailed '.$email.' a link to set their password; they can sign in once they do.',
        ];
    }

    private function sendInvite(User $user, int $businessId): void
    {
        $minutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        $token = Password::broker()->createToken($user);

        $businessName = Business::find($businessId)->name ?? 'The Business';

        app(PlatformMailer::class)->send(
            $user->email,
            new TeamInviteEmail(
                $businessName,
                url(route('password.reset', [
                    'token' => $token,
                    'email' => $user->email,
                ], false)),
                $minutes
            )
        );
    }

    public function members(): Collection
    {
        $memberships = BusinessMembership::orderByDesc('id')->get();
        $userIds = $memberships->pluck('user_id')->unique()->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return $memberships->map(function (BusinessMembership $membership) use ($users) {
            $status = 'active';
            if ($membership->revoked_at !== null) {
                $status = 'revoked';
            } elseif ($membership->accepted_at === null) {
                $status = 'pending';
            }

            $user = $users->get($membership->user_id);

            return [
                'membership_id' => $membership->id,
                'name' => $user->name ?? 'Unknown',
                'email' => $user->email ?? 'unknown',
                'status' => $status,
                'role' => $user->role->value ?? 'staff',
            ];
        });
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function resend(int $membershipId, User $actor): array
    {
        $membership = BusinessMembership::findOrFail($membershipId);
        $user = User::findOrFail($membership->user_id);

        if ($membership->revoked_at !== null || $membership->accepted_at !== null) {
            return [
                'ok' => false,
                'message' => 'That invite is no longer pending.',
            ];
        }

        $this->sendInvite($user, $membership->business_id);

        app(AuditService::class)->record(
            'team.invite_resent',
            'user:'.$actor->id,
            $membership,
            []
        );

        return [
            'ok' => true,
            'message' => 'Sent '.$user->email.' a new link.',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function revoke(int $membershipId, User $actor): array
    {
        $membership = BusinessMembership::findOrFail($membershipId);
        $user = User::findOrFail($membership->user_id);

        if ($membership->user_id === $actor->id) {
            return [
                'ok' => false,
                'message' => 'You cannot remove your own access.',
            ];
        }

        if ($membership->revoked_at !== null) {
            return [
                'ok' => false,
                'message' => 'That access is already removed.',
            ];
        }

        $membership->update(['revoked_at' => now()]);

        DB::table('sessions')
            ->where('user_id', $membership->user_id)
            ->delete();

        $user->update(['remember_token' => null]);

        app(AuditService::class)->record(
            'team.revoked',
            'user:'.$actor->id,
            $membership,
            []
        );

        return [
            'ok' => true,
            'message' => $user->name.' can no longer sign in to your business.',
        ];
    }
}
