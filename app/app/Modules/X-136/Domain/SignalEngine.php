<?php
declare(strict_types=1);

namespace App\Modules\X136\Domain;

final class SignalEngine
{
    public function enforceG3_15(): void { throw new \DomainException('[G3-15] a signal'); }
    public function enforceG3_27(): void { throw new \DomainException('[G3-27] an alert, never a send (P-068)'); }
    public function enforceG3_28(): void { throw new \DomainException('[G3-28] a signal'); }
    public function enforceG3_33(): void { throw new \DomainException('[G3-33] a 30-day re-scan raising a signal'); }
    public function enforceG3_42(): void { throw new \DomainException('[G3-42] a signal; the alert names an action (X-111)'); }
    public function enforceG3_56(): void { throw new \DomainException('[G3-56] enrichment from a job posting — no scrape needed'); }
    public function enforceG4_07(): void { throw new \DomainException('[G4-07] a new-registration signal; a signal never mints a SendPermit (P-068) — the send is X-105\'s on Lane 3'); }
    public function enforceG12_35(): void { throw new \DomainException('[G12-35] a hiring signal, never a permit (P-068)'); }
    public function enforceG19_06(): void { throw new \DomainException('[G19-06] a hiring signal, never a permit (P-068)'); }
}
