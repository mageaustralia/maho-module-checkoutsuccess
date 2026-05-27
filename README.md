# MageAustralia_CheckoutSuccess

A configurable, modular checkout success page for [Maho](https://github.com/mahocommerce/maho) 26.5+.

Replaces the spartan stock success page (order number link + Continue Shopping button) with a four-slot board layout the admin can compose: thank-you banner, line items + totals, shipping/billing addresses, guest quick-register CTA, recurring profile schedule, four CMS-block slots, and a custom HTML/tracking-snippet box with order-variable substitution. Includes a signed admin preview that renders the success page for any historical order.

- PHP 8.3+, strict types throughout, modern Maho attribute observers/routes
- No Prototype, no Zend, no Varien_Data on JS — vanilla ES2017 only
- One database-free `install` (pure config), zero schema changes
- BSD-2-Clause

## Install

```bash
composer config repositories.maho-module-checkoutsuccess vcs https://github.com/mageaustralia/maho-module-checkoutsuccess.git
COMPOSER_ROOT_VERSION=26.5.x-dev composer require mageaustralia/maho-module-checkoutsuccess:dev-main
./maho cache:flush
```

The `COMPOSER_ROOT_VERSION` env var lets the `mahocommerce/maho ^26.5` constraint resolve against a `dev-main` checkout of the Maho fork.

If your install drops module code into `app/code/community/` directly rather than via Composer, the maho-composer-plugin may not auto-wire the module declaration. In that case, after `composer require` also symlink the code dir and copy the declaration:

```bash
ln -s ../../../../vendor/mageaustralia/maho-module-checkoutsuccess/app/code/community/MageAustralia/CheckoutSuccess \
      app/code/community/MageAustralia/CheckoutSuccess
cp vendor/mageaustralia/maho-module-checkoutsuccess/app/etc/modules/MageAustralia_CheckoutSuccess.xml \
   app/etc/modules/
rsync -a vendor/mageaustralia/maho-module-checkoutsuccess/app/design/ app/design/
rsync -a vendor/mageaustralia/maho-module-checkoutsuccess/public/skin/ public/skin/
rsync -a vendor/mageaustralia/maho-module-checkoutsuccess/public/js/ public/js/
cp vendor/mageaustralia/maho-module-checkoutsuccess/app/locale/en_US/MageAustralia_CheckoutSuccess.csv \
   app/locale/en_US/
./maho cache:flush
```

## Configure

**System > Configuration > Sales > Checkout Success Page**.

| Group | Field | What it does |
|---|---|---|
| General | Enabled | Master switch. When off, the success page renders the stock fallback template. Module ships disabled — flip to Yes after install. |
| General | Show full addresses | Off = compact `Name, City` summary. On = full multi-line shipping + billing address block. |
| General | Show product thumbnails in items table | 90x90 thumbnail per line item. |
| General | CMS Block above the grid | Renders an admin-managed CMS block immediately above the slot grid. |
| General | CMS Block below the grid | Renders an admin-managed CMS block immediately below the slot grid (but above the bottom HTML / Continue button). |
| General | Custom HTML at the bottom | Raw HTML/JS appended after the success page. Supports variable substitution (see below). |
| Block Layout | Top / Middle-left / Middle-right / Bottom slot | Check the blocks you want in each slot. Drag the handles to reorder within a slot. The hidden form value is a comma-separated CSV of block codes. |
| Block Layout | Order # to preview | Enter an order number, click "Open Preview in New Tab" — module signs the URL with the install crypt key and opens the success page as it would render for that order. |
| Additional CMS Blocks | Block #1..#4 | CMS blocks rendered inside the "Additional" block in this order. |

### `Custom HTML at the bottom` variables

`{{orderId}}`, `{{orderIncrementId}}`, `{{orderAmount}}`, `{{customerEmail}}` are substituted at render time. **Values are HTML-escaped before substitution** to prevent a customer-controlled field (like the email) from breaking out of the surrounding markup. For tracking pixels / GTM / GA event snippets this is the expected behaviour — the substituted values are still safe to drop into JS string literals.

Typical use:

```html
<script>
gtag('event', 'purchase', {
    transaction_id: '{{orderIncrementId}}',
    value: parseFloat('{{orderAmount}}'),
    currency: 'AUD'
});
</script>
```

### Available block codes

The per-slot picker offers a fixed list (changing it requires registering the block in `app/design/frontend/base/default/layout/mageaustralia_checkoutsuccess.xml`):

| Code | Block |
|---|---|
| `checkoutsuccess.thank.you` | Thank-you message + tick-mark SVG + order # link |
| `checkoutsuccess.quick.register` | "Create account for next time" CTA (guests only, hidden if the email is already a registered customer) |
| `sales.order.view` | Order line items + totals (subclasses `Mage_Sales_Block_Order_Items`) |
| `sales.order.info` | Shipping + billing addresses (subclasses `Mage_Sales_Block_Order_Info`) |
| `checkoutsuccess.additional` | The four "Additional CMS Blocks" slots, stacked |
| `sales.recurring.profile.schedule` | Recurring profile summary (subscription products only) |

## Admin preview

The preview field on the Block Layout group lets you render the success page for any historical order without placing a new test order. Mechanics:

1. Admin enters an order number, clicks the button.
2. `PreviewController::urlAction` (admin-area, `system/config/mageaustralia_checkoutsuccess` ACL) signs the order's increment_id with HMAC-SHA256 keyed by the install crypt key, returns the URL.
3. JS opens a new tab pointing at `/checkout/onepage/success/?previewObjectId=<id>&previewSig=<hmac>&___store=<code>`.
4. Frontend predispatch observer verifies the signature, primes `checkout/session` with the historical order's IDs + registers `current_order`, and the page renders.

The HMAC is the only auth — admin sessions aren't visible cross-area in Maho. A leaked preview URL exposes one order, no more.

## Quick Register flow

On the success page, guests see a "Create account for next time" CTA. The form posts to `/mageaustralia_checkoutsuccess/quick/register` and:

- Validates form_key (CSRF).
- Refuses if the customer is already logged in.
- Refuses if there's no order in `checkout/session` (must come from the success flow).
- Pulls the email **from the order**, not from the form — guests can't claim a different email.
- Refuses if a customer with that email already exists.
- Requires password ≥ 8 chars and `confirmation` to match.
- Creates the customer, logs them in, and re-assigns any other matching guest orders (same `customer_email`, `customer_id IS NULL`) to the new customer so they appear in My Orders.

The guest-order claim step matches Magento's general guest-to-customer linking semantics: anyone who places a guest order and then registers with that email inherits previous guest orders for that email. If you don't want this, the only path that triggers it is this controller's `registerAction` — remove the `Mage::getResourceModel('sales/order_collection')->...->walk(...)` block in `controllers/QuickController.php`.

## Files

| Path | Purpose |
|---|---|
| `app/etc/modules/MageAustralia_CheckoutSuccess.xml` | Module declaration |
| `app/code/community/MageAustralia/CheckoutSuccess/` | PHP source |
| `app/design/frontend/base/default/{layout,template}/` | Frontend layout + templates |
| `app/design/adminhtml/default/default/layout/mageaustralia_checkoutsuccess.xml` | Loads the sortable.js + admin.css on the config-edit screen |
| `app/locale/en_US/MageAustralia_CheckoutSuccess.csv` | Translatable strings |
| `public/skin/frontend/base/default/css/mageaustralia/checkoutsuccess.css` | Frontend grid styling |
| `public/skin/adminhtml/default/default/mageaustralia/checkoutsuccess/admin.css` | Sortable field styling |
| `public/js/mageaustralia/checkoutsuccess/sortable.js` | Vanilla HTML5 drag-and-drop for the slot picker |
| `tests/Frontend/Integration/MageAustralia/CheckoutSuccessTest.php` | Pest integration test |

## Disable / uninstall

To disable the module without removing it:

- **System > Configuration > Sales > Checkout Success Page > General > Enabled = No**, then **`./maho cache:flush`**. The frontend falls back to the stock template.

To uninstall completely:

```bash
composer remove mageaustralia/maho-module-checkoutsuccess
./maho cache:flush
```

If you used the manual symlink/copy path, remove the symlink, declaration XML, design files, skin/js assets, and locale CSV.

## Security model — summary

- All admin endpoints are in the adminhtml area → automatic admin auth + ACL (`system/config/mageaustralia_checkoutsuccess`).
- Admin endpoint forces `form_key` validation (`_setForcedFormKeyActions`).
- The preview URL is signed with HMAC-SHA256 keyed by the Maho crypt key; verification is constant-time via `hash_equals`.
- The guest `registerAction` validates `form_key`, takes email from the order (not the POST), and refuses if the email already has an account.
- The custom-HTML token substitution HTML-escapes order values before substitution (prevents stored XSS via crafted customer email).
- The frontend success controller's `reviewAction` is **POST-only** in core Maho. The module doesn't change that.

## Compatibility

- Maho 26.5+ (uses `#[Maho\Config\Route]` + `#[Maho\Config\Observer]` attribute routing — requires Maho 26+ for these to be picked up by the route compiler).
- PHP 8.3+.

## Development

```bash
composer install
./vendor/bin/pest tests/Frontend/Integration/MageAustralia/CheckoutSuccessTest.php
```

CI: see `.github/workflows/ci.yml` — composer-validate + php-l + the maho-ci removed-Zend/Varien/Prototype scan via the shared `mageaustralia/maho-ci` reusable workflow.
