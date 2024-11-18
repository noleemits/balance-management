<?php

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
