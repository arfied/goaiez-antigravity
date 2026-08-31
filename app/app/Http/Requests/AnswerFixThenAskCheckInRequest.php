<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FixThenAskResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The customer's own answer to "did we get that sorted?" — T546 §37.3(1),
 * wave 38 lane C (10590–10609).
 *
 * ⚠️ **A CLICK, VALIDATED AS ONE.** The two buttons on the check-in page each
 * post one of `FixThenAskResponse`'s two values — never free text, on that
 * enum's own docblock. `Rule::enum()` is `CLAUDE.md`'s standing rule for
 * validating a backed enum.
 */
final class AnswerFixThenAskCheckInRequest extends FormRequest
{
    /**
     * Public by design. The page is unauthenticated; the tenant boundary and
     * the whole authorisation are the `signed` route middleware's, the same
     * shape `campaign.media` and `content.growth-page.hold` already use.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'answer' => ['required', Rule::enum(FixThenAskResponse::class)],
        ];
    }

    public function answer(): FixThenAskResponse
    {
        return FixThenAskResponse::from($this->string('answer')->toString());
    }
}
