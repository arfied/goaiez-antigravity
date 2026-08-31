<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §8's canonical conversion list: *"form_submitted
 * | phone_click | email_click | directions_click | tenant-fired
 * `_q.track('conversion')`."*
 *
 * ⚠️ **THE FIFTH IS NOT IN THIS ENUM.** The pixel bundle has no `_q.track()`
 * API today — it emits exactly the four auto-conversions below, from
 * `conversionFor()` and the form-submit listener — so a case for the
 * tenant-fired kind would be a value nothing could ever produce (256's
 * vacuity). It arrives with the tracking call.
 *
 * The values are L1's own `event_type` strings, which is what
 * `App\Services\Warehouse\Replayer` matches against when it selects
 * conversion rows for the conversion mart — never a database enum.
 */
enum ConversionType: string
{
    case FormSubmitted = 'form_submitted';
    case PhoneClick = 'phone_click';
    case EmailClick = 'email_click';
    case DirectionsClick = 'directions_click';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
