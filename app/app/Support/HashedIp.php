<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProofHashDomain;
use App\Services\Consent\ProofHash;
use Illuminate\Http\Request;

/**
 * The one way this application turns a visitor's IP address into something it is
 * allowed to keep.
 *
 * `29`'s privacy rules are absolute about the input: "Never store raw IP." What
 * the product still needs is the ability to notice that *one source* is asking
 * for many things — a rate limit, an abuse signal, `public_audits.ip_hash` and
 * `magic_link_tokens.requested_ip_hash`. A keyed hash answers "is this the
 * same visitor as a moment ago?" and answers nothing else.
 *
 * ⚠️ **THIS SAID "THE `ip_hash` ON `public_audits` AND ON `magic_link_tokens`"
 * UNTIL 2026-08-22 AND THE SECOND COLUMN IS `requested_ip_hash`** (7903). Two
 * other files were quoting this sentence and inherited the wrong name, which is
 * why the correction is here rather than only at the copies: this is the file a
 * reader arrives at when asking whether the two hashes are joinable. **They
 * are** — same function, same key, same address.
 *
 * KEYED, NOT PLAIN. `hash_hmac` with the application key rather than a bare
 * `sha256`, and the difference is the whole security property. The IPv4 space is
 * 2^32 — about four billion — which a laptop enumerates in minutes. An unkeyed
 * hash of an IP address is therefore not a pseudonym at all; it is the address
 * with an extra step. Only the secret makes the mapping one-way in practice.
 *
 * That argument applies to rate limiter keys as much as to stored columns, which
 * is why the public audit limiters key on this rather than on `$request->ip()`:
 * Laravel's ThrottleRequests hashes the limiter key with an unsalted sha1/md5
 * before it reaches the cache, and an unsalted hash of an IPv4 is reversible by
 * the same arithmetic.
 *
 * ⛔ **AND THE KEY IS `APP_KEY`, SO A ROTATION MAKES EVERY STORED `ip_hash`
 * UNMATCHABLE — SILENTLY** (8080). `public_audits.ip_hash` stops resuming an
 * audit, `magic_link_tokens.requested_ip_hash` stops joining to it, every rate
 * limiter keyed on this value starts from zero, and the five proof blobs that
 * re-hash it through {@see ProofHash} hold a fingerprint nothing can ever
 * compare again. Nothing raises: the value is still 64 hex characters, and
 * `config/app.php`'s `previous_keys` is **decryption-only**, so every `Crypt`
 * path goes on working and the rotation reads as clean.
 *
 * ⚠️ **THIS IS NOT COVERED BY `IdentifierHashEpochs`, DELIBERATELY, AND THE
 * REASON IS THE DIRECTION OF THE FAILURE.** That guard fails *closed* — a
 * suppression register that cannot answer must not answer "no", because the
 * cost is texting somebody who sent STOP. Every consumer of this function fails
 * *open* in the harmless direction instead: a rate limit resets, an audit
 * resumption is not offered, a proof hash stops matching a hash nothing asks
 * about. Refusing a magic link because the key moved would lock every operator
 * out of the application on the day they most need to be in it. **The hazard is
 * documented here rather than gated, and 8091 records that as a choice.**
 *
 * NULL IS A REAL ANSWER. A console request, a queued job and some proxy
 * configurations have no client address. Callers get null and must decide what
 * that means for them — a nullable column, or a limiter that falls back to a
 * fixed key. Inventing a placeholder here would make "we could not tell" and "we
 * chose not to say" indistinguishable downstream.
 */
final class HashedIp
{
    /**
     * The hash of this request's client address, or null when there isn't one.
     */
    public static function of(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip === null ? null : self::hash($ip);
    }

    /**
     * The hash of an address a caller already holds.
     *
     * Separate from of() so tests and back-fills can hash a literal without
     * building a Request around it.
     *
     * ⛔ **THIS VALUE IS THE SAME IN EVERY TABLE THAT STORES IT, AND THAT IS
     * WHAT 7888 IS ABOUT.** Before storing it anywhere durable, read
     * {@see ProofHashDomain}: the five consent and contract proof
     * blobs re-hash it under their own table's domain
     * ({@see ProofHash}) so one record type's hashes
     * stay comparable with each other and with nothing else.
     * `tests/Feature/Architecture/ProofHashTest.php` holds a census of every
     * caller in `app/` and what becomes of the value, so a new one reddens the
     * build until somebody says where it comes to rest.
     */
    public static function hash(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
