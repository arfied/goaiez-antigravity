<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The shape a pixel batch must have before the collector will look at it.
 *
 * ⚠️ **THE BODY ARRIVES AS `text/plain` AND THAT IS DELIBERATE ON BOTH ENDS.**
 * The pixel bundle sends `Content-Type: text/plain;charset=UTF-8` on both its
 * transports, because `POST` + `text/plain` is a **CORS-simple** request: no
 * preflight, so one round trip on a visitor's first page and no `OPTIONS` to
 * answer. The cost is that Laravel does not parse it, so this request decodes the
 * raw body itself in `prepareForValidation()`.
 *
 * ⛔ **AND `all()` IS NOT THE ARCHIVE'S INPUT.** The validated array is inspected;
 * what is *stored* is [[rawBody()]], the exact bytes received. Decision 4874: the
 * moment the archive holds a re-encoding of somebody else's JSON it stops being
 * an archive, because key order, float rendering and unicode escaping all change
 * under a round trip while every value still compares equal.
 *
 * ---------------------------------------------------------------------------
 * ⛔ EVERY FAILURE HERE IS A 204, INCLUDING THE VALIDATION ONES
 * ---------------------------------------------------------------------------
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11: *"Always 204, always <50 ms."* A `422` would
 * be worse than useless — the client is a script on somebody else's website that
 * discards the response by design (*"the right behaviour on somebody else's
 * website is to say nothing at all"*), and a validation body naming the missing
 * field would tell an attacker probing the endpoint exactly what shape to send
 * next. `failedValidation()` therefore throws the same empty success the happy
 * path returns.
 *
 * ⚠️ **WHICH MAKES THIS ENDPOINT UNDEBUGGABLE FROM OUTSIDE, ON PURPOSE**, and
 * [[\App\Enums\PixelRefusal]] is where the compensating observability lives.
 */
final class StorePixelBatchRequest extends FormRequest
{
    /**
     * The largest body the collector will read, in bytes.
     *
     * ⚠️ **A SIZE GATE ON AN ANONYMOUS ENDPOINT IS NOT OPTIONAL.** The pixel
     * flushes at twenty events and its own fields are individually truncated, so
     * a genuine batch is a few kilobytes; a client we did not write can post
     * whatever it likes, and L0 is append-only — **for ever, not for §5.2's
     * seven years, because nothing in this application expires L0 at all**
     * (7706). 64 KB is roughly
     * an order of magnitude above the real ceiling, which leaves room for a
     * verbose future event type without leaving the archive open.
     *
     * ⚠️ It is checked against the **raw body length in bytes**, never
     * `mb_strlen` — the limit is a storage limit and a multi-byte character
     * costs what it costs.
     */
    public const int MAX_BODY_BYTES = 64 * 1024;

    /**
     * The most events one batch may carry.
     *
     * The pixel bundle flushes at twenty (`if (queue.length >= 20)`) and the
     * flush on page exit can carry up to that. Verified against the bundle
     * rather than remembered. The margin is for a future event type that batches
     * differently, not for a client inventing its own limit.
     */
    public const int MAX_EVENTS = 50;

    /**
     * ⛔ **DELIBERATELY UNAUTHENTICATED**, like the widget feed and the public
     * audit, and for the strongest reason of the three: the caller is an
     * anonymous visitor's browser on a stranger's website with no session and no
     * token. The public key in the body is the whole credential, and
     * [[\App\Services\Pixel\PixelKeys::resolve()]] is what validates it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // §11 row 1's `data-k`. Checked as a string here and as a UUID by
            // `PixelKeys::resolve()`, which is the one place that decides what a
            // key is — a `uuid` rule beside it would be a second decider.
            'k' => ['required', 'string', 'max:64'],

            // The client's own idea of which line format it is speaking.
            // ⚠️ NOT COMPARED AGAINST `config('warehouse.schema_version')`: a
            // stale cached bundle on somebody's homepage is the normal state of
            // the world, and refusing it would silently switch a tenant off on
            // the day we bump a version. The L0 line carries *our* version and
            // the derivation is what must keep reading old ones.
            'schema_version' => ['required', 'integer', 'min:1'],

            'events' => ['required', 'array', 'min:1', 'max:'.self::MAX_EVENTS],
        ];
    }

    /**
     * Decode the raw body into the validator's input.
     *
     * ⚠️ **NOTHING IS MERGED WHEN THE BODY IS NOT AN OBJECT**, so `rules()` fails
     * on `k` and the request 204s. Throwing here would produce a 500 on a public
     * endpoint for the cost of one malformed request.
     */
    protected function prepareForValidation(): void
    {
        $raw = $this->rawBody();

        if (strlen($raw) === 0 || strlen($raw) > self::MAX_BODY_BYTES) {
            return;
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded) && ! array_is_list($decoded)) {
            $this->merge($decoded);
        }
    }

    /**
     * The exact bytes received, which is what gets archived.
     *
     * ⚠️ **`getContent()`, NOT A RE-ENCODE OF `validated()`.** See the class
     * docblock — this is the single most damaging line in the file to "tidy up".
     */
    public function rawBody(): string
    {
        return $this->getContent();
    }

    /**
     * The decoded batch, for inspection only.
     *
     * @return array<string, mixed>
     */
    public function batch(): array
    {
        /** @var array<string, mixed> $all */
        $all = $this->all();

        return $all;
    }

    /**
     * §11's *"always 204"*, applied to the one path that would otherwise answer
     * something else.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->noContent());
    }
}
