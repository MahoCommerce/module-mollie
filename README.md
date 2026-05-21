# Maho Mollie

![Maho Commerce](https://img.shields.io/badge/Maho_Commerce-module-orange)
![License](https://img.shields.io/badge/license-OSL--3.0-blue)
![PHP](https://img.shields.io/badge/php-%3E%3D8.3-8892BF)
![PHPStan Level](https://img.shields.io/badge/PHPStan-level%208-brightgreen)

**Mollie** payment gateway integration for [Maho Commerce](https://mahocommerce.com).

Accept payments through [Mollie](https://www.mollie.com), one of Europe's leading payment service providers — offering 40+ payment methods across the Payments API and the Orders API (for Klarna and other Buy Now Pay Later methods).

> **Status: Beta — redirect flow + Mollie Components verified against the Mollie sandbox.** Core payment flow (create → redirect → webhook → return → cron) is implemented against the Mollie Payments API, plus online refunds and admin-configurable order statuses. 28 method blocks are configurable in admin and all of them work end-to-end via redirect (the generic Mollie selector plus the per-method blocks listed below). For credit cards there's an opt-in PCI-SAQ-A flow via Mollie Components: the card-number / expiry / CVC / cardholder fields render inline in your checkout (cross-origin iframes hosted by Mollie), the customer stays on your site through to the success page, and the redirect-to-Mollie fallback still triggers if Components isn't configured or JS fails. Apple Pay's express-checkout button (cart/PDP) is not yet implemented, though Apple Pay via redirect works. Translations ship for Dutch, German, French, Italian, and Spanish.
>
> **Known gap:** the webhook re-fetches the payment from Mollie's API for verification, but there is no DB-level lock around the capture path; concurrent webhook redeliveries could race.

## Requirements

- PHP >= 8.3
- Maho Commerce
- A [Mollie](https://www.mollie.com) merchant account

## Installation

```bash
composer require mahocommerce/module-mollie
```

Clear the cache after installation:

```bash
./maho cache:flush
```

## Configuration

Navigate to **System > Configuration > Payment Methods** in the Maho admin panel.

### General Settings (Mollie - General Settings)

| Setting | Description | Default |
|---|---|---|
| **Test Mode** | Use the Mollie test API key to process mock payments | Yes |
| **Live API Key** | Your Mollie live API key (starts with `live_`) | — |
| **Test API Key** | Your Mollie test API key (starts with `test_`) | — |
| **Test API Key** (button) | One-click check that the configured key can reach the Mollie API | — |
| **Debug Logging** | When enabled, writes verbose reconcile/refund events to `var/log/mollie.log` | No |

Find your API keys in the [Mollie dashboard](https://my.mollie.com/dashboard/) under **Developers**.

### Method-specific groups

Each of the 28 bundled methods has its own admin group with the usual active / title / country / sort-order controls plus per-method pending / processing order statuses.

## Roadmap

### API layer
- [x] Payments API integration (create, webhook, return, cron reconciliation, refunds)
- [ ] Orders API integration (Klarna line items, in3, Billie, Alma, Riverty, Voucher categorisation)
- [ ] Apple Pay express checkout (domain association + JS button)

### Payment methods

Working end-to-end via redirect (Mollie hosts the actual UI):
- [x] Generic Mollie gateway (Mollie's full method picker)
- [x] iDEAL
- [x] Bancontact
- [x] Credit card (redirect by default; PCI-SAQ-A inline checkout via Mollie Components — toggle "Use Mollie Components" + Profile ID in the admin section)
- [x] PayPal
- [x] Apple Pay (redirect only — express button on cart/PDP not yet)
- [x] Bank Transfer
- [x] SEPA Direct Debit
- [x] Gift Card
- [x] Google Pay
- [x] Trustly
- [x] TWINT (Switzerland)
- [x] BLIK (Poland)
- [x] Przelewy24 (Poland)
- [x] EPS (Austria)
- [x] Belfius (Belgium)
- [x] Bizum (Spain)
- [x] MyBank (Italy / Spain)
- [x] Satispay (Italy)
- [x] BANCOMAT Pay (Italy)
- [x] Multibanco (Portugal)
- [x] MB WAY (Portugal)
- [x] Payconiq (Belgium / NL / Lux)
- [x] MobilePay (Denmark / Finland)
- [x] Swish (Sweden)
- [x] Vipps (Norway)
- [x] Pay by Bank (UK)
- [x] paysafecard

Not bundled at all (Mollie supports them; this module has no method block, model, or config):
- [ ] Klarna Pay Later / Pay Now / Slice It — need the Orders API to send `orderLines`
- [ ] iDEAL in3 — needs Orders API
- [ ] Riverty — needs Orders API
- [ ] KBC / CBC (Belgium) — needs issuer selection
- [ ] Voucher (meal-voucher schemes — Edenred, Sodexo, etc.) — needs per-product category mapping
- [ ] Alma, Billie — BNPL, need the Orders API
- [ ] POS (point-of-sale) — needs terminal picker
- [ ] SOFORT Banking (deprecated by Mollie — listed for completeness)

### Features
- [x] Online refunds from admin via Mollie API (full + partial)
- [x] Webhook-driven payment status reconciliation (all `mollie_*` method codes, not just the generic gateway)
- [x] Cron-based safety net for missed webhooks (5-minute interval, 24-hour lookback)
- [x] Admin-configurable pending / processing order statuses, per payment method
- [x] External-refund and chargeback reconciliation (creditmemo from Mollie dashboard refunds; chargeback order comments only)
- [x] Multi-store API key scoping
- [x] Admin "Test API Key" button (one-click connectivity check)
- [x] Debug logging toggle (gates info-level entries in `var/log/mollie.log`)
- [x] Translations for Dutch, German, French, Italian, and Spanish
- [ ] DB-level idempotency lock on the capture path (today only an in-memory `hasInvoices()` check)
- [ ] Multi-currency support (code paths present but not verified end-to-end)
- [ ] Second-chance payment email
- [ ] Vault / stored cards
- [x] Mollie Components (hosted card fields) — opt-in per store via admin
- [ ] Payment-link generation from admin
- [ ] Shipment push to Mollie (Orders API)

## Acknowledgements

Architecture and feature set informed by the official [mollie/magento2](https://github.com/mollie/magento2) module by Magmodules (OSL-3.0). This module is an independent Maho implementation; no code is copied, but the integration shape and feature surface follow the upstream as a reference.

## License

This module is licensed under the [Open Software License v3.0](LICENSE.txt).

## Links

- [Maho Commerce](https://mahocommerce.com)
- [Mollie](https://www.mollie.com)
- [Mollie API documentation](https://docs.mollie.com/)
- [Mollie Magento 2 module (reference for business logic)](https://github.com/mollie/magento2)
