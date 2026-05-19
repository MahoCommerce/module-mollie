# Maho Mollie

![License](https://img.shields.io/badge/license-OSL--3.0-blue)
![PHP](https://img.shields.io/badge/php-%3E%3D8.3-8892BF)
![Maho Commerce](https://img.shields.io/badge/Maho_Commerce-module-orange)

**Mollie** payment gateway integration for [Maho Commerce](https://mahocommerce.com).

Accept payments through [Mollie](https://www.mollie.com), one of Europe's leading payment service providers — offering 40+ payment methods across the Payments API and the Orders API (for Klarna and other Buy Now Pay Later methods).

> **Status: Beta — unverified against a live Mollie sandbox.** Core payment flow (create → redirect → webhook → return → cron) is implemented against the Mollie Payments API, plus online refunds, admin-configurable order statuses, and a payment-fee surcharge that shows in checkout. 16 method blocks are configurable in admin — of those, **10 should work end-to-end via redirect** (the generic Mollie selector plus iDEAL, Bancontact, Credit Card, PayPal, Apple Pay, Bank Transfer, SEPA Direct Debit, Gift Card, Google Pay, Trustly). The remaining **6 are flagged "(not implemented yet)"** in their admin labels: Klarna Pay later / Pay now / Slice it, iDEAL in3, and Riverty all require the Orders API (not yet wired) to send `orderLines`; Apple Pay's express-checkout button (cart/PDP) is also not implemented, though Apple Pay via redirect works. Translations ship for Dutch, German, French, Italian, and Spanish.
>
> **Known gaps you'll hit in real testing:**
> - The payment fee is added to the cart grand total but **not propagated to the invoice or credit memo** (DB columns exist; nothing populates them). The fee is also not rendered on order/invoice/creditmemo views — the module ships zero layout XML or phtml templates.
> - Refund amounts passed by Maho's creditmemo flow are forwarded to Mollie verbatim — there is no special handling to exclude the payment fee from a partial refund.
> - The webhook re-fetches the payment from Mollie's API for verification, but there is no DB-level lock around the capture path; concurrent webhook redeliveries could race.

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

### Payment fee (Mollie - Payment Fee)

Optional surcharge added to the order grand total when the customer picks a fee-enabled Mollie method. Supports fixed, percent, or combined fees, with per-method opt-in.

### Method-specific groups

Each of the 16 bundled methods has its own admin group with the usual active / title / country / sort-order controls plus per-method pending / processing order statuses and an optional fee override. Method blocks whose label ends with **"(not implemented yet)"** can be saved/configured but will not produce a working checkout — the module either lacks the Orders API plumbing they need (Klarna ×3, iDEAL in3, Riverty) or is missing a non-redirect flow (Apple Pay express button — note that Apple Pay *via redirect* does work).

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
- [x] Credit card (redirect; Mollie Components / hosted fields not yet)
- [x] PayPal
- [x] Apple Pay (redirect only — express button on cart/PDP not yet)
- [x] Bank Transfer
- [x] SEPA Direct Debit
- [x] Gift Card
- [x] Google Pay
- [x] Trustly

Configurable in admin but not functional without further work (labels end with "(not implemented yet)"):
- [ ] Klarna Pay Later — needs Orders API (`orderLines`)
- [ ] Klarna Pay Now — needs Orders API
- [ ] Klarna Slice It — needs Orders API
- [ ] iDEAL in3 — needs Orders API
- [ ] Riverty — needs Orders API

Not bundled at all (Mollie supports them; this module has no method block, model, or config):
- [ ] TWINT (Switzerland)
- [ ] BLIK (Poland)
- [ ] Przelewy24 (Poland)
- [ ] EPS (Austria)
- [ ] KBC / CBC (Belgium)
- [ ] Belfius (Belgium)
- [ ] Bizum (Spain)
- [ ] MyBank (Italy)
- [ ] Satispay (Italy)
- [ ] Multibanco (Portugal)
- [ ] Voucher (meal-voucher schemes — Edenred, Sodexo, etc.)
- [ ] Alma, Billie, Pay by Bank, POS, and other long-tail methods
- [ ] SOFORT Banking (deprecated by Mollie — listed for completeness)

### Features
- [x] Online refunds from admin via Mollie API (full + partial — but partial refunds forward Maho's amount verbatim, no fee-aware logic)
- [x] Webhook-driven payment status reconciliation (all `mollie_*` method codes, not just the generic gateway)
- [x] Cron-based safety net for missed webhooks (5-minute interval, 24-hour lookback)
- [x] Admin-configurable pending / processing order statuses, per payment method
- [x] Payment-fee surcharge in checkout (fixed / percent / combined, per-method opt-in)
- [x] External-refund and chargeback reconciliation (creditmemo from Mollie dashboard refunds; chargeback order comments only)
- [x] Multi-store API key scoping
- [x] Admin "Test API Key" button (one-click connectivity check)
- [x] Debug logging toggle (gates info-level entries in `var/log/mollie.log`)
- [x] Translations for Dutch, German, French, Italian, and Spanish
- [ ] Payment fee carried into invoice and credit memo records (DB columns exist, no code populates them)
- [ ] Payment fee rendered on order / invoice / credit memo / "My Orders" / order email / PDF (no layout XML or templates ship)
- [ ] Tax on the payment fee (`fee_tax_class` is stored but no tax collector applies it)
- [ ] DB-level idempotency lock on the capture path (today only an in-memory `hasInvoices()` check)
- [ ] Multi-currency support (code paths present but not verified end-to-end)
- [ ] Second-chance payment email
- [ ] Vault / stored cards
- [ ] Mollie Components (hosted card fields)
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
