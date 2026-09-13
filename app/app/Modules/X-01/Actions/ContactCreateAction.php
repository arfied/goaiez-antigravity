<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X01\Events\ContactCreated;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X121\Actions\PersonLookupAction;
use Illuminate\Support\Facades\Event;

final class ContactCreateAction
{
    public function handle(int $businessId, string $name, ?string $phone = null, ?string $email = null): array
    {
        $personId = app(PersonLookupAction::class)->create($businessId, [
            'first_name' => $name,
            'last_name' => '',
            'phone' => $phone,
            'email' => $email,
        ]);

        Event::dispatch(new ContactCreated(
            businessId: $businessId,
            personId: $personId,
            name: $name,
            phone: $phone,
            email: $email
        ));

        return app(EntityReadAction::class)->handle('people', $personId, $businessId);
    }
}
