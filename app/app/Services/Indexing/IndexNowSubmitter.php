<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Enums\IndexingEngine;
use App\Enums\IndexingMethod;
use App\Enums\IndexingRefusal;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * The IndexNow half of slice E: one POST that tells every participating search
 * engine a URL changed.
 *
 * ## The protocol, read on 2026-08-20 and not from decision 48
 *
 * ⛔ **DECISION 48 SAYS *"IndexNow → Bing/Yandex/Seznam/Naver"* AND THAT IS NOT
 * FOUR CALLS** (5681). The specification's own closing paragraph: *"Search
 * engines adopting the IndexNow protocol agree that submitted URLs will be
 * automatically shared with all other participating search engines"*, and the
 * FAQ makes it an instruction rather than a nicety — *"You may submit your
 * request to only one of the following participating endpoints. Each endpoint
 * sends your submission directly to its respective search engine, and your
 * submission will be shared across all IndexNow-enabled search engines"*
 * (`indexnow.org/documentation` and `indexnow.org/faq`, both fetched
 * 2026-08-20). Posting to four of them would quadruple our exposure to the 429
 * the same FAQ warns about, for nothing.
 *
 * ⚠️ **AND THE PARTICIPANT LIST HAS GROWN SINCE 48 WAS WRITTEN.** Seven
 * endpoints are published today: the shared one, Amazon, Bing, Naver, Seznam.cz,
 * Yandex and Yep. So a submission reaches **six companies**, which is a fact for
 * `SUBPROCESSOR-INVENTORY.md` rather than for this file, and which no
 * single-vendor row could have stated.
 *
 * ## The payload
 *
 * *"You can submit up to 10,000 URLs per post, mixing http and https URLs if
 * needed"*, with `Content-Type: application/json; charset=utf-8` and the four
 * documented fields — `host`, `key`, optional `keyLocation`, `urlList`.
 *
 * ## Nothing in this deployment can call this class successfully
 *
 * ⛔ **AND THAT IS SAID HERE RATHER THAN LEFT TO BE DISCOVERED.** IndexNow
 * verifies ownership by fetching a key file from the tenant's own host;
 * {@see UnhostedIndexNowKeys} is the only `IndexNowKeys` binding and it never
 * returns a key, because serving that file is the WordPress plugin's job (F2,
 * decision 5581). So {@see Indexing::announce()} refuses before this class is
 * reached, on every location, on every deployment that exists today — and every
 * one of those refusals is a row with a reason on it.
 *
 * ⚠️ **WHAT THE TESTS PROVE IS OUR HALF OF THE WIRE AND NOTHING ELSE**
 * (352/397, `WordPressAdapter`'s posture). They fake the HTTP layer, assert the
 * request this class builds against the protocol as published, and drive every
 * documented response code. **No test here has ever talked to a search engine**,
 * and a test named as though it had would be worse than none.
 */
final class IndexNowSubmitter
{
    /**
     * The shared endpoint. One call, all participants.
     *
     * ⚠️ **THE HOST LITERAL AND ITS `SUBPROCESSOR-INVENTORY.md` ROW ARE ONE
     * CHANGE**, which is the coupling `outboundHttpPermittedFiles()` exists to
     * force: the only way to reach a new vendor from this codebase is to name it
     * in a document with legal weight.
     */
    public const string ENDPOINT = 'https://api.indexnow.org/indexnow';

    /**
     * *"Submit no more than 10,000 URLs per request."* A caller over the limit
     * is a programming error here — this slice announces one URL at a time —
     * and the constant exists so a later batching caller has the vendor's own
     * figure rather than a guess.
     */
    public const int MAX_URLS = 10_000;

    /**
     * Seconds. Short on purpose: an announcement is a courtesy to a search
     * engine and must never hold a queue worker open behind a slow endpoint.
     */
    private const int TIMEOUT = 8;

    /**
     * How much of the engine's answer is kept.
     *
     * IndexNow's success bodies are empty and its failures are short, so this is
     * generous rather than tight — the reason it is bounded at all is that
     * `indexing_submissions.response` is read on a staff screen and an
     * unbounded vendor string is somebody else's content in our database.
     */
    private const int RESPONSE_BODY_LIMIT = 200;

    /**
     * Announce URLs, and say what the engine answered.
     *
     * @param  list<string>  $urls
     */
    public function submit(IndexNowKey $key, array $urls): IndexingAttempt
    {
        if (count($urls) > self::MAX_URLS) {
            // ⛔ **OUR OWN CALLER'S ERROR, SO IT RAISES.** The vendor answers an
            // over-limit post with a 400 that says *"invalid format"* and
            // nothing about the count, which would be recorded as a rejection
            // and read as a key problem for as long as nobody counted the array.
            throw new InvalidArgumentException(
                'IndexNow accepts at most '.self::MAX_URLS.' URLs per request; '.count($urls).' were given.',
            );
        }

        if (! $key->isWellFormed()) {
            // ⛔ **REFUSED BEFORE THE WIRE, NOT THROWN.** A malformed key is a
            // defect in whoever provided it — F2, when it exists — and the
            // vendor's answer to one is a 403 meaning *"key not valid"*, which
            // is indistinguishable in the row from a key file that is not being
            // served. Refusing here keeps those two facts apart.
            return IndexingAttempt::refused(
                IndexingEngine::IndexNow,
                IndexingMethod::IndexNow,
                IndexingRefusal::MalformedKey,
            );
        }

        $payload = array_filter([
            'host' => $key->host,
            'key' => $key->key,
            'keyLocation' => $key->keyLocation,
            'urlList' => $urls,
        ], static fn (mixed $value): bool => $value !== null);

        try {
            $response = VendorLog::timed(
                'indexnow',
                'POST',
                self::ENDPOINT,
                fn (): Response => Http::withHeaders(['Content-Type' => 'application/json; charset=utf-8'])
                    ->timeout(self::TIMEOUT)
                    ->post(self::ENDPOINT, $payload),
            );
        } catch (ConnectionException) {
            VendorLog::failure('indexnow', 'POST', self::ENDPOINT, ConnectionException::class);

            return IndexingAttempt::failed(IndexingEngine::IndexNow, IndexingMethod::IndexNow, [
                'error' => 'connection_failed',
                'urls' => count($urls),
            ]);
        }

        return $this->classify($response, $key, count($urls));
    }

    /**
     * The vendor's own response table, turned into our own five outcomes.
     *
     * ⛔ **403 AND 422 ARE PERMANENT AND 429 IS NOT, AND CONFLATING THEM IS THE
     * EXPENSIVE MISTAKE.** IndexNow documents 403 as *"key not valid (e.g. key
     * not found, file found but key not in the file)"* and 422 as *"URLs which
     * don't belong to the host or the key is not matching the schema"* — both
     * are our configuration being wrong, and retrying is spam. 429 is *"Too Many
     * Requests (potential Spam)"* and the FAQ's remedy is to *"check the
     * Retry-After header"*, which is the queue's business.
     */
    private function classify(Response $response, IndexNowKey $key, int $urlCount): IndexingAttempt
    {
        $recorded = [
            'status' => $response->status(),
            'urls' => $urlCount,
            'body' => $this->safeBody($response, $key),
        ];

        $retryAfter = $response->header('Retry-After');

        if ($retryAfter !== '') {
            $recorded['retry_after'] = $retryAfter;
        }

        return match (true) {
            $response->status() === 200 => IndexingAttempt::submitted(
                IndexingEngine::IndexNow, IndexingMethod::IndexNow, $recorded,
            ),
            $response->status() === 202 => IndexingAttempt::pending(
                IndexingEngine::IndexNow, IndexingMethod::IndexNow, $recorded,
            ),
            $response->status() === 429, $response->serverError() => IndexingAttempt::failed(
                IndexingEngine::IndexNow, IndexingMethod::IndexNow, $recorded,
            ),
            default => IndexingAttempt::rejected(
                IndexingEngine::IndexNow, IndexingMethod::IndexNow, $recorded,
            ),
        };
    }

    /**
     * The engine's answer, bounded and with the key taken out of it.
     *
     * ⛔ **THE REDACTION IS NOT DECORATION.** IndexNow's 403 means *"file found
     * but key not in the file"*, and an endpoint that echoed the key it was sent
     * would put a shared secret into a jsonb column that a staff screen renders
     * — `CLAUDE.md`'s *never reaches a log, an exception message or a test
     * fixture* applied to the one sink nobody thinks of. We cannot promise the
     * vendor never echoes it; we can promise we never keep it.
     */
    private function safeBody(Response $response, IndexNowKey $key): string
    {
        $body = mb_substr(trim($response->body()), 0, self::RESPONSE_BODY_LIMIT);

        return str_replace($key->key, '[redacted]', $body);
    }
}
