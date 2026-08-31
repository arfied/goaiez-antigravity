<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every external account a business can connect.
 *
 * Backing values match DATA-MODEL §5.1 `oauth_provider`. A `string` column cast
 * to this enum, never a database enum — see CLAUDE.md §Critical rules and the
 * convention test that enforces it.
 */
enum OauthProvider: string
{
    case Google = 'google';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Microsoft = 'microsoft';
    case Apple = 'apple';
    case Bing = 'bing';
    case Infobip = 'infobip';
    case Stripe = 'stripe';
    case Gsc = 'gsc';
    case GoogleAds = 'google_ads';
    case MetaAds = 'meta_ads';
}
