# Changelog

All notable changes to this project are documented here.

## 1.5.0 - Public GitHub release

- Renamed the public-facing plugin to **WooCommerce Delivery Date Scheduler**.
- Added a complete English/Arabic README and searchable project metadata.
- Changed translatable source strings to English and added Arabic translation files.
- Added a WooCommerce dependency notice.
- Declared WooCommerce HPOS compatibility.
- Explicitly declared WooCommerce Cart/Checkout Blocks unsupported for now.
- Reworked the daily due-order query into bounded batches to reduce memory usage on larger stores.
- Corrected the daily cron schedule so the first run is scheduled for 00:10 in the WordPress site timezone.
- Added GPL license, uninstall cleanup, GitHub issue templates, security policy, contribution guide and PHP lint workflow.
- Preserved existing `_rh_*` order meta and `rh_*` option keys for compatibility with previous installs.

## 1.4.2

- Previous private release.
