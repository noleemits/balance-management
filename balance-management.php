<?php

// Including all files in the setup.
require_once get_stylesheet_directory() . '/balance-management/balance-hooks.php';
require_once get_stylesheet_directory() . '/balance-management/balance-functions.php';
require_once get_stylesheet_directory() . '/balance-management/subscription-management.php';
require_once get_stylesheet_directory() . '/balance-management/weekly-cycle-check.php';

// Create a function that initializes all the core elements.
function initialize_balance_management_system() {
    // Set up cron job for weekly balance checks.
    schedule_weekly_balance_reset();

    // Register AJAX actions to handle manual and automatic refills.
    add_action('wp_ajax_manual_balance_update', 'manual_balance_update');
    add_action('wp_ajax_nopriv_manual_balance_update', 'manual_balance_update');

    // Hook into WooCommerce to manage balance deductions when an order is placed.
    add_action('woocommerce_order_status_completed', 'update_user_balance_on_order_completion');
}
add_action('after_setup_theme', 'initialize_balance_management_system');


// Function to record the subscription start date.
function record_subscription_start_date($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $product_ids = [10125, 10126, 10127]; // Subscription product IDs

    // Check if the order contains any of the subscription products.
    foreach ($order->get_items() as $item) {
        if (in_array($item->get_product_id(), $product_ids)) {
            if ($user_id && !get_user_meta($user_id, 'subscription_start_date', true)) {
                $start_date = current_time('Y-m-d H:i:s');
                update_user_meta($user_id, 'subscription_start_date', $start_date);
            }
            break;
        }
    }
}
