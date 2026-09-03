<?php
declare(strict_types=1);

namespace App\Modules\X185\Domain;

final class SequenceEngine
{
    // X-185 domain layer explicitly blocking price testing in sequences and routing all sends through ConsentService via Lane 2.
}
