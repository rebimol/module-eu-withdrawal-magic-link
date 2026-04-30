# i18n CSVs — translation provenance (Pro Magic-Link)

These 22 CSVs hold the four magic-link-specific strings used by
`Block\Email\WithdrawalLinkSnippet` (the email-side block that injects
the "Right of withdrawal" CTA into the order/shipment confirmation
emails). The strings are AI-drafted (Lite phase-10 sprint, 2026-04-29);
the merchant should review the legal-language phrasing for their
target jurisdictions with local counsel before production.

When this module is not installed:

- Lite ships no sales-email override and registers no
  AppendMagicLinkToOrderEmails observer, so the customer never sees the
  "Right of withdrawal" block in their order confirmation email — they
  rely on the lookup form for Art. 11a(1) guest withdrawal access.

When this module IS installed:

- Translations load alongside the Lite CSVs; Lite's
  `Plugin\Translate\MergeParentLanguageStrings` walks the locale fallback
  chain so country variants (de_AT, fr_BE, etc.) get parent-language
  strings.

## Locales shipped (22)

en_US (master), bg_BG, cs_CZ, da_DK, de_DE, el_GR, es_ES, et_EE, fi_FI,
fr_FR, hr_HR, hu_HU, it_IT, lt_LT, lv_LV, nl_NL, pl_PL, pt_PT, ro_RO,
sk_SK, sl_SI, sv_SE.

The "withdrawal CTA" button label itself is resolved through Lite's
`FooterLinkLabelResolver` (which reads `etc/button_labels.xml` and
walks `LocaleFallbackResolver`), not through these CSVs.
