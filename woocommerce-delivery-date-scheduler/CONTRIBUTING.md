# Contributing

Contributions are welcome.

1. Fork the repository and create a focused branch.
2. Keep changes backward compatible with the existing `_rh_*` order meta and `rh_*` option keys whenever possible.
3. Do not introduce external tracking, remote assets, or unnecessary background jobs.
4. Sanitize input, escape output, and use WooCommerce order APIs instead of direct order-table queries.
5. Run `php -l custom-delivery-date-checkbox.php` before opening a pull request.
6. Describe how the change was tested, including classic checkout and HPOS when relevant.

Please keep pull requests small and explain any change to order statuses or checkout behavior clearly.
