# Campus Bakery POS

A web-based food ordering and point-of-sale (POS) system for a campus bakery.
Built with plain **PHP 8** and **SQLite (PDO)** for IT 415 – Application Development and Emerging Technologies, Midterm Practical Exam.

## 1. Folder structure

```
midterm/
├── config.php            Session, database, tables, seed data, helper functions   (changed)
├── index.php             Product list / ordering screen with category tabs          (changed)
├── cart.php              Cart actions and the order summary table                   (changed)
├── payment.php           Payment screen: quick cash, validation, saving the order   (changed)
├── payment_success.php   Payment success screen with the computed change            (NEW)
├── receipt.php           Paper-style printable receipt                              (changed)
├── includes/
│   ├── header.php        Shared <head>, navigation, progress steps, flash message   (changed)
│   └── footer.php        Shared footer, closes the page
├── assets/
│   └── style.css         All the styling (hand-written CSS, no framework)           (changed)
└── data/
    └── bakery.db         SQLite database (created automatically on first run)
```

## 2. How to run

1. Install PHP 8. Check it with `php -v`.
2. Make sure the SQLite driver is on: `php -m` must list `pdo_sqlite`.
   If it is missing, open `php.ini` and remove the `;` in front of `extension=pdo_sqlite`.
3. Open a terminal in the project folder (the folder that has `index.php`).
4. Start the built-in server:
   ```
   php -S localhost:8000
   ```
5. Open **http://localhost:8000** in the browser.
6. On the first visit, `data/bakery.db` is created and the 12 products are added automatically.
   To reset everything, stop the server and delete the `data` folder.

## 3. Design improvements per screen

**All screens**
- A progress bar (Order → Cart → Payment → Receipt) at the top. Finished steps show a green check, and the current step is highlighted in caramel.
- One palette: cream background, caramel/brown accents, soft shadows, and 12–16px rounded corners.
- Prices use equal-width digits so they line up. Buttons have hover and pressed effects, and keyboard users see a clear focus outline.
- Phone layout: the page becomes a single column with no sideways scrolling, and the steps shrink to number + label.

**Product list / ordering**
- Category tabs: All, Breads, Pastries, Cakes & Desserts.
- Larger prices, a category label above each name, and a green "In cart: N" badge.
- The cart panel shows the item count and an empty-cart message.

**Cart / order summary**
- Table with item, unit price, − / + quantity, subtotal, and Remove.
- "Total items (pcs)" and the grand total shown at the bottom.

**Payment**
- Order summary on the left, total due in large text on the right.
- Quick-cash buttons: Exact amount, ₱100, ₱200, ₱500, ₱1,000.
- A rejected payment shows a red box with the total due, the amount entered, and how much is still owed. The cash field gets a red border.

**Payment success (new)**
- Green check icon, "Payment successful", and the transaction reference.
- Total and amount paid, then the change in a large green box.
- "View receipt" and "New order" buttons.

**Receipt**
- Narrow white paper with a torn zigzag bottom edge, a centered header, and dashed dividers.
- Columns: item, quantity, unit price, subtotal. Then the total, amount paid, and change.
- When printed, only the receipt appears.

## 4. Screenshot checklist (for the documentation)

Start the server, then open http://localhost:8000.

1. **Product list / ordering screen**: Add 6 × Pandesal, 2 × Ensaymada, and 1 × Chocolate Cake Slice so the "In cart" badges and the cart panel show items. Click the **All** tab and take the screenshot.
2. **Cart / order summary**: Click **Review cart** (or **Cart** in the top bar). Take the screenshot showing the items, quantities, subtotals, total items, and grand total.
3. **Rejected payment (optional)**: Click **Proceed to payment**. Click the **₱100** quick-cash button (or type `100`), then **Pay now**. Take the screenshot of the red "Insufficient cash" box. The cart is not changed.
4. **Successful payment with change**: On the same screen click **₱200** (or type `200`), then **Pay now**. Take the screenshot of the "Payment successful" screen with the change (₱55.00 for this order).
5. **Receipt**: Click **View receipt**. Take the screenshot showing the items, total, amount paid, change, and the `TXN-YYYYMMDD-0001` reference. You can also click **Print receipt** to show the print preview.

## 5. How each file works

**config.php**
- Loaded first by every page. Starts the session and sets the timezone to Asia/Manila.
- Connects to SQLite with PDO, creates the `products`, `orders`, and `order_items` tables, and adds the 12 products if the table is empty.
- Helper functions: `e()` (escapes output), `peso()` (₱ format), `redirect()`, `setFlash()`/`getFlash()`, `findProduct()`, `findOrder()` (loads one order by id), `getLastOrder()` (loads the order that was just paid, using `$_SESSION['last_order_id']`), `validQuantity()`, `getCartItems()`, `getCartTotal()`, `cartCount()`.

**includes/header.php**
- Every page sets `$pageTitle`, `$activePage`, and `$currentStep` (1–4) before including it.
- Prints the navigation bar, then the progress steps. A `foreach` loop gives each step the class `done`, `current`, or `todo` by comparing its number with `$currentStep`.
- Shows the flash message, if there is one.

**index.php**
- Reads the selected tab from `?category=`. It only accepts a category that exists in the database (`in_array` check); anything else means "All".
- Loads the products with a prepared statement (filtered by category when one is selected).
- The "Add to cart" form also sends the current category, so `cart.php` can return to the same tab.

**cart.php**
- **POST part:** add, increase, decrease, remove, or clear. Then it redirects back (to `index.php?category=...` or `cart.php`).
- `new_order` starts a new transaction: it empties the cart and removes `last_order_id`, so the old payment confirmation and receipt can no longer be opened.
- **GET part:** shows the table, the total item count (`cartCount()`), and the grand total.

**payment.php**
- **Quick-cash button** (`quick` field): only fills in the cash box. "Exact amount" uses the cart total. Other values are accepted only if they are in the `$quickAmounts` list.
- **Pay now** (`cash` field): validates the cash. If it is too low, it computes `$shortBy = total − cash` and shows the red box with the total, the amount entered, and the amount still owed.
- If the cash is enough, it computes the change, saves the order and its items in one transaction with the `TXN-YYYYMMDD-0001` reference, clears the cart, saves the order id in `$_SESSION['last_order_id']`, and redirects to `payment_success.php`.

**payment_success.php** (new)
- Loads the order that was just paid with `getLastOrder()` and shows the total, amount paid, and change (Change = Amount Paid − Total Amount).
- If there is no paid order (for example after "New order"), it goes back to the order screen with a message.
- "New order" is a small form that sends `action=new_order` to `cart.php`.

**receipt.php**
- Loads the order with `getLastOrder()` and its items from `order_items` (prepared statements). If there is no paid order, it goes back to the order screen.
- Shows the paper-style receipt. "Print receipt" calls `window.print()`, and the print CSS hides everything except the receipt.

**assets/style.css**
- Colors are CSS variables in `:root`. Sections are labeled by screen: steps, tabs, product grid, cart, payment, success, receipt.
- `@media (max-width: 900px)` and `(max-width: 600px)` handle tablet and phone layouts. `@media print` prints only the receipt.

## 6. Database

| Table         | Columns |
|---------------|---------|
| `products`    | id, name, category, price |
| `orders`      | id, reference (unique), total, amount_paid, change_amount, created_at |
| `order_items` | id, order_id, product_id, product_name, unit_price, quantity, subtotal |

`order_items` stores a copy of the product name and price, so old receipts stay correct even if a price changes later.
Every query that uses data is a prepared statement. The `CREATE TABLE` statements use `exec()` because they contain no user input.

## 7. Validation (server side)

| Rule | Where | Message |
|------|-------|---------|
| Quantity is a whole number from 1 to 99 | `cart.php` (add), `validQuantity()` | "Quantity must be a whole number from 1 to 99." |
| One item cannot go over 99 in the cart | `cart.php` (add, increase) | "You can only have up to 99 of …" |
| An empty cart cannot be paid | `payment.php` | "The cart is empty. Add products before paying." |
| Blank cash | `payment.php` | "The cash amount is blank. Please enter the cash received." |
| Non-numeric cash (`abc`, `12a`) | `payment.php` (`is_numeric`) | "The cash amount must be a number… Letters and symbols are not allowed." |
| Negative cash (`-50`) | `payment.php` | "The cash amount cannot be negative." |
| Zero cash | `payment.php` | "The cash amount must be greater than zero." |
| More than 2 decimals or `1e3` format | `payment.php` (regex `^\d+(\.\d{1,2})?$`) | "Enter a plain peso amount with at most 2 decimal places…" |
| Cash is at least the total | `payment.php` | "Insufficient cash. The payment was rejected." + total, entered, still owed |
| Quick-cash value must be in the allowed list | `payment.php` | (ignored if not in the list) |
| Category tab must exist | `index.php` | (falls back to "All") |

All output goes through `e()` (`htmlspecialchars`) to prevent XSS.

## 8. New transaction

Clicking **New order** on the payment confirmation or the receipt starts a new transaction:
- the cart is emptied,
- the cash amount is gone (it is never stored),
- the last order id is removed from the session, so the old confirmation and receipt pages show "There is no receipt to show" instead.

The paid order stays saved in the database as a sales record.
