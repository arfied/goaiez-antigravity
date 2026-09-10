<?php

declare(strict_types=1);

namespace App\Modules\X120\Actions;

use App\Modules\X120\Domain\CardExpiredException;
use App\Modules\X120\Domain\CardNumberInvalidException;
use Carbon\Carbon;

/**
 * The door of the vault. Takes the number, the expiry and the name; keeps
 * none of them. Returns what a token would carry — brand, last four, expiry,
 * name — and the state the vault is in: waiting on Stripe tokenisation.
 * Nothing here writes a row, dispatches an event or logs a line (N-046, N-047).
 */
final class CardPresentAction
{
    /**
     * @return array{brand:string,last_four:string,exp_month:int,exp_year:int,name:string,state:string}
     */
    public function handle(string $number, int $expMonth, int $expYear, string $name): array
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';
        if (strlen($digits) < 13 || strlen($digits) > 19 || ! self::luhn($digits)) {
            throw new CardNumberInvalidException('That card number does not check out; nothing was stored.');
        }
        if ($expMonth < 1 || $expMonth > 12) {
            throw new CardExpiredException('The expiry month is not a month; nothing was stored.');
        }
        if (Carbon::createFromDate($expYear, $expMonth, 1)->endOfMonth()->isPast()) {
            throw new CardExpiredException(sprintf('That card expired %02d/%d; an expired card is never stored.', $expMonth, $expYear));
        }
        $name = trim($name);
        if ($name === '') {
            throw new CardNumberInvalidException('The name on the card is needed; nothing was stored.');
        }

        $first = $digits[0];
        if (! in_array($first, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], true)) {
            throw new CardNumberInvalidException('That card number does not check out; nothing was stored.');
        }

        $two = (int) substr($digits, 0, 2);
        $four = (int) substr($digits, 0, 4);

        $brand = match (true) {
            $first === '4' => 'visa',
            $two >= 51 && $two <= 55 => 'mastercard',
            $four >= 2221 && $four <= 2720 => 'mastercard',
            $two === 34 || $two === 37 => 'amex',
            default => 'card',
        };

        return [
            'brand' => $brand,
            'last_four' => substr($digits, -4),
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
            'name' => $name,
            'state' => 'waiting_on_tokenisation',
        ];
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($double) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
