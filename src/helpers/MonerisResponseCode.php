<?php

namespace allomambo\CommerceMoneris\helpers;

/**
 * Moneris RBC / host responseCode helpers.
 *
 * Official semantics (not ISO):
 *  - numeric RBC < 50: approved
 *  - numeric RBC >= 50: declined
 *  - null / empty / "null": not sent for authorization
 *
 * Do not whitelist individual codes (027, 001, 025, …). Brand tables are
 * incomplete and the same ISO means opposite things depending on the RBC.
 */
class MonerisResponseCode
{
    /**
     * Whether the RBC indicates an approved transaction.
     *
     * Must not use empty() on the code: PHP empty("0") is true, and integer 0
     * would be discarded. Padded "000" is a documented Visa/Discover approval.
     */
    public static function isApproved(?string $rbc): bool
    {
        if ($rbc === null) {
            return false;
        }

        $rbc = trim($rbc);

        if ($rbc === '' || strtolower($rbc) === 'null') {
            return false;
        }

        return is_numeric($rbc) && (int) $rbc < 50;
    }
}
