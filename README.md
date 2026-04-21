# Maho Mollie

![License](https://img.shields.io/badge/license-OSL--3.0-blue)
![PHP](https://img.shields.io/badge/php-%3E%3D8.3-8892BF)
![Maho Commerce](https://img.shields.io/badge/Maho_Commerce-module-orange)

**Mollie** payment gateway integration for [Maho Commerce](https://mahocommerce.com).

Accept payments through [Mollie](https://www.mollie.com), one of Europe's leading payment service providers — offering 40+ payment methods across the Payments API and the Orders API (for Klarna and other Buy Now Pay Later methods).

> **Status: Beta.** Core payment flow (create → redirect → webhook → return → cron) is implemented against the Mollie Payments API, including online refunds, admin-configurable order statuses, and a configurable payment fee. 8 payment methods ship out of the box. End-to-end checkout has not yet been verified against a live Mollie sandbox — expect rough edges. The Orders API (required for full Klarna line-item detail) and Apple Pay express checkout are not yet implemented.

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

Find your API keys in the [Mollie dashboard](https://my.mollie.com/dashboard/) under **Developers**.

### Order statuses (Mollie - Order Statuses)

Configurable codes applied when Mollie reports pending / paid, with optional per-method overrides on each method group.

### Payment fee (Mollie - Payment Fee)

Optional surcharge added to the order grand total when the customer picks a fee-enabled Mollie method. Supports fixed, percent, or combined fees, with per-method opt-in.

### Method-specific groups

Each of the 8 bundled methods (iDEAL, Bancontact, Credit Card, PayPal, Klarna Pay Later / Pay Now / Slice It, Apple Pay) has its own admin group with the usual active / title / country / sort-order controls plus optional status and fee overrides.

## Roadmap

### API layer
- [x] Payments API integration (create, webhook, return, cron reconciliation, refunds)
- [ ] Orders API integration (Klarna line items, in3, Billie, Alma, Riverty, Voucher categorisation)
- [ ] Apple Pay express checkout (domain association + JS button)
- [ ] Google Pay

### Payment methods
- [x] iDEAL
- [x] Credit card (redirect; components / hosted fields not yet)
- [x] Bancontact
- [x] PayPal
- [x] Klarna Pay Later (Payments API — Orders API pending for line items)
- [x] Klarna Pay Now (Payments API — Orders API pending)
- [x] Klarna Slice It (Payments API — Orders API pending)
- [x] Apple Pay (redirect — express button pending)
- [ ] SOFORT Banking
- [ ] SEPA Direct Debit
- [ ] Google Pay
- [ ] Przelewy24
- [ ] KBC / Belfius / EPS / Giftcard / Bank transfer / Voucher / Trustly / ... (long tail)

### Features
- [x] Full + partial refunds from admin (online refunds via Mollie API)
- [x] Webhook-driven payment status reconciliation
- [x] Cron-based safety net for missed webhooks
- [x] Admin-configurable pending / processing order statuses (global + per-method override)
- [x] Payment fee (fixed / percent / combined, per-method opt-in)
- [x] External-refund and chargeback reconciliation (creditmemo from Mollie dashboard refunds; chargeback order comments)
- [x] Multi-store API key scoping
- [ ] Multi-currency support (code paths present but not verified end-to-end)
- [ ] Second-chance payment email
- [ ] Vault / stored cards
- [ ] Mollie Components (hosted card fields)
- [ ] Payment-link generation from admin
- [ ] Shipment push to Mollie (Orders API)
- [ ] Tax on the payment fee (fee_tax_class is stored but not yet applied by a tax collector)

## Acknowledgements

Architecture and feature set informed by the official [mollie/magento2](https://github.com/mollie/magento2) module by Magmodules (OSL-3.0). This module is an independent Maho implementation; no code is copied, but the integration shape and feature surface follow the upstream as a reference.

## License

This module is licensed under the [Open Software License v3.0](LICENSE.txt).

## Links

- [Maho Commerce](https://mahocommerce.com)
- [Mollie](https://www.mollie.com)
- [Mollie API documentation](https://docs.mollie.com/)
- [Mollie Magento 2 module (reference for business logic)](https://github.com/mollie/magento2)
