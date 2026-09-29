=== WooCommerce Delivery Date Scheduler ===
Contributors: mazen
Tags: woocommerce, delivery date, delivery scheduler, order status, arabic, rtl, hpos
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight WooCommerce delivery-date scheduling with checkout date tiles, Arabic/RTL support, HPOS, admin/email display, and automatic order-status workflow.

Author: Mazen Bassiso — https://fa7ma.com

== Description ==

WooCommerce Delivery Date Scheduler adds an optional delivery-date selector to the classic WooCommerce checkout.

Store owners can configure the minimum number of days before the first available delivery date and the number of delivery-date tiles shown to customers.

When a customer selects a date, the plugin saves it to the order, displays it in order details and emails, adds an admin orders column, changes the order to Scheduled Delivery after the checkout/payment flow, and automatically moves due orders to Packing using WP-Cron.

Features:

* Lightweight checkout date tiles.
* Configurable minimum lead time.
* Configurable number of date options.
* Scheduled Delivery and Packing statuses.
* Automatic due-order transition using WP-Cron.
* HPOS-compatible order admin column.
* Delivery date in order emails, thank-you page, My Account and admin order view.
* English base strings with included Arabic translation.
* RTL-friendly layout and locale-aware day/month names.
* No external APIs, remote assets or tracking.

Arabic / العربية:

تضيف الإضافة خياراً اختيارياً لاختيار موعد التوصيل داخل صفحة الدفع التقليدية في ووكومرس. يمكن تحديد الحد الأدنى للأيام وعدد المواعيد المعروضة، ويتم حفظ الموعد داخل الطلب وعرضه في البريد وصفحة الطلب ولوحة التحكم. كما تتحول الطلبات المجدولة تلقائياً من "مجدول التسليم" إلى "قيد التجهيز" عند حلول موعد التوصيل.

Important: the current checkout UI supports the classic WooCommerce checkout. WooCommerce Cart/Checkout Blocks are not currently supported.

== Installation ==

1. Upload the plugin ZIP from Plugins > Add New > Upload Plugin.
2. Activate WooCommerce first, then activate this plugin.
3. Go to WooCommerce > Delivery Date Settings.
4. Configure the minimum lead time and number of delivery-date options.

== Frequently Asked Questions ==

= Does it support HPOS? =

Yes. The plugin declares WooCommerce custom order table (HPOS) compatibility and uses WooCommerce order APIs.

= Does it support the WooCommerce Checkout Block? =

Not currently. The date selector uses classic WooCommerce checkout hooks.

= Does it send data to an external service? =

No. The plugin does not call external APIs and does not include analytics or tracking.

= How are scheduled orders updated? =

A daily WP-Cron event finds due orders in Scheduled Delivery status and moves them to Packing in bounded batches.

== Changelog ==

= 1.5.0 =
* Prepared the plugin for public GitHub distribution.
* Added English base UI strings and Arabic translation files.
* Added WooCommerce dependency notice.
* Declared HPOS compatibility and declared Checkout Blocks unsupported.
* Changed the due-order cron query to bounded batches.
* Corrected the scheduled cron start time to 00:10 in the WordPress site timezone.
* Added public documentation, licensing, security and contribution files.

= 1.4.2 =
* Previous private release.
