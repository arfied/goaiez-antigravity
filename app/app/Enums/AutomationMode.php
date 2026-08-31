<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How much a location's autopilot may do unattended (DATA-MODEL §5.4).
 *
 * Auto is the default — defaults are ungated (`29` §2 rule 37). Confirm is
 * reserved for exactly three things regardless of this setting: GBP core-field
 * changes, anything that spends money, and the first send of a new campaign
 * type. This enum is the mode, not the gate.
 */
enum AutomationMode: string
{
    case Auto = 'auto';
    case Hold = 'hold';
    case Confirm = 'confirm';
}
