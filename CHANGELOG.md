## 1.0.3

* Other: Corrected the database schema whitelist to match the table's real index and constraint names.

## 1.0.2

- Fix: Token lifetime now respects the per-store-view "Token Lifetime" setting.
* Other: Expired token records are cleaned up automatically by a daily job; the audit trail now logs one "used" event per link instead of per click.

## 1.0.1

* Other: Aligned the EU Withdrawal core dependency requirement with the 2.x line.

## 1.0.0

+ New: First public release. One-click guest withdrawal access via signed magic-link tokens — the "Withdraw from contract" link in order and shipment confirmation emails takes the customer straight to their request, with no order lookup required.
+ New: Admin toggle to enable or disable magic links per store view; when disabled, the link falls back to the standard lookup form.
