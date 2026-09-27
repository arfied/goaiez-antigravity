<?php

declare(strict_types=1);

namespace App\Http\Controllers\Social;

use App\Exceptions\GbpRequestFailed;
use App\Exceptions\ZernioConnectionRefused;
use App\Http\Controllers\Controller;
use App\Modules\X182\Domain\SocialConnections;
use App\Modules\X182\Models\SocialAccount;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\URL;
use Masmerise\Toaster\Toaster;

final class SocialConnectController extends Controller
{
    private const array PROVIDER_PARAMETERS = [
        'connected', 'profileId', 'accountId', 'username', 'error', 'error_description', 'platform', 'state', 'code', 'connect_token', 'reason', 'dashboard_url',
    ];

    public static function middleware(): array
    {
        return [
            'auth',
            ValidateSignature::absolute(self::PROVIDER_PARAMETERS),
        ];
    }

    public static function callbackUrlFor(string $platform, string $profileRef): string
    {
        return URL::temporarySignedRoute(
            'social.connect.callback',
            now()->addMinutes(30),
            ['platform' => $platform, 'profile' => $profileRef]
        );
    }

    public function __invoke(Request $request, SocialConnections $c, string $platform): RedirectResponse
    {
        abort_if(Tenancy::id() === null, 403);
        abort_unless($request->user()->role->canManageConnections(), 403);
        abort_unless(in_array($platform, ['facebook', 'instagram'], true), 404);

        $error = $request->string('error')->trim()->value();
        $errorDescription = $request->string('error_description')->trim()->value();
        $connected = $request->string('connected')->trim()->value();

        if ($error !== '' || $connected === 'false') {
            $err = $errorDescription ?: $error;
            Toaster::error(ucfirst($platform).' was not connected — '.$err.'.');

            $pending = SocialAccount::where('status', 'pending')->where('platform', $platform)->first();
            if ($pending !== null) {
                $pending->last_error = $error;
                $pending->save();
            }

            return redirect()->route('x-182.connected-accounts');
        }

        $accountId = $request->string('accountId')->trim()->value();
        $profileId = $request->string('profileId')->trim()->value();
        $username = $request->string('username')->trim()->value();
        $actor = 'user:'.(int) $request->user()?->getAuthIdentifier();

        try {
            $c->complete(
                $platform,
                $accountId ?: null,
                $request->string('profile')->trim()->value(),
                $profileId ?: null,
                $username ?: null,
                $actor
            );
        } catch (ZernioConnectionRefused $e) {
            Toaster::error($e->getMessage());

            return redirect()->route('x-182.connected-accounts');
        } catch (GbpRequestFailed) {
            Toaster::error('We could not confirm that connection. Please try again shortly.');

            return redirect()->route('x-182.connected-accounts');
        }

        Toaster::success(ucfirst($platform).' is connected.');

        return redirect()->route('x-182.connected-accounts');
    }
}
