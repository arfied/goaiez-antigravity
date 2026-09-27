<?php

declare(strict_types=1);

namespace App\Modules\X01\Listeners;

use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X01\Events\ContactCreated;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X155\Events\FormCaptured;
use Illuminate\Support\Facades\Event;

final class FormCapturedListener
{
    public function __construct(
        private readonly UnifiedInboxManager $inboxManager
    ) {}

    public function handle(FormCaptured $event): void
    {
        $person = app(EntityReadAction::class)->handle('people', $event->personId, $event->businessId);
        if ($person === null) {
            return;
        }

        $phone = isset($person['phone']) && is_string($person['phone']) && trim($person['phone']) !== '' ? trim($person['phone']) : null;
        $email = isset($person['email']) && is_string($person['email']) && trim($person['email']) !== '' ? trim($person['email']) : null;
        $identifier = $phone ?? $email;
        if ($identifier === null) {
            return; // nothing to reach this person by
        }

        $name = trim(((string) ($person['first_name'] ?? '')).' '.((string) ($person['last_name'] ?? '')));
        if ($name === '') {
            $name = 'Website visitor';
        }

        $this->inboxManager->ingestMessage(
            $event->businessId,
            'form',
            $identifier,
            $name,
            'Website form submission #'.$event->submissionId
        );

        Event::dispatch(new ContactCreated(
            businessId: $event->businessId,
            personId: $event->personId,
            name: $name,
            phone: $phone,
            email: $email
        ));
    }
}
