<?php

function initialize_balance_management_system() {
    // Hook into WooCommerce to manage balance additions and subscription start date when an order is placed.
    add_action('woocommerce_order_status_completed', 'update_user_balance_on_order_completion');
    add_action('woocommerce_order_status_completed', 'record_subscription_start_date');

    // Set up automatic balance refill when balance drops below the threshold.
    add_action('wp', 'check_user_balance_and_refill');
}
add_action('after_setup_theme', 'initialize_balance_management_system');

// Register AJAX actions to handle manual and automatic refills.
add_action('wp_ajax_manual_balance_update', 'manual_balance_update');
add_action('wp_ajax_nopriv_manual_balance_update', 'manual_balance_update');
