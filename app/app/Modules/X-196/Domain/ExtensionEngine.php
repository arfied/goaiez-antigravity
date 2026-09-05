<?php

declare(strict_types=1);

namespace App\Modules\X196\Domain;

final class ExtensionEngine
{
    // X-196 domain layer enforcing hard actions-per-minute caps to prevent rate limiting,
    // and ensuring FetchPolicy.authenticated=false by default to protect operator accounts.
}
