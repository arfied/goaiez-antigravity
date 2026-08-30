<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X01\Events\ContactCreated;
use App\Modules\X121\Models\Person;
use Illuminate\Support\Facades\Event;

final class ContactCreateAction
{
    public function handle(int $businessId, string $name, ?string $phone = null, ?string $email = null): Person
    {
        $person = Person::create([
            'business_id' => $businessId,
            'first_name' => $name,
            'last_name' => '',
            'phone' => $phone,
            'email' => $email,
        ]);

        Event::dispatch(new ContactCreated(
            businessId: $businessId,
            personId: $person->id,
            name: $name,
            phone: $phone,
            email: $email
        ));

        return $person;
    }
}
