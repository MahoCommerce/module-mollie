---
name: check-upstream-parity
description: Compare this Maho Mollie module against the upstream mollie/magento2 module and produce a grouped gap report of missing features. Use when the user asks to check parity with mollie/magento2, find missing features, check what the upstream has that we don't, or keep this module up to date with upstream.
---

# Check parity against mollie/magento2

Upstream repo: https://github.com/mollie/magento2

This module is a Maho / Mage1-style port. Upstream is a Magento 2 module. Mapping is *conceptual*, never file-for-file:

| Upstream (M2)                             | Here (Maho / Mage1)                           |
|-------------------------------------------|-----------------------------------------------|
| `etc/config.xml`, `etc/payment.xml`       | `app/code/core/Maho/Mollie/etc/config.xml`    |
| `etc/adminhtml/system.xml`                | `app/code/core/Maho/Mollie/etc/system.xml`    |
| `Model/Client/Payments/*`, `Orders/*`     | `Model/Method/*.php`                          |
| `Controller/Checkout/*`                   | `controllers/*`                               |
| `Plugin/*`, `Observer/*` (events.xml)     | `config.xml <events>` + `Model/Observer.php`  |
| `etc/crontab.xml`                         | `config.xml <crontab>`                        |
| `view/frontend/web/js/view/payment/*`     | frontend blocks + templates                   |

Code won't match. Only features match.

## Step 1 — refresh the upstream cache

```bash
UPSTREAM=/tmp/mollie-magento2-upstream
if [ -d "$UPSTREAM/.git" ]; then
  git -C "$UPSTREAM" fetch --depth=1 origin main
  git -C "$UPSTREAM" reset --hard origin/main
else
  git clone --depth=1 https://github.com/mollie/magento2.git "$UPSTREAM"
fi
git -C "$UPSTREAM" log -1 --format='%h %s (%ci)'
```

Record the upstream commit hash in the report so we know the baseline.

## Step 2 — enumerate features on both sides, category by category

Work through each category below. For each one: list what upstream has, list what we have, mark the delta. Use Glob/Grep on `$UPSTREAM` and on `app/code/core/Maho/Mollie/`.

### A. Payment methods
- **Upstream source of truth:** `etc/config.xml` under `<payment>` (each `<mollie_methods_*>` node). Cross-check with `Model/Methods/` (or `Service/Mollie/MethodCodes.php` in recent versions) and with `Mollie/Subscriptions`, `Mollie/B2B`, `Mollie/Klarna` if vendored.
- **Here:** `<payment>` section of `app/code/core/Maho/Mollie/etc/config.xml` and files under `Model/Method/`.
- List every upstream method code and check presence locally. Note per-method capabilities: `can_refund`, `can_refund_partial_per_invoice`, `can_capture`, `can_void`, `can_use_for_multishipping`, `is_gateway`, min/max order total, allowed countries, currency constraints.

### B. Admin configuration (system.xml)
- **Upstream:** `etc/adminhtml/system.xml`.
- **Here:** `etc/system.xml`.
- For each upstream field: is it present here? Note missing groups (e.g. payment-surcharge, Apple Pay direct, components/hosted-fields, connection test button, webhook profile settings, logging verbosity).

### C. Webhook & return flow
- Upstream controllers under `Controller/Checkout/` (`Webhook`, `Success`, `Restore`, `Redirect`) and `Service/Mollie/TransactionProcessor`.
- Here: `controllers/` (webhook/return/process actions) and `Helper/Data.php` / `Model/Method/*`.
- Check: IP allowlisting, signature/CSRF handling, idempotency, "payment still open" handling, pending-payment order state, "order expired" handling, failed-payment restore-to-cart, second-chance email.

### D. Orders API vs Payments API
- Upstream supports both (`Model/Client/Orders` and `Model/Client/Payments`) with a switch. Check which API this module uses and whether the other is missing.
- Orders-API-only features to flag if absent: order lines, partial shipments, partial captures, voucher categorisation, Klarna line-item detail.

### E. Refunds, captures, voids, cancellations
- Upstream: `Service/Order/Refund.php`, `Service/Order/Transactions.php`, observer/plugin on `sales_order_creditmemo_save_after`, capture on invoice save.
- Here: check `Model/Observer.php`, method classes' `refund()` / `capture()` / `void()`.
- Flag: partial refund per credit-memo line, refund of shipping, multi-invoice captures, void before authorization expiry.

### F. Shipments (Orders API)
- Upstream ships `Service/Order/OrderCommentHistory`, `Observer/SalesOrderShipmentAfter`. Registers shipment against Mollie, supports partial.
- Flag whether we push shipments to Mollie at all.

### G. Cron jobs
- Upstream: `etc/crontab.xml` — pending-payment reminder, cancel-abandoned-orders, apple-pay domain refresh, second-chance email, capture-authorized, etc.
- Here: `<crontab>` in `config.xml` + `Model/Cron.php`.
- List each upstream job and tick it off.

### H. Apple Pay
- Domain-association file served at `/.well-known/apple-developer-merchantid-domain-association`.
- Express-checkout button on cart/product/minicart.
- ApplePay payment-session controller.
- Check each piece.

### I. Components / hosted card fields
- Upstream `view/frontend/web/js/view/payment/method-renderer/mollie_methods_creditcard.js` + `Service/Mollie/Components`.
- Flag if we only do redirect and upstream supports components.

### J. Vault / stored card
- Upstream: `Service/Mollie/DashboardUrl`, Mollie customer creation, token persistence, `vault_*` config.
- Flag if absent.

### K. Payment-link / manual payment
- Upstream: admin button to generate a Mollie payment link for an order, email template.

### L. Second-chance email
- Upstream: template + cron + config toggle under `second_chance_email`.

### M. Surcharge / payment fee
- Upstream: `payment_surcharge` config group, adds fee item to order.

### N. Multishipping
- Upstream enables multishipping only on redirect methods. Flag here.

### O. Subscriptions / recurring
- Upstream has a companion `Mollie_Subscriptions` module. Note presence as "missing add-on", not core gap.

### P. i18n
- Upstream: `i18n/*.csv`. Here: `app/locale/*/Mage_Mollie.csv` (or similar). Compare which locales exist and whether strings are current.

### Q. Logging & diagnostics
- Upstream: `Service/Mollie/MollieApiClient` logs to `var/log/mollie.log`, config toggle for verbose. Connection-test admin button.

### R. Order status mapping
- Upstream: configurable mapping for processing/pending/canceled, separate per-method status overrides. Compare config keys.

### S. Compatibility / environment
- PHP version, Mollie PHP SDK version pinned in `composer.json`. Flag stale SDK.

### T. Anything new since last parity check
- Run `git -C "$UPSTREAM" log --since='6 months ago' --format='%h %s' -- etc/ Model/ Service/ Controller/ | head -100` to surface recent upstream changes to guide the eyeball pass.

## Step 3 — produce the report

Write the report to `.claude/check-upstream-parity-report.md` (gitignored path — file is meant to be ephemeral).

Format:

```markdown
# Mollie upstream parity report

Generated: <date>
Upstream baseline: mollie/magento2 @ <short-sha> — <subject>
Local commit: <short-sha>

## Summary
- X missing features
- Y partial/needs-verification
- Z not applicable to Maho

## Gaps by category
### A. Payment methods
- [ ] `mollie_methods_klarnapaynow` — upstream: Model/Methods/Klarnapaynow.php, here: absent
- [x] `mollie_methods_ideal` — parity
- [?] `mollie_methods_creditcard` — here is redirect-only, upstream supports components (see §I)

### B. Admin configuration
...

(one section per category A–T; omit categories with zero gaps)

## Not applicable
Features that don't translate to Maho (e.g. M2 DI plugins, GraphQL resolvers) — list so the user knows they were considered, not forgotten.

## Suggested next actions
Top 3–5 gaps ranked by user impact (payment-method gaps first, then refund/shipment flows, then admin UX).
```

## Rules

- **Never** claim parity for a category without actually grepping both sides — assumptions from the mapping table at the top are not evidence.
- For each gap, cite the upstream file path so the user can jump to it.
- Do not modify the module during this skill. Report only.
- If the upstream fetch fails (network, rate-limit), stop and tell the user — don't produce a partial report labelled as complete.
- Note when upstream has moved a feature into a sibling package (`mollie/mollie-magento2-subscriptions`, `mollie/mollie-magento2-b2b`, `mollie/mollie-magento2-klarna`) — those are "missing add-on modules", not core gaps.
