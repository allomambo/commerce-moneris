# Changelog

## 1.2.1-alpha.1 - 2026-09-01

### Fixed
- Treat any numeric Moneris RBC / `responseCode` below 50 as approved (Amex `025`, Visa/MC `027`, `000`, `001`, …). The previous `027`/`001` whitelist marked successful charges as failed in Craft, which led to retries and duplicate captures.
- Do not use PHP `empty()` on the RBC — `empty("000")` is true, but `000` is an approved code.
- Map ISO codes to user-facing decline copy only after the RBC says declined. Approved receipts (e.g. Mastercard `027` + ISO `01`) no longer store “Your payment was declined.”

### Changed
- Purchase, preauth, capture, and refund share `MonerisResponseCode::isApproved()` for pass/fail.
- Persist raw receipt fields on the transaction (`raw_message`, `complete`, `timed_out`, `cvd_result`, `avs_result`) so a receipt can be re-judged without PAN or CVD values.
