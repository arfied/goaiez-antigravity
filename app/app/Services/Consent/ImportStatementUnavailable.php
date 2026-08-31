<?php

declare(strict_types=1);

namespace App\Services\Consent;

use RuntimeException;

/**
 * No usable Customer List Attestation is published, so nothing may be imported.
 *
 * ⚠️ **A PLATFORM STATE, NOT A CALLER MISTAKE**, which is why it is its own
 * class rather than the `InvalidArgumentException` every other refusal in this
 * namespace throws. The caller did nothing wrong and can do nothing about it:
 * the wording is counsel's to write and an admin's to publish (decisions 549 and
 * 475 are untouched by any of this code), and until that happens the honest
 * answer is a refusal, not a default.
 *
 * That is decision 502's pattern — `WithheldRegistryValue` raises rather than
 * resolving a price nobody has set, because a conservative default for an unset
 * figure is not a smaller number but a refusal to quote one. The same reasoning
 * carries much further here: a made-up attestation statement would put words
 * into a tenant's mouth in the one record that exists to prove what they said.
 *
 * A distinct type so the screen can catch *this* and render "waiting on
 * published wording" without string-matching a message, and so a future queued
 * importer can tell "nothing is published yet" apart from "this file is
 * malformed" — the first clears when an admin acts, the second never does.
 */
final class ImportStatementUnavailable extends RuntimeException {}
