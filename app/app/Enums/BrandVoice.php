<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The tone AI-written content speaks in (DATA-MODEL §5.1 `brand_voice`).
 *
 * Applies to replies, posts, and content — the things AI is allowed to write.
 */
enum BrandVoice: string
{
    case FriendlyWarm = 'friendly_warm';
    case Professional = 'professional';
    case FunCasual = 'fun_casual';
}
