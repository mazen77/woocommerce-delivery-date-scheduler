<?php
/**
 * Plugin Name: WooCommerce Delivery Date Scheduler
 * Description: Lightweight delivery-date scheduling for WooCommerce with localized checkout tiles, Arabic/RTL support, HPOS admin columns, email/order display, and automatic Scheduled Delivery/Packing statuses.
 * Version: 1.5.0
 * Author: Mazen Bassiso
 * Author URI: https://fa7ma.com
 * Text Domain: custom-delivery-date-checkbox
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

if (!defined('RH_CDD_VERSION')) {
    define('RH_CDD_VERSION', '1.5.0');
}

// Cron hook (daily) to move scheduled delivery orders to packing on the delivery date.
if (!defined('RH_CDD_CRON_HOOK')) {
    define('RH_CDD_CRON_HOOK', 'rh_cdd_cron_check_delivery');
}


class RH_Custom_Delivery_Date_Checkbox {
    const META_KEY_DATE    = '_rh_custom_delivery_date';
    const META_KEY_ENABLED = '_rh_custom_delivery_date_enabled';
    const OPTION_MIN_DAYS   = 'rh_custom_delivery_min_days';
    const OPTION_WINDOW_DAYS = 'rh_custom_delivery_window_days';

    // Custom statuses (slugs without 'wc-')
    const STATUS_SCHEDULED = 'scheduled-delivery';
    const STATUS_PACKING   = 'packing';

    public function __construct() {
        // Register / expose custom statuses (if missing)
        add_action('init', [$this, 'register_custom_statuses']);
        add_filter('wc_order_statuses', [$this, 'add_custom_statuses_to_list']);

        // Cron to move orders to Packing on delivery date
        add_action(RH_CDD_CRON_HOOK, [$this, 'cron_move_orders_to_packing']);

        // Settings page
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // Checkout fields
        add_action('woocommerce_after_order_notes', [$this, 'render_checkout_fields']);
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_fields']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_order_meta'], 20, 2);

        // Apply custom statuses after the order is safely created / paid (avoid breaking gateways)
        add_action('woocommerce_payment_complete', [$this, 'maybe_apply_delivery_status_on_payment_complete'], 20, 1);
        add_action('woocommerce_order_status_changed', [$this, 'maybe_apply_delivery_status_on_status_change'], 20, 4);

        // Frontend display: Thank you + My Account order details
        add_action('woocommerce_thankyou', [$this, 'display_order_delivery_date_on_frontend'], 20);
        add_action('woocommerce_view_order', [$this, 'display_order_delivery_date_on_frontend'], 20);

        // Admin display (order edit screen)
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_delivery_date_in_admin'], 20, 1);

        // Admin orders list column (legacy + HPOS)
        add_filter('manage_edit-shop_order_columns', [$this, 'add_orders_list_column'], 20);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_orders_list_column'], 20, 2);
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'add_orders_list_column_hpos'], 20);
        add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'render_orders_list_column_hpos'], 20, 2);

        // Emails
        add_filter('woocommerce_email_order_meta_fields', [$this, 'add_email_meta_fields'], 10, 3);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function get_min_days(): int {
        $val = get_option(self::OPTION_MIN_DAYS, 3);
        $val = is_numeric($val) ? (int)$val : 3;
        if ($val < 0) $val = 0;
        if ($val > 365) $val = 365;
        return $val;
    }


    public function get_window_days(): int {
        $val = get_option(self::OPTION_WINDOW_DAYS, 7);
        $val = is_numeric($val) ? (int)$val : 7;
        if ($val < 1) $val = 1;
        if ($val > 60) $val = 60; // sanity limit
        return $val;
    }

    /**
     * 7 options starting from today + min_days (site timezone).
     * Uses wp_date() with formats that localize to Arabic when site language is Arabic.
     */
    public function get_delivery_options(): array {
        $tz = wp_timezone();
        $min_days = $this->get_min_days();

        $start = new DateTime('now', $tz);
        $start->setTime(0,0,0);
        if ($min_days > 0) $start->modify('+' . $min_days . ' days');

        $out = [];
        $count = $this->get_window_days();
        for ($i=0; $i<$count; $i++) {
            $d = clone $start;
            if ($i > 0) $d->modify('+' . $i . ' days');

            $ts  = $d->getTimestamp();
            $iso = wp_date('Y-m-d', $ts, $tz);
            $dow = wp_date('l', $ts, $tz); // full day name (Arabic if locale)
            $day = wp_date('j', $ts, $tz); // 1-31
            $mon = wp_date('F', $ts, $tz); // full month name (Arabic if locale)

            $out[] = [
                'iso' => $iso,
                'dow' => $dow,
                'day' => $day,
                'mon' => $mon,
            ];
        }
        return $out;
    }

    public function enqueue_assets() {
        if (!function_exists('is_checkout') || !is_checkout()) return;

        // Ensure jQuery exists for our tiny script
        wp_enqueue_script('jquery');

        // Use our own handles so CSS always loads (some themes don't enqueue woocommerce-general reliably)
        wp_register_style('rh-cdd-style', false, [], RH_CDD_VERSION);
        wp_enqueue_style('rh-cdd-style');
        wp_add_inline_style('rh-cdd-style', $this->inline_css());

        wp_register_script('rh-cdd-script', false, ['jquery'], RH_CDD_VERSION, true);
        wp_enqueue_script('rh-cdd-script');
        wp_add_inline_script('rh-cdd-script', $this->inline_js());
    }

    private function inline_css() {
        return '
            .rh-delivery-date-wrap{
                margin: 16px 0;
                padding: 16px;
                border: 1px solid #e8e8e8;
                border-radius: 16px;
                background: #fff;
            }
            .rh-delivery-date-wrap h3{
                margin: 0 0 10px;
                font-size: 16px;
                font-weight: 800;
            }

            /* Checkbox row */
            .rh-delivery-date-wrap .form-row{
                margin: 0;
            }
            #rh_custom_delivery_date_enable_field label{
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-weight: 700;
            }

            .rh-delivery-date-row{ margin-top: 14px; display:none; }

            /* Grid tiles */
            .rh-dd-grid{
                display: grid;
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 10px;
            }
            @media (max-width: 1000px){ .rh-dd-grid{ grid-template-columns: repeat(4, minmax(0, 1fr)); } }
            @media (max-width: 560px){ .rh-dd-grid{ grid-template-columns: repeat(3, minmax(0, 1fr)); } }

            .rh-dd-choice{ position: relative; }

            /* Hide default radio completely */
            .rh-dd-choice input{
                position: absolute !important;
                opacity: 0 !important;
                width: 1px !important;
                height: 1px !important;
                overflow: hidden !important;
                pointer-events: none !important;
            }

            .rh-dd-tile{
                border: 1px solid #ededed;
                border-radius: 16px;
                background: #fff;
                padding: 10px 8px;
                min-height: 92px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 6px;
                text-align: center;
                cursor: pointer;
                box-shadow: 0 8px 24px rgba(0,0,0,.06);
                transition: transform .08s ease, box-shadow .12s ease, border-color .12s ease;
            }
            .rh-dd-tile:hover{
                transform: translateY(-1px);
                box-shadow: 0 12px 30px rgba(0,0,0,.10);
            }

            .rh-dd-dow{ font-size: 12px; opacity: .90; font-weight: 700; }
            .rh-dd-day{ font-size: 26px; font-weight: 900; line-height: 1; }
            .rh-dd-mon{ font-size: 12px; opacity: .90; font-weight: 700; }

            /* Selected state */
            .rh-dd-choice input:checked + .rh-dd-tile{
                border-color: #111;
                box-shadow: 0 14px 34px rgba(0,0,0,.14);
                transform: translateY(-1px);
            }

            /* Focus for accessibility */
            .rh-dd-choice input:focus-visible + .rh-dd-tile{
                outline: 3px solid rgba(17,17,17,.18);
                outline-offset: 2px;
            }

            .rh-delivery-date-badge{
                display:inline-block;
                padding: 8px 12px;
                border-radius: 999px;
                background:#f5f5f5;
                font-weight: 700;
                margin-top: 6px;
            }

            /* Remove any Woo radio list styling if theme adds it */
            .rh-dd-grid ul, .rh-dd-grid li{ list-style:none; margin:0; padding:0; }
        ';
    }

    private function inline_js() {
        return "
            jQuery(function($){
                function toggleRow(){
                    var \$checkbox = $('#rh_custom_delivery_date_enable');
                    var \$row = $('.rh-delivery-date-row');
                    if(\$checkbox.is(':checked')){
                        \$row.slideDown(150);
                    } else {
                        \$row.slideUp(150);
                        $('input[name=\"rh_custom_delivery_date_choice\"]').prop('checked', false);
                        $('#rh_custom_delivery_date').val('');
                    }
                }

                function syncHiddenDate(){
                    var v = $('input[name=\"rh_custom_delivery_date_choice\"]:checked').val() || '';
                    $('#rh_custom_delivery_date').val(v);
                }

                toggleRow();
                syncHiddenDate();

                $(document.body).on('change', '#rh_custom_delivery_date_enable', function(){
                    toggleRow();
                    syncHiddenDate();
                });

                $(document.body).on('change', 'input[name=\"rh_custom_delivery_date_choice\"]', function(){
                    syncHiddenDate();
                });

                $(document.body).on('updated_checkout', function(){
                    toggleRow();
                    syncHiddenDate();
                });
            });
        ";
    }


    /**
     * Register custom statuses if they don't already exist.
     * Slugs: scheduled-delivery (مجدول التسليم), packing (قيد التجهيز)
     */
    public function register_custom_statuses() {
        // Scheduled Delivery
        if (!get_post_status_object('wc-' . self::STATUS_SCHEDULED)) {
            register_post_status('wc-' . self::STATUS_SCHEDULED, [
                'label'                     => _x('Scheduled Delivery', 'Order status', 'custom-delivery-date-checkbox'),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Scheduled Delivery <span class="count">(%s)</span>', 'Scheduled Delivery <span class="count">(%s)</span>', 'custom-delivery-date-checkbox'),
            ]);
        }

        // Packing
        if (!get_post_status_object('wc-' . self::STATUS_PACKING)) {
            register_post_status('wc-' . self::STATUS_PACKING, [
                'label'                     => _x('Packing', 'Order status', 'custom-delivery-date-checkbox'),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Packing <span class="count">(%s)</span>', 'Packing <span class="count">(%s)</span>', 'custom-delivery-date-checkbox'),
            ]);
        }
    }

    /**
     * Make custom statuses appear in WooCommerce status lists.
     */
    public function add_custom_statuses_to_list($order_statuses) {
        if (!is_array($order_statuses)) return $order_statuses;

        // Insert after "processing" if possible; otherwise append.
        $new = [];
        foreach ($order_statuses as $k => $label) {
            $new[$k] = $label;

            if ($k === 'wc-processing') {
                $new['wc-' . self::STATUS_SCHEDULED] = _x('Scheduled Delivery', 'Order status', 'custom-delivery-date-checkbox');
                $new['wc-' . self::STATUS_PACKING]   = _x('Packing', 'Order status', 'custom-delivery-date-checkbox');
            }
        }

        if (!isset($new['wc-' . self::STATUS_SCHEDULED])) {
            $new['wc-' . self::STATUS_SCHEDULED] = _x('Scheduled Delivery', 'Order status', 'custom-delivery-date-checkbox');
        }
        if (!isset($new['wc-' . self::STATUS_PACKING])) {
            $new['wc-' . self::STATUS_PACKING] = _x('Packing', 'Order status', 'custom-delivery-date-checkbox');
        }

        return $new;
    }

    /**
     * Daily cron: move orders from "scheduled-delivery" to "packing" when delivery date is today or earlier.
     */
    public function cron_move_orders_to_packing() {
        if (!function_exists('wc_get_orders')) return;

        $statuses = wc_get_order_statuses();
        if (!is_array($statuses)) return;

        if (!array_key_exists('wc-' . self::STATUS_SCHEDULED, $statuses)) return;
        if (!array_key_exists('wc-' . self::STATUS_PACKING, $statuses)) return;

        $tz = wp_timezone();
        $today = wp_date('Y-m-d', (new DateTime('now', $tz))->getTimestamp(), $tz);

        // Process due orders in bounded batches so large stores do not load every
        // scheduled order into memory at once. Each status change removes the order
        // from the next query automatically.
        $batch_size  = 100;
        $max_batches = 10;

        for ($batch = 0; $batch < $max_batches; $batch++) {
            $orders = wc_get_orders([
                'status'     => [self::STATUS_SCHEDULED],
                'limit'      => $batch_size,
                'return'     => 'objects',
                'meta_query' => [
                    [
                        'key'     => self::META_KEY_ENABLED,
                        'value'   => 'yes',
                        'compare' => '=',
                    ],
                    [
                        'key'     => self::META_KEY_DATE,
                        'value'   => $today,
                        'compare' => '<=',
                        'type'    => 'DATE',
                    ],
                ],
            ]);

            if (empty($orders)) return;

            foreach ($orders as $order) {
                if (!$order instanceof WC_Order) continue;

                $date = (string) $order->get_meta(self::META_KEY_DATE);
                if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;

                $order->set_status(
                    self::STATUS_PACKING,
                    __('Auto-moved to packing on delivery date.', 'custom-delivery-date-checkbox'),
                    true
                );
                $order->save();
            }

            if (count($orders) < $batch_size) return;
        }
    }

    public function render_checkout_fields($checkout) {
        $options = $this->get_delivery_options();
        $current = $checkout->get_value('rh_custom_delivery_date');

        echo '<div class="rh-delivery-date-wrap">';
        echo '<h3>' . esc_html__('Delivery options', 'custom-delivery-date-checkbox') . '</h3>';

        woocommerce_form_field('rh_custom_delivery_date_enable', [
            'type'    => 'checkbox',
            'class'   => ['form-row-wide'],
            'label'   => esc_html__('Choose a delivery date (optional)', 'custom-delivery-date-checkbox'),
            'default' => 0,
        ], $checkout->get_value('rh_custom_delivery_date_enable'));

        // hidden actual value
        echo '<input type="hidden" id="rh_custom_delivery_date" name="rh_custom_delivery_date" value="' . esc_attr($current) . '" />';

        echo '<div class="rh-delivery-date-row">';
        echo '<div class="rh-dd-grid" role="radiogroup" aria-label="' . esc_attr__('Choose a delivery date', 'custom-delivery-date-checkbox') . '">';

        foreach ($options as $opt) {
            $iso = $opt['iso'];
            $id  = 'rh_dd_' . esc_attr(str_replace('-', '', $iso));
            $checked = ($current === $iso) ? 'checked' : '';
            echo '<div class="rh-dd-choice">';
            echo '  <input type="radio" id="' . esc_attr($id) . '" name="rh_custom_delivery_date_choice" value="' . esc_attr($iso) . '" ' . $checked . ' />';
            echo '  <label class="rh-dd-tile" for="' . esc_attr($id) . '">';
            echo '      <div class="rh-dd-dow">' . esc_html($opt['dow']) . '</div>';
            echo '      <div class="rh-dd-day">' . esc_html($opt['day']) . '</div>';
            echo '      <div class="rh-dd-mon">' . esc_html($opt['mon']) . '</div>';
            echo '  </label>';
            echo '</div>';
        }

        echo '</div>'; // grid
        echo '</div>'; // row
        echo '</div>'; // wrap
    }

    public function validate_checkout_fields() {
        $enabled = isset($_POST['rh_custom_delivery_date_enable']) && $_POST['rh_custom_delivery_date_enable'];
        if (!$enabled) return;

        $date = isset($_POST['rh_custom_delivery_date']) ? sanitize_text_field(wp_unslash($_POST['rh_custom_delivery_date'])) : '';
        if ($date === '') {
            wc_add_notice(__('Please choose a delivery date.', 'custom-delivery-date-checkbox'), 'error');
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wc_add_notice(__('Invalid delivery date format.', 'custom-delivery-date-checkbox'), 'error');
            return;
        }

        $valid = array_map(function($o){ return $o['iso']; }, $this->get_delivery_options());
        if (!in_array($date, $valid, true)) {
            wc_add_notice(__('Please choose one of the available delivery dates.', 'custom-delivery-date-checkbox'), 'error');
        }
    }

    public function save_order_meta($order, $data) {
        $enabled = isset($_POST['rh_custom_delivery_date_enable']) && $_POST['rh_custom_delivery_date_enable'];
        $date = isset($_POST['rh_custom_delivery_date']) ? sanitize_text_field(wp_unslash($_POST['rh_custom_delivery_date'])) : '';

        if ($enabled && $date) {
            $order->update_meta_data(self::META_KEY_ENABLED, 'yes');
            $order->update_meta_data(self::META_KEY_DATE, $date);
        } else {
            $order->update_meta_data(self::META_KEY_ENABLED, 'no');
            $order->delete_meta_data(self::META_KEY_DATE);
        }
    }


    /**
     * Apply delivery statuses ONLY after checkout/payment has progressed, to avoid breaking payment gateways.
     * - If selected delivery date is today/past => packing
     * - Else => scheduled-delivery
     */
    public function maybe_apply_delivery_status_on_payment_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        $this->maybe_apply_delivery_status($order);
    }

    public function maybe_apply_delivery_status_on_status_change($order_id, $old_status, $new_status, $order) {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) return;

        // Only run when order reaches a "post-checkout" status (common for COD/offline: on-hold; paid: processing/completed)
        $allowed_new = ['processing', 'on-hold', 'completed'];
        if (!in_array($new_status, $allowed_new, true)) return;

        $this->maybe_apply_delivery_status($order);
    }

    private function maybe_apply_delivery_status($order) {
        if (!$order instanceof WC_Order) return;

        // Do not interfere with terminal states
        if ($order->has_status(['cancelled', 'refunded', 'failed', 'trash'])) return;

        $enabled = $order->get_meta(self::META_KEY_ENABLED);
        $date    = $order->get_meta(self::META_KEY_DATE);

        if ($enabled !== 'yes' || empty($date)) return;

        $statuses = wc_get_order_statuses();
        if (!is_array($statuses)) return;

        // Ensure our custom statuses exist
        if (!array_key_exists('wc-' . self::STATUS_SCHEDULED, $statuses)) return;
        if (!array_key_exists('wc-' . self::STATUS_PACKING, $statuses)) return;

        // If already in one of our custom statuses, don't re-apply
        if ($order->has_status([self::STATUS_SCHEDULED, self::STATUS_PACKING])) return;

        $tz = wp_timezone();
        $today = wp_date('Y-m-d', (new DateTime('now', $tz))->getTimestamp(), $tz);

        $target = ($date <= $today) ? self::STATUS_PACKING : self::STATUS_SCHEDULED;

        // Change status with a note; this happens after checkout/payment so gateways won't choke.
        $order->update_status($target, __('Auto status based on scheduled delivery date.', 'custom-delivery-date-checkbox'), true);
    }


    public function display_order_delivery_date_on_frontend($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $date = $order->get_meta(self::META_KEY_DATE);
        if ($date) {
            echo '<section class="woocommerce-order-details" style="margin-top:14px;">';
            echo '<h2>' . esc_html__('Delivery date', 'custom-delivery-date-checkbox') . '</h2>';
            echo '<div class="rh-delivery-date-badge">'
                . esc_html($date)
                . '</div>';
            echo '</section>';
        }
    }

    public function display_order_delivery_date_in_admin($order) {
        if (!$order instanceof WC_Order) return;
        $date = $order->get_meta(self::META_KEY_DATE);

        echo '<p><strong>' . esc_html__('Delivery date:', 'custom-delivery-date-checkbox') . '</strong> ';
        echo $date ? esc_html($date) : '—';
        echo '</p>';
    }

    public function add_email_meta_fields($fields, $sent_to_admin, $order) {
        if (!$order instanceof WC_Order) return $fields;

        $date = $order->get_meta(self::META_KEY_DATE);
        if ($date) {
            $fields['rh_custom_delivery_date'] = [
                'label' => __('Delivery date', 'custom-delivery-date-checkbox'),
                'value' => $date,
            ];
        }
        return $fields;
    }

    public function add_orders_list_column($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'order_status') {
                $new['rh_delivery_date'] = __('Delivery date', 'custom-delivery-date-checkbox');
            }
        }
        if (!isset($new['rh_delivery_date'])) {
            $new['rh_delivery_date'] = __('Delivery date', 'custom-delivery-date-checkbox');
        }
        return $new;
    }

    public function render_orders_list_column($column, $post_id) {
        if ($column !== 'rh_delivery_date') return;
        $order = wc_get_order($post_id);
        if (!$order) { echo '—'; return; }
        $date = $order->get_meta(self::META_KEY_DATE);
        echo $date ? esc_html($date) : '—';
    }

    public function add_orders_list_column_hpos($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'order_status') {
                $new['rh_delivery_date'] = __('Delivery date', 'custom-delivery-date-checkbox');
            }
        }
        if (!isset($new['rh_delivery_date'])) {
            $new['rh_delivery_date'] = __('Delivery date', 'custom-delivery-date-checkbox');
        }
        return $new;
    }

    public function render_orders_list_column_hpos($column, $order) {
        if ($column !== 'rh_delivery_date') return;
        if (!$order instanceof WC_Order) { echo '—'; return; }
        $date = $order->get_meta(self::META_KEY_DATE);
        echo $date ? esc_html($date) : '—';
    }

    public function register_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Delivery Date Settings', 'custom-delivery-date-checkbox'),
            __('Delivery Date Settings', 'custom-delivery-date-checkbox'),
            'manage_woocommerce',
            'rh-custom-delivery-date',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('rh_cdd_settings_group', self::OPTION_MIN_DAYS, [
            'type' => 'integer',
            'sanitize_callback' => function($value){
                $value = is_numeric($value) ? (int)$value : 3;
                if ($value < 0) $value = 0;
                if ($value > 365) $value = 365;
                return $value;
            },
            'default' => 3,
        ]);

        register_setting('rh_cdd_settings_group', self::OPTION_WINDOW_DAYS, [
            'type' => 'integer',
            'sanitize_callback' => function($value){
                $value = is_numeric($value) ? (int)$value : 7;
                if ($value < 1) $value = 1;
                if ($value > 60) $value = 60;
                return $value;
            },
            'default' => 7,
        ]);

        add_settings_section(
            'rh_cdd_main_section',
            __('Checkout rules', 'custom-delivery-date-checkbox'),
            function(){
                echo '<p>' . esc_html__('Controls the first available delivery date offset and how many date tiles appear at checkout.', 'custom-delivery-date-checkbox') . '</p>';
            },
            'rh-custom-delivery-date'
        );

        add_settings_field(
            self::OPTION_MIN_DAYS,
            __('Minimum days after order date', 'custom-delivery-date-checkbox'),
            [$this, 'render_min_days_field'],
            'rh-custom-delivery-date',
            'rh_cdd_main_section'
        );

        add_settings_field(
            self::OPTION_WINDOW_DAYS,
            __('Number of delivery date options', 'custom-delivery-date-checkbox'),
            [$this, 'render_window_days_field'],
            'rh-custom-delivery-date',
            'rh_cdd_main_section'
        );

    }

    public function render_min_days_field() {
        $value = $this->get_min_days();
        echo '<input type="number" min="0" max="365" step="1" name="' . esc_attr(self::OPTION_MIN_DAYS) . '" value="' . esc_attr($value) . '" style="width:120px;" />';
        echo '<p class="description">' . esc_html__('Example: 3 means first option is today + 3 days.', 'custom-delivery-date-checkbox') . '</p>';

    }

    public function render_window_days_field() {
        $value = $this->get_window_days();
        echo '<input type="number" min="1" max="60" step="1" name="' . esc_attr(self::OPTION_WINDOW_DAYS) . '" value="' . esc_attr($value) . '" style="width:120px;" />';
        echo '<p class="description">' . esc_html__('Example: 7 means show 7 tiles starting from the first available date.', 'custom-delivery-date-checkbox') . '</p>';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'custom-delivery-date-checkbox'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Delivery Date Settings', 'custom-delivery-date-checkbox') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('rh_cdd_settings_group');
        do_settings_sections('rh-custom-delivery-date');
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}


/**
 * Schedule the daily cron on activation.
 */
function rh_cdd_activate() {
    if (!wp_next_scheduled(RH_CDD_CRON_HOOK)) {
        $tz = wp_timezone();
        $next_run = new DateTimeImmutable('tomorrow 00:10:00', $tz);
        wp_schedule_event($next_run->getTimestamp(), 'daily', RH_CDD_CRON_HOOK);
    }
}
register_activation_hook(__FILE__, 'rh_cdd_activate');

/**
 * Clear all plugin cron events on deactivation.
 */
function rh_cdd_deactivate() {
    wp_clear_scheduled_hook(RH_CDD_CRON_HOOK);
}
register_deactivation_hook(__FILE__, 'rh_cdd_deactivate');

/**
 * Declare WooCommerce feature compatibility.
 * The current checkout UI uses classic checkout hooks, so Cart/Checkout Blocks
 * are intentionally marked incompatible until a block integration is added.
 */
function rh_cdd_declare_woocommerce_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
    }
}
add_action('before_woocommerce_init', 'rh_cdd_declare_woocommerce_compatibility');

/**
 * Load translations and start the plugin only when WooCommerce is active.
 */
function rh_cdd_load_textdomain() {
    load_plugin_textdomain(
        'custom-delivery-date-checkbox',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('init', 'rh_cdd_load_textdomain', 1);

function rh_cdd_bootstrap() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'rh_cdd_missing_woocommerce_notice');
        return;
    }

    new RH_Custom_Delivery_Date_Checkbox();
}
add_action('plugins_loaded', 'rh_cdd_bootstrap', 20);

function rh_cdd_missing_woocommerce_notice() {
    if (!current_user_can('activate_plugins')) return;

    echo '<div class="notice notice-error"><p>'
        . esc_html__('WooCommerce Delivery Date Scheduler requires WooCommerce to be installed and active.', 'custom-delivery-date-checkbox')
        . '</p></div>';
}
