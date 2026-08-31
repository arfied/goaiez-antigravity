<?php

declare(strict_types=1);

namespace App\Services\Ops;

/**
 * Both push channels, asked the one question a settings screen cannot answer.
 *
 * ⚠️ **`set` IS NOT `reachable` AND THIS IS THE INSTRUMENT FOR THE GAP**
 * (7244). `ops:alert-channels` reads the two registry rows and can say only
 * that they are filled in; a typo'd address, a mailbox that rejects everything,
 * a number that changed hands, `SMS_DRIVER` still on `log` and `mail.default`
 * still on `log` all read as *set*. The second half of that command narrows it
 * by counting alerts that reached nobody — **which needs an alert to have been
 * raised**, and on a platform where nothing has broken yet there are none.
 *
 * ⛔ **AND THE ONLY WAY TO PRODUCE ONE WAS TO FAKE AN INCIDENT.**
 * {@see OperatorAlerts::raise()} writes its row first and unconditionally, so
 * borrowing a real `OperatorAlertKind` to test a channel left a record of
 * something that never happened — on the board's *still
 * ringing* section for a day, at the top of its severity band ahead of a real
 * incident, in the kind filter, and **inside `ops:alert-channels`' own
 * undelivered count**, where a test alert that failed became indistinguishable
 * from a real incident nobody was told about. A probe writes no row.
 *
 * ⛔ **THIS SAID "`operator_alerts` HAS NO DELETE PATH ANYWHERE IN THIS
 * APPLICATION" UNTIL 2026-08-22 — CORRECTED** (7520–7539).
 * {@see OperatorAlerts::prune()} is that path, at
 * {@see OperatorAlerts::RETENTION_DAYS}. ⚠️ **Not one clause of the argument
 * above moves**: every cost listed is paid in the first month, so a fabricated
 * row expiring in a year is the same defect with a date on it.
 */
final readonly class ChannelProbe
{
    public function __construct(
        public ChannelProbeResult $email,
        public ChannelProbeResult $sms,
    ) {}

    /**
     * @return list<ChannelProbeResult>
     */
    public function results(): array
    {
        return [$this->email, $this->sms];
    }

    /**
     * At least one channel took the message.
     */
    public function reachedSomebody(): bool
    {
        foreach ($this->results() as $result) {
            if ($result->handedOver) {
                return true;
            }
        }

        return false;
    }

    /**
     * A channel somebody configured did not carry it.
     */
    public function anythingBroken(): bool
    {
        foreach ($this->results() as $result) {
            if ($result->isBroken()) {
                return true;
            }
        }

        return false;
    }
}
