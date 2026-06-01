# MageMe EU Withdrawal — Magic Link (Pro)

> One-click guest withdrawal access — the "Withdraw from contract" link in order and shipment emails takes the customer straight to their request, with no order lookup required.

[![Magento](https://img.shields.io/badge/Magento-2.4.4%20–%202.4.9-EE672F.svg?style=flat-square)](https://magento.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-777BB4.svg?style=flat-square)](https://php.net)
[![Tier](https://img.shields.io/badge/tier-Pro-6E56CF.svg?style=flat-square)](https://mageme.com/magento-2-withdrawal-button-extension.html)
[![License](https://img.shields.io/badge/license-MageMe%20EULA-blue.svg?style=flat-square)](https://mageme.com/license/)

Pro-tier UX add-on for [`mageme/module-eu-withdrawal`](https://github.com/mageme/module-eu-withdrawal). Upgrades the withdrawal call-to-action in order and shipment confirmation emails from the lookup form to a signed one-click link.

**[Documentation](https://docs.mageme.com)** · **[Get EU Withdrawal Pro](https://mageme.com/magento-2-withdrawal-button-extension.html)**

---

## What it does

- Signed **magic-link tokens** (`?t=…`) behind the "Withdraw from contract" CTA in order and shipment confirmation emails — one click takes the guest straight to their withdrawal, no manual order lookup.
- A **configurable lifetime** (default 30 days, per store view); the link is reusable across the multi-step flow within its window.
- An **admin toggle** to enable or disable per store view — when disabled, the CTA falls back to the standard lookup form, so you can stage the rollout without uninstalling.
- Tokens are scoped to a **single order** and stored as **SHA-256 hashes** (plaintext is never persisted); they grant the withdrawal flow only, never account access.

The CTA placement and email templates live in the base module — this add-on only swaps the link behind them.

## Requirements

- **EU Withdrawal** base module (pulled automatically) — Magento **2.4.4–2.4.9**, **PHP 8.1–8.5**
- A valid **EU Withdrawal Pro** licence

## Install

Pro modules are distributed through the private MageMe Composer repository. Add it once with the credentials from your purchase, then require the package:

```bash
composer config repositories.mageme composer https://repo.mageme.com
composer require mageme/module-eu-withdrawal-magic-link
bin/magento module:enable MageMe_EUWithdrawalMagicLink
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configuration

**Stores → Configuration → MageMe Extensions → EU Withdrawal → Magic Link (Pro)**

| Setting | Default |
|---|---|
| Enable Magic Link | Yes |
| Token Lifetime (days) | 30 |

## Custom Magento development

Need a feature an extension doesn't cover, or a bespoke Magento build? MageMe takes on custom extension development and integration work.

→ **[Custom Magento development](https://mageme.com/magento-services/custom-development)**

## Support

- Documentation: [docs.mageme.com](https://docs.mageme.com)

## Legal disclaimer

Provided **AS-IS, without warranty**, and **not legal advice**. The token lifetime is a security trade-off — set a shorter lifetime if your threat model requires. Whether the CTA suits your transactional-email rules is the merchant's responsibility. See the base module's [full disclaimer](https://docs.mageme.com).

## License

Governed by the **MageMe End User License Agreement** ([mageme.com/license](https://mageme.com/license/)). Pro requires a paid commercial licence.

---

**MageMe** builds Magento 2 and Adobe Commerce extensions for B2B merchants — form building, quoting, catalog control, and EU compliance. → [Browse all extensions](https://mageme.com/extensions)
