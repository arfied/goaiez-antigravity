<?php

declare(strict_types=1);

namespace App\Services\Legal;

use RuntimeException;

/**
 * No account can be opened on terms nobody has published (T176 P22).
 *
 * `ImportStatementUnavailable`'s shape, one document set over: a distinct type
 * rather than a bare `RuntimeException` so the two signup doors can each report
 * it in their own idiom — `POST /register` as a form error, the OAuth callback
 * as a redirect — without catching everything else a long call chain can throw.
 */
final class SignupTermsUnavailable extends RuntimeException {}
