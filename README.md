# MageMe_EUWithdrawalMagicLink

**Version:** 0.1.0 (pre-release)
**Composer:** `mageme/module-eu-withdrawal-magic-link`
**Requires:** `mageme/module-eu-withdrawal: >=0.11 <1.0` (Lite)
**Tier:** Pro add-on

Adds one-click guest withdrawal access on top of `MageMe_EUWithdrawal` (Lite). Issues signed 72-hour `?t=TOKEN` magic-link tokens (with rolling 24-hour TTL extension after first click), persisted in `mm_eu_withdrawal_magic_link` as SHA-256 hashes (plaintext never stored). Overrides four Magento sales-email templates (`sales_order_new`, `_guest`, `sales_shipment_new`, `_guest`) to inject a "Right of withdrawal" CTA card via the `WithdrawalLinkSnippet` block.

When this module is installed, Lite's `MagicLinkServiceInterface` is overridden from `NoOpMagicLinkService` (returns null / empty string) to `MagicLinkService` (DB-persisted). The customer-identity factory, lookup controller, and frontend form blocks then resolve `?t=` URL params to bound order entities. When this module is not installed, Lite still satisfies Art. 11a(1) "as easy as conclusion" via the lookup form (CJEU DHL/Amazon precedent treats lookup forms as legally sufficient) — magic links are a UX upgrade, not a compliance MUST.

## ⚠️ Disclaimer

This module is provided **AS-IS, WITHOUT WARRANTY OF ANY KIND**. It is a UX/integration feature add-on. **Whether the magic-link CTA satisfies your jurisdiction's specific marketing-email or transactional-email rules (e.g. PECR, ePrivacy Directive, national email-marketing transpositions) is a question for your counsel.**

The vendor (MageMe / ACTEK d.o.o., Slovenia) makes **no claim** that:

- Magic-link tokens are immune to phishing, credential-stuffing, or token-replay attacks (the 72h + rolling 24h TTL is a trade-off; configure shorter if your threat model requires)
- The injected CTA is appropriate for your transactional-email design — modify the `WithdrawalLinkSnippet` block as needed
- The override of `sales_email/{order,shipment}/{,guest_}template` defaults is acceptable in every regulatory context — you may need to review your customer notice / privacy policy

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

The four `sales_email/{order,shipment}/{,guest_}template` defaults are pointed at this module's templates via `etc/config.xml`. Merchants who set custom values in `core_config_data` keep their overrides (Magento config-merge precedence).

## What it does (technical features)

1. **`mm_eu_withdrawal_magic_link`** table — per-order tokens. SHA-256 hashes only (plaintext never persisted). Columns: token_id, order_id, token_hash, issued_at, expires_at, first_accessed_at, last_accessed_at, used_at, revoked_at.
2. **`Model\Token\MagicLinkService`** — implements Lite's `MagicLinkServiceInterface`. Methods: `issueOrReuseForOrder(int): string`, `resolveOrder(string): ?int`, `markUsed(string): void`, `revoke(int): void`. Emits `mageme_eu_withdrawal_audit_token_{issued,used}` events for the audit trail (consumed by `MageMe_EUWithdrawalAudit` if also installed).
3. **`Observer\AppendMagicLinkToOrderEmails`** — registered on `email_order_set_template_vars_before` and `email_shipment_set_template_vars_before`. Issues/reuses the token, builds the URL, renders the snippet block, and sets `withdrawal_link_url` + `withdrawal_link_html` on the email transport.
4. **`Block\Email\WithdrawalLinkSnippet`** — renders the CTA card with heading ("Right of withdrawal"), reminder text, exclusions note, and bulletproof button (table-based markup that survives email clients).
5. **Token lifecycle** — initial 72h TTL (`INITIAL_TTL_HOURS = 72`). After first click, switches to rolling 24h (`ROLLING_TTL_HOURS = 24`) — each access extends. Single-use after step-2 confirm (currently `markUsed` is reserved; multi-use is the default behaviour).
6. **Race-protection grace window** — `REVOKE_GRACE_SECONDS = 300`. When the same order triggers a re-send (e.g. order-confirmation + shipment-notification within 5 minutes), the existing token is reused; older tokens (>5 min) are revoked.

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

Expected: 37 tests / 108 assertions / 0 failures.

## Licence

MageMe EULA — commercial. See https://mageme.com/license/. Licensor: ACTEK d.o.o., Slovenia.
