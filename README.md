# Quick Instant Checkout

**Quick Instant Checkout** is a tiered package selector widget and high-converting 5-step order portal for WooCommerce. It streamlines account, bulk, and subscription sales by replacing the standard WooCommerce add-to-cart layout with an instant, interactive tiered checkout experience.

- **Author:** Foysal Hasan
- **WhatsApp Support:** [+8801521521499](https://wa.me/8801521521499)
- **Requires:** WordPress 6.0+, WooCommerce 7.5+, PHP 7.4+
- **HPOS Compatible:** Yes (High-Performance Order Storage compatible)

---

## Table of Contents

1. [Features](#features)
2. [How to Add a Variable Product with Quick Instant Checkout](#how-to-add-a-variable-product-with-quick-instant-checkout)
   - [Step 1: Create a Variable Product](#step-1-create-a-variable-product)
   - [Step 2: Add Product Attributes](#step-2-add-product-attributes)
   - [Step 3: Generate and Configure Variations](#step-3-generate-and-configure-variations)
   - [Step 4: Enable Quick Instant Checkout](#step-4-enable-quick-instant-checkout)
3. [Simple Products Support](#simple-products-support)
4. [Order Portal Modal Overview](#order-portal-modal-overview)
5. [Global Plugin Settings](#global-plugin-settings)
6. [Shortcode & Elementor Usage](#shortcode--elementor-usage)
7. [Author & Support](#author--support)

---

## Features

- **Tiered Package Cards:** Showcases available quantity packages with account counts, live total price, per-account unit price, and savings badges.
- **Instant Order Portal Modal:** Interactive multi-step checkout dialog (Package Tier, Contact, Payment Method, Verification, and Confirmation).
- **Manual Crypto Verification:** Integrated cryptocurrency payment options (USDT TRC-20, ERC-20, BTC, LTC, etc.) with copyable wallet addresses, QR codes, and transaction hash / payment proof upload.
- **Customizable Theming:** Color presets (Red, Emerald, Indigo, Amber, Slate, Custom Hex) and Google Font loading options.
- **Theme & Page Builder Friendly:** Works with standard WooCommerce product templates, Elementor, and custom page builders.

---

## How to Add a Variable Product with Quick Instant Checkout

Follow these steps to set up a variable product that renders as tiered packages in the Quick Instant Checkout widget and Order Portal.

### Step 1: Create a Variable Product

1. In your WordPress admin dashboard, navigate to **Products > Add New** (or edit an existing product).
2. Enter the **Product Name** (e.g., *Twitter Accounts (PVA)*).
3. Scroll down to the **Product Data** meta box.
4. Open the dropdown next to **Product Data** and select **Variable product**.

---

### Step 2: Add Product Attributes

1. In the **Product Data** box, click the **Attributes** tab on the left.
2. Click **Add new** (or select a global attribute if you created one under *Products > Attributes*).
3. Set the **Name** to `Package` or `Quantity`.
4. In the **Value(s)** textarea, enter your tier levels separated by a pipe (`|`). For example:
   ```text
   1 Account | 5 Accounts | 10 Accounts | 25 Accounts | 50 Accounts
   ```
5. Ensure the checkbox **Used for variations** is **checked** (`[x]`).
6. Click **Save attributes**.

---

### Step 3: Generate and Configure Variations

1. Click the **Variations** tab on the left.
2. Select **Generate variations** (or **Add variation**) and click **Go**.
3. WooCommerce will generate a variation for each package attribute you defined.
4. Expand each variation to configure its settings:
   - **Regular Price ($):** Set the regular price for this package (e.g., `$10.00`).
   - **Sale Price ($) (Optional):** Setting a sale price will automatically display the `"Save $X"` badge.
   - **Stock Status:** Make sure it is marked as **In stock**.
5. Inside each variation, configure the **Quick Instant Checkout custom fields**:
   - **Accounts in package:** The numeric count of accounts/units for this tier (e.g., `5`). If left blank, the plugin automatically extracts numbers from the variation label.
   - **Badge (e.g. Save 10%):** Optional text badge displayed on the tier (e.g., `POPULAR`, `BEST VALUE`, `SAVE 20%`).
   - **Ribbon (e.g. POPULAR):** Optional ribbon displayed across the top of the portal tile in the modal.
   - **Default selected tier:** Check this on the tier you want pre-selected when visitors view the product page.
6. Click **Save changes** at the bottom of the Variations tab.

---

### Step 4: Enable Quick Instant Checkout

1. Switch to the **General** tab in the **Product Data** box.
2. Check the box **Enable Quick Instant Checkout widget** (`[x]`).
3. *(Optional)* Configure product-level override fields:
   - **Dispatch label (summary row):** e.g., `Instant (< 3 minutes)`
   - **Warranty label (summary row):** e.g., `72 Hours Replacement Guaranteed`
   - **Portal tagline:** e.g., `Best Seller • Aged USA IP` (shown under the category in the Order Portal modal)
   - **Portal icon / emoji:** e.g., `🔥`
4. Click **Publish** or **Update** to save the product.

---

## Simple Products Support

Quick Instant Checkout also works with **Simple products**:

1. Under **Product Data**, select **Simple product**.
2. In the **General** tab, check **Enable Quick Instant Checkout widget**.
3. Set **Regular price** and optional **Sale price**.
4. Configure the **Accounts in package (simple product)** field (defaults to `1`).
5. Set an optional **Badge** and **Ribbon**.
6. Save and publish.

---

## Order Portal Modal Overview

When a customer clicks the call-to-action button (e.g., *Proceed to Payment ($25.00)*), the plugin opens the **Order Portal Modal**:

1. **Step 1: Package Selection:**
   - Visual category cards with product selection.
   - 5-column quantity tier tiles showing unit price and discount badges.
   - Custom quantity counter for customers wanting arbitrary order sizes.
   - Live summary strip displaying package quantity, unit cost, and total due.
2. **Step 2: Contact Details:**
   - Contact email address for automated delivery.
   - Username or delivery notes field.
3. **Step 3: Payment Gateway:**
   - Choose between cryptocurrency protocols (USDT TRC20, USDT ERC20, BTC, LTC, etc.) or standard WooCommerce gateways.
   - Instant cryptocurrency discounts (if configured).
4. **Step 4: Verification & Payment Proof:**
   - Copyable wallet address and QR code display.
   - Transaction hash input or receipt upload dropzone.
5. **Step 5: Order Confirmation:**
   - Centered order number confirmation and automatic dispatch notification.

---

## Global Plugin Settings

Navigate to **WooCommerce > Quick Checkout** in your WordPress dashboard to manage global configurations:

- **Widget & Store Text:** Default dispatch text, warranty text, button label template (supports `{price}` placeholder), and sub-button footnote.
- **Appearance & Colors:** Preset color themes (Red, Emerald, Indigo, Amber, Slate) or custom hex color accent.
- **Google Fonts:** Toggle Plus Jakarta Sans and Inter font loading.
- **Crypto Protocols:** Add, remove, and reorder crypto payment options with custom network names, wallet addresses, and badges.
- **Terms & Policies:** Custom checkout terms text and order status options for manual verification.

---

## Shortcode & Elementor Usage

You can embed the Quick Instant Checkout widget anywhere using:

```text
[qic_widget id="123"]
```
*(Replace `123` with your WooCommerce Product ID).*

To open the Order Portal modal from any custom button, link, or Elementor widget:
```html
<a href="#" data-qc-product-id="123" class="btn">Order Now</a>
```

---

## Author & Support

Developed by **Foysal Hasan**.

For custom development, inquiries, or support:
- **WhatsApp:** [+8801521521499](https://wa.me/8801521521499)
- **Direct WhatsApp Chat:** [https://wa.me/8801521521499](https://wa.me/8801521521499)
