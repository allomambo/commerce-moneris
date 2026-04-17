<?php

namespace allomambo\CommerceMoneris\helpers;

use Craft;

/**
 * Maps Moneris ISO response codes to clean, localized user-facing messages.
 *
 * Per Moneris documentation: "Do NOT validate the combination of RBC and ISO
 * response codes. These are liable to change without notice." — ISO codes are
 * therefore the only reliable key used here.
 */
class MonerisResponseMessage
{
    /**
     * Resolve a user-facing, localized message from the Moneris response fields.
     *
     * Priority:
     *  1. ISO code match (always preferred — standardized and stable)
     *  2. Generic decline fallback for response codes >= 050
     *  3. Cleaned raw message (strip padding, asterisks, equals signs)
     *  4. Generic fallback
     */
    public static function resolve(string $isoCode, string $responseCode, string $rawMessage): string
    {
        $isoCode = trim($isoCode);

        // Step 1 — ISO code lookup
        $message = self::messageForIso($isoCode);
        if ($message !== null) {
            return $message;
        }

        // Step 2 — generic decline for any unrecognised code >= 050
        if ($responseCode !== '' && $responseCode !== 'null') {
            $numericCode = (int)$responseCode;
            if ($numericCode >= 50) {
                return Craft::t('moneris-gateway', 'Your payment was declined. Please try a different card.');
            }
        }

        // Step 3 — clean up the raw Moneris terminal message
        $cleaned = self::cleanRawMessage($rawMessage);
        if ($cleaned !== '') {
            return $cleaned;
        }

        // Step 4 — final fallback
        return Craft::t('moneris-gateway', 'Your payment could not be processed. Please try again.');
    }

    /**
     * Map an ISO 8583 code to a localized user-facing message.
     * Returns null when no specific mapping exists.
     */
    private static function messageForIso(string $iso): ?string
    {
        return match ($iso) {
            // Refer to issuer
            '01'      => Craft::t('moneris-gateway', 'Your payment was declined. Please contact your bank or try a different card.'),

            // Do not honour / general decline
            '05'      => Craft::t('moneris-gateway', 'Your payment was declined. Please try a different card.'),

            // Invalid card number (Mod 10 / bad PAN / bad track)
            '14', '15', '92' => Craft::t('moneris-gateway', 'The card number is invalid. Please check and try again.'),

            // Re-enter / format / edit error
            '06', '12', '13', '19', '20', '21', '25', '30' => Craft::t('moneris-gateway', 'An error occurred. Please try again.'),

            // Restricted / lost / stolen card (avoid revealing sensitive status)
            '36', '41', '43', '62' => Craft::t('moneris-gateway', 'Please use a different card.'),

            // PIN tries exceeded
            '38', '75' => Craft::t('moneris-gateway', 'Too many incorrect PIN attempts. Please contact your bank.'),

            // No chequing account
            '39', '52' => Craft::t('moneris-gateway', 'No chequing account found for this card.'),

            // No savings account
            '48', '53' => Craft::t('moneris-gateway', 'No savings account found for this card.'),

            // Insufficient funds
            '51'      => Craft::t('moneris-gateway', 'Insufficient funds. Please use a different card.'),

            // Expired card
            '54'      => Craft::t('moneris-gateway', 'This card has expired. Please use a different card.'),

            // Incorrect PIN
            '55'      => Craft::t('moneris-gateway', 'Incorrect PIN. Please try again.'),

            // Card not set up for this transaction type
            '56'      => Craft::t('moneris-gateway', 'This card is not set up for this type of transaction.'),

            // Transaction not permitted to cardholder or terminal
            '57', '58' => Craft::t('moneris-gateway', 'This transaction is not permitted for this card.'),

            // Exceeds withdrawal amount or frequency limit
            '61', '65' => Craft::t('moneris-gateway', 'This transaction exceeds your card limit.'),

            // PIN / CVD security code error
            '82'      => Craft::t('moneris-gateway', 'Security code error. Please check and try again.'),

            // Card issuer temporarily unavailable
            '91'      => Craft::t('moneris-gateway', 'Your bank is temporarily unavailable. Please try again later.'),

            // System malfunction
            '96'      => Craft::t('moneris-gateway', 'A system error occurred. Please try again later.'),

            default   => null,
        };
    }

    /**
     * Strip padding, asterisks, equals signs, and excessive whitespace from the
     * raw Moneris terminal message (e.g. "Re-Try             * Invalid Card #     =").
     */
    private static function cleanRawMessage(string $raw): string
    {
        // Remove asterisks, equals signs, and leading/trailing whitespace
        $cleaned = trim(preg_replace('/[*=]+/', '', $raw) ?? '');

        // Collapse multiple consecutive spaces into one
        $cleaned = (string)preg_replace('/\s{2,}/', ' ', $cleaned);

        return $cleaned;
    }
}
