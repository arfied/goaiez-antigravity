<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\BusinessMembership;
use App\Services\AuditService;
use App\Support\Tenancy;
use Illuminate\Auth\Events\PasswordReset;

final class ActivateMembershipOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        Tenancy::setUser($event->user->id);

        $membership = BusinessMembership::withoutGlobalScopes()
            ->where('user_id', $event->user->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->first();

        if ($membership !== null) {
            Tenancy::actingAs($membership->business_id, function () use ($membership, $event) {
                $membership->forceFill(['accepted_at' => now()])->save();
                
                if ($event->user->email_verified_at === null) {
                    $event->user->forceFill(['email_verified_at' => now()])->save();
                }

                app(AuditService::class)->record('team.joined', 'user:'.$event->user->id, $membership);
            });
        }

        Tenancy::forgetAll();
    }
}
