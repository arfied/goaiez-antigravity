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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class TeamInvites
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function invite(int $businessId, string $email, string $name, User $actor): array
    {
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
        } else {
            $user = new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make(Str::random(64));
            $user->role = UserRole::Staff;
            $user->save();
        }

        $membership = BusinessMembership::create([
            'business_id' => $businessId,
            'user_id' => $user->id,
            'role' => 'staff',
            'invited_at' => now(),
        ]);

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

        $emailDomain = explode('@', $user->email)[1] ?? '';

        app(AuditService::class)->record(
            'team.invited',
            'user:'.$actor->id,
            $membership,
            ['email_domain' => $emailDomain]
        );

        return [
            'ok' => true,
            'message' => 'Invited '.$name.'. We emailed '.$email.' a link to set their password; they can sign in once they do.',
        ];
    }
}
