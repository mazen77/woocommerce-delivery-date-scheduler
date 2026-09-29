# WooCommerce Delivery Date Scheduler

A lightweight WooCommerce plugin that lets customers choose a delivery date during checkout and automatically moves scheduled orders into a packing status when the delivery date arrives.

**English + Arabic / RTL support • HPOS compatible • No external APIs • No tracking**

## What it does

WooCommerce Delivery Date Scheduler adds an optional delivery-date selector to the **classic WooCommerce checkout**. Store owners can configure the minimum lead time and how many date choices customers see.

When a customer chooses a date, the plugin:

- saves the selected date to the WooCommerce order;
- shows it on the thank-you page and My Account order view;
- includes it in WooCommerce order emails;
- adds a Delivery Date column to the WooCommerce orders screen;
- supports both legacy order storage and WooCommerce HPOS;
- changes the order to **Scheduled Delivery** after checkout/payment flow;
- automatically moves due scheduled orders to **Packing** using WP-Cron;
- localizes day/month names using the WordPress site locale;
- includes an Arabic translation and works with RTL layouts.

## ماذا تفعل الإضافة؟

إضافة خفيفة لووكومرس تسمح للعميل باختيار **موعد التوصيل** أثناء إتمام الطلب، مع إمكانية تحديد الحد الأدنى لعدد الأيام وعدد المواعيد المعروضة من لوحة التحكم.

عند اختيار الموعد تقوم الإضافة بـ:

- حفظ موعد التوصيل داخل الطلب؛
- عرضه في صفحة تأكيد الطلب وصفحة الطلب في حساب العميل؛
- إظهاره في رسائل البريد الخاصة بالطلب؛
- إضافة عمود لموعد التوصيل في صفحة طلبات ووكومرس؛
- دعم WooCommerce HPOS؛
- تحويل الطلب إلى حالة **مجدول التسليم** بعد إتمام الطلب/الدفع؛
- تحويل الطلب تلقائياً إلى **قيد التجهيز** عند حلول موعد التوصيل عبر WP-Cron؛
- عرض أسماء الأيام والأشهر حسب لغة ووردبريس؛
- توفير ترجمة عربية ودعم اتجاه RTL.

## Settings

Go to:

**WooCommerce → Delivery Date Settings**

You can configure:

1. **Minimum days after order date** — for example, `3` means the first selectable date is today + 3 days.
2. **Number of delivery date options** — for example, `7` displays seven selectable date tiles.

## Order-status workflow

If a delivery date is selected:

`Processing / On hold / Completed` → `Scheduled Delivery` → `Packing`

If the selected delivery date is already today (or earlier), the plugin can move the order directly to `Packing`.

The automatic Scheduled Delivery → Packing transition runs through WordPress WP-Cron and processes due orders in bounded batches to avoid loading all scheduled orders into memory at once.

## Installation

1. Download the release ZIP.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and activate it.
4. Make sure WooCommerce is active.
5. Open **WooCommerce → Delivery Date Settings** and configure your delivery window.

## Compatibility

- WordPress: requires 6.4+
- PHP: requires 7.4+
- WooCommerce: requires 8.2+
- WooCommerce HPOS: declared compatible
- Classic WooCommerce checkout: supported
- WooCommerce Cart/Checkout Blocks: **not currently supported**

## Performance and privacy

The plugin does not call external services, load remote assets, create tracking requests, or send customer data anywhere. Checkout CSS/JavaScript is only enqueued on the checkout page. The scheduled-order job queries only due orders and processes them in batches.

## Stored data

The plugin stores:

- the selected delivery date in order meta;
- whether delivery-date scheduling was enabled for that order;
- two WordPress options for minimum lead time and number of date choices.

Uninstall removes the two plugin settings. Historical order delivery-date metadata is intentionally retained so old order records are not destroyed.

## Search keywords

WooCommerce delivery date, WooCommerce delivery scheduler, order delivery date, checkout delivery date, scheduled delivery, packing status, WooCommerce order status automation, Arabic WooCommerce, RTL WooCommerce, موعد التوصيل ووكومرس, جدولة التوصيل, تاريخ التوصيل, حالة الطلب, WooCommerce HPOS.

## License

GPL-2.0-or-later.

## Author

**Mazen Bassiso**  
Website: https://fa7ma.com
