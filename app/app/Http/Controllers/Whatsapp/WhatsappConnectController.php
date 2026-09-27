<?php

declare(strict_types=1);

namespace App\Http\Controllers\Whatsapp;

use App\Exceptions\GbpRequestFailed;
use App\Exceptions\ZernioConnectionRefused;
use App\Http\Controllers\Controller;
use App\Modules\CWhatsapp\Actions\WhatsappConnectCompleteAction;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\URL;
use Masmerise\Toaster\Toaster;

final class WhatsappConnectController extends Controller
{
    private const array PROVIDER_PARAMETERS = [
        'connected', 'profileId', 'accountId', 'username', 'connect_token', 'error', 'platform', 'reason', 'dashboard_url',
    ];

    private const array ERROR_WORDS = [
        'one_whatsapp_per_profile' => 'This business already has a WhatsApp number connected. Disconnect it first.',
        'whatsapp_number_already_connected' => 'That number is already connected to another account.',
        'whatsapp_number_pinned_to_profile' => 'That number belongs to another Zernio profile.',
        'connection_cancelled' => 'WhatsApp was not connected.',
        'session_expired' => 'The connection window expired. Try again.',
        'payment_required' => 'Zernio needs a payment method before it can connect a number.',
    ];

    public static function middleware(): array
    {
        return [
            'auth',
            ValidateSignature::absolute(self::PROVIDER_PARAMETERS),
        ];
    }

    public static function callbackUrlFor(string $profileRef): string
    {
        return URL::temporarySignedRoute(
            'whatsapp.connect.callback',
            now()->addMinutes(30),
            ['profile' => $profileRef]
        );
    }

    public function __invoke(Request $request, WhatsappConnectCompleteAction $action): RedirectResponse
    {
        abort_if(Tenancy::id() === null, 403);
        abort_unless($request->user()->role->canManageConnections(), 403);

        $error = $request->string('error')->trim()->value();
        if ($error !== '') {
            $message = self::ERROR_WORDS[$error] ?? 'WhatsApp could not be connected.';

            if ($error === 'connection_cancelled') {
                Toaster::info($message);
            } else {
                Toaster::error($message);
            }

            $pending = WhatsappConnection::where('status', 'pending')->first();
            if ($pending !== null) {
                $pending->last_error = $error;
                $pending->save();
            }

            return redirect()->route('c-whatsapp.thread');
        }

        $accountId = $request->string('accountId')->trim()->value();
        if ($accountId === '') {
            Toaster::info('WhatsApp was not connected.');

            return redirect()->route('c-whatsapp.thread');
        }

        try {
            $action->handle(
                accountRef: $accountId,
                expectedProfileRef: $request->string('profile')->trim()->value(),
                profileRef: $request->string('profileId')->trim()->value() ?: null,
                label: $request->string('username')->trim()->value() ?: null,
                actor: 'user:'.(int) $request->user()?->getAuthIdentifier(),
            );
        } catch (ZernioConnectionRefused $e) {
            Toaster::error($e->getMessage());

            return redirect()->route('c-whatsapp.thread');
        } catch (GbpRequestFailed) {
            Toaster::error('We could not confirm that connection. Please try again shortly.');

            return redirect()->route('c-whatsapp.thread');
        }

        Toaster::success('WhatsApp is connected.');

        return redirect()->route('c-whatsapp.thread');
    }
}
