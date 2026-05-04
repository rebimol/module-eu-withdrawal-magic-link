# MageMe_EUWithdrawalMagicLink

**Version:** 0.1.2 (pre-release)
**Composer:** `mageme/module-eu-withdrawal-magic-link`
**Requires:** `mageme/module-eu-withdrawal: >=0.12.9 <1.0` (Lite)
**Tier:** Pro add-on

Pro tier UX upgrade for `MageMe_EUWithdrawal` (Lite). Issues signed `?t=TOKEN` magic-link tokens with a configurable absolute lifetime (admin-controlled, default 30 days). Tokens are persisted in `mm_eu_withdrawal_magic_link` as SHA-256 hashes (plaintext never stored). Swaps Lite's withdrawal-CTA URL in order/shipment emails from the lookup-form URL to the tokenised one-click variant.

## What it does (technical features)

1. **`mm_eu_withdrawal_magic_link`** table — per-order tokens. SHA-256 hashes only (plaintext never persisted). Columns: token_id, order_id, token_hash, issued_at, expires_at, first_accessed_at, last_accessed_at, used_at, revoked_at.
2. **`Model\Token\MagicLinkService`** — implements Lite's `MagicLinkServiceInterface`. Methods: `issueOrReuseForOrder(int): string`, `resolveOrder(string): ?int`, `markUsed(string): void`, `revoke(int): void`. Emits `mageme_eu_withdrawal_audit_token_{issued,used}` events for the audit trail (consumed by `MageMe_EUWithdrawalAudit` if also installed).
3. **`Model\Email\MagicLinkWithdrawalLinkResolver`** — implements Lite's `WithdrawalLinkResolverInterface`. Returns `https://example.com/withdraw-contract?t=TOKEN` instead of the Lite default `/withdraw-contract/`. The withdrawal-CTA observer, snippet block, phtml, and the four sales-email overrides all live in Lite — Pro only swaps the URL via DI `<preference>`.
4. **Admin toggle** — `Stores → Configuration → MageMe Extensions → EU Withdrawal → Magic Link (Pro) → Enable Magic Link` (store-view scope). Default after install: **Yes**. When set to **No**, the Pro resolver delegates to Lite's `LookupWithdrawalLinkResolver` and the CTA falls back to the lookup-form URL — same behaviour as a Lite-only install, no need to uninstall Pro for staged rollouts. Already-issued tokens keep resolving until their TTL expires.
5. **Token lifecycle** — single absolute lifetime configurable via `Stores → Configuration → MageMe Extensions → EU Withdrawal → Magic Link (Pro) → Token Lifetime (days)` (default `30`, store-view scope, read by `Model\Config\MagicLinkConfig::getLifetimeDays()`). The token resolves while `now <= expires_at`; once consumed (`markUsed`), revoked, or expired, it stops resolving. `first_accessed_at` / `last_accessed_at` are stamped on every successful resolve for audit purposes only — they no longer gate access.
6. **Race-protection grace window** — `REVOKE_GRACE_SECONDS = 300`. When the same order triggers a re-send (e.g. order-confirmation + shipment-notification within 5 minutes), the existing token is reused; older tokens (>5 min) are revoked.

## ⚠️ Disclaimer

This module is provided **AS-IS, WITHOUT WARRANTY OF ANY KIND**. It is a UX feature add-on. **Whether the magic-link CTA satisfies your jurisdiction's specific marketing-email or transactional-email rules (e.g. PECR, ePrivacy Directive, national email-marketing transpositions) is a question for your counsel.**

The vendor (MageMe / ACTEK d.o.o., Slovenia) makes **no claim** that:

- Magic-link tokens are immune to phishing, credential-stuffing, or token-replay attacks (the absolute lifetime is a trade-off; configure a shorter `Token Lifetime` if your threat model requires)
- The injected CTA is appropriate for your transactional-email design — modify the `WithdrawalLinkSnippet` block (Lite-side) as needed

The merchant is solely responsible for legal-context evaluation. By installing this module you accept these terms.

See the parent module's [LICENSE](../EUWithdrawal/LICENSE) and [README disclaimer](../EUWithdrawal/README.md#-disclaimer-—-please-read-before-installing) for full terms.

## Installation

```bash
composer require mageme/module-eu-withdrawal-magic-link
bin/magento module:enable MageMe_EUWithdrawalMagicLink
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The withdrawal-CTA placement and template wire-up are managed by Lite (see `MageMe_EUWithdrawal` README → "Adding the withdrawal CTA to your email template"). Installing this Pro module changes the URL behind the CTA from the lookup form to a tokenised one-click variant — nothing else.

## What it doesn't do

- Replace customer login. Magic links are scoped to **one order** — they bind a guest's session to that order entity for the withdrawal flow only. The customer cannot use the token to access account features, change the order, or log in.
- Provide email-deliverability guarantees. The token is generated and the URL is shipped; whether the email actually reaches the inbox depends on the merchant's SMTP / mail-service setup.
- Sign or encrypt the token URL. The token is a 32-byte random hex string; security relies on (a) collision resistance of the random space (~10^77 keyspace), (b) SHA-256 hash storage so a DB leak doesn't reveal active tokens, and (c) TTL.

## Database

| Table | Purpose |
|---|---|
| `mm_eu_withdrawal_magic_link` | Per-order tokens. SHA-256 hashes only. Validity check on `resolveOrder()`. |

## Tests

```bash
docker exec -u magento dev_php vendor/bin/phpunit -c app/code/MageMe/EUWithdrawalMagicLink/Test/Unit/phpunit.xml.dist
```

## Licence

MageMe EULA — commercial. See https://mageme.com/license/. Licensor: ACTEK d.o.o., Slovenia.
