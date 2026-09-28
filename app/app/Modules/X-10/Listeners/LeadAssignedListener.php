<?php

declare(strict_types=1);

namespace App\Modules\X10\Listeners;

use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X10\Mail\LeadAssignedNotification;
use App\Modules\X121\Actions\EntityReadAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LeadAssignedListener
{
    public function handle(LeadAssigned $event): void
    {
        $email = DB::table('users')->where('id', $event->staffId)->value('email');
        if (! is_string($email) || $email === '') {
            return;
        }
        $person = app(EntityReadAction::class)->handle('people', $event->leadId, $event->businessId) ?? [];
        $name = trim(((string) ($person['first_name'] ?? '')).' '.((string) ($person['last_name'] ?? '')));
        Mail::to($email)->send(new LeadAssignedNotification(
            businessId: $event->businessId,
            leadId: $event->leadId,
            leadName: $name !== '' ? $name : 'Lead #'.$event->leadId,
            leadPhone: isset($person['phone']) ? (string) $person['phone'] : null,
            leadEmail: isset($person['email']) ? (string) $person['email'] : null,
            reason: $event->reason,
        ));
    }
}
