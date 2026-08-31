<?php

declare(strict_types=1);

namespace App\Services\Legal;

use InvalidArgumentException;

/**
 * An SMS & Communications Terms version that does not quote the opt-in notice
 * this application actually shows.
 *
 * ⚠️ IT EXTENDS `InvalidArgumentException` DELIBERATELY, AND THAT IS WHERE ITS
 * MESSAGE IS READ. `LegalDocuments` already refuses an unreviewed publish, an
 * empty publisher and an edit to frozen text by throwing that type, and
 * `Livewire\Admin\LegalDocuments` catches exactly it so the reason reaches the
 * admin as a toast rather than as a generic failure. A new exception hierarchy
 * would have made this one refusal — the only one with a legal consequence
 * outside this application — the one that surfaced as "something went wrong".
 *
 * It is a distinct class rather than a bare `InvalidArgumentException` so a
 * test can name what refused. Decision 398's finding is that an outer guard
 * refusing first leaves the inner one unfalsifiable; when the guard and the
 * three above it all throw the same type, "the publish was refused" stops being
 * evidence that this check ran.
 */
final class SmsTermsMisaligned extends InvalidArgumentException {}
