<?php
/**
 * Uninstall cleanup for WooCommerce Delivery Date Scheduler.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('rh_custom_delivery_min_days');
delete_option('rh_custom_delivery_window_days');
wp_clear_scheduled_hook('rh_cdd_cron_check_delivery');

// Historical order metadata is intentionally retained to preserve order records.
