# Maho Mollie

![License](https://img.shields.io/badge/license-OSL--3.0-blue)
![PHP](https://img.shields.io/badge/php-%3E%3D8.3-8892BF)
![Maho Commerce](https://img.shields.io/badge/Maho_Commerce-module-orange)

**Mollie** payment gateway integration for [Maho Commerce](https://mahocommerce.com).

Accept payments through [Mollie](https://www.mollie.com), one of Europe's leading payment service providers — offering 40+ payment methods across the Payments API and the Orders API (for Klarna and other Buy Now Pay Later methods).

> **Status: Work in progress.** The module skeleton is in place but the payment flow is not yet implemented. See the `TODO` markers in the source — logic is being ported from the official [Mollie Magento 2 module](https://github.com/mollie/magento2).

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

## Roadmap

### API layer
- [ ] Payments API integration (iDEAL, Bancontact, SOFORT, Giropay, EPS, KBC, Belfius, Przelewy24, PayPal, SEPA, Credit Card, etc.)
- [ ] Orders API integration (Klarna Pay Now/Pay Later/Slice It, in3, Billie, Alma, Riverty)
- [ ] Apple Pay (domain association + JS button)
- [ ] Google Pay

### Payment methods (initial set)
- [ ] iDEAL
- [ ] Credit card
- [ ] Bancontact
- [ ] PayPal
- [ ] SOFORT Banking
- [ ] SEPA Direct Debit
- [ ] Klarna Pay Later (Orders API)
- [ ] Apple Pay
- [ ] Google Pay
- [ ] Przelewy24

### Features
- [ ] Full + partial refunds from admin
- [ ] Webhook-driven payment status reconciliation
- [ ] Cron-based safety net for missed webhooks
- [ ] Multi-store API key scoping
- [ ] Multi-currency support

## License

This module is licensed under the [Open Software License v3.0](LICENSE.txt).

## Links

- [Maho Commerce](https://mahocommerce.com)
- [Mollie](https://www.mollie.com)
- [Mollie API documentation](https://docs.mollie.com/)
- [Mollie Magento 2 module (reference for business logic)](https://github.com/mollie/magento2)
