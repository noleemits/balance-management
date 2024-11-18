<?php

// Including all files in the setup.
require_once get_stylesheet_directory() . '/balance-management/balance-hooks.php';
require_once get_stylesheet_directory() . '/balance-management/balance-functions.php';
require_once get_stylesheet_directory() . '/balance-management/subscription-management.php';
require_once get_stylesheet_directory() . '/balance-management/weekly-cycle-check.php';
require_once get_stylesheet_directory() . '/balance-management/display-user-shortcode.php';

// Enqueue the JavaScript for the shortcode
function enqueue_display_user_script() {
    wp_enqueue_script(
        'display-user-script',
        get_stylesheet_directory_uri() . '/balance-management/js/display-user.js',
        array('jquery'),
        null,
        true
    );

    wp_localize_script(
        'display-user-script',
        'ajax_object',
        array('ajaxurl' => admin_url('admin-ajax.php'))
    );
}
add_action('wp_enqueue_scripts', 'enqueue_display_user_script');

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

// Function to check user balance and trigger an automatic refill if below threshold.
function check_user_balance_and_refill() {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $current_balance = (float) get_user_meta($user_id, 'user_balance', true);
    $threshold = 150; // Threshold below which we trigger a refill
    $refill_amount = 500; // Amount to refill automatically

    // If balance is below threshold, trigger refill
    if ($current_balance < $threshold) {
        // Update the balance by adding refill amount
        $new_balance = $current_balance + $refill_amount;
        update_user_meta($user_id, 'user_balance', $new_balance);

        // Record the refill in the user's balance history
        $balance_history = get_user_meta($user_id, 'balance_history', true);
        if (!$balance_history) {
            $balance_history = [];
        }

        $balance_history[] = [
            'type' => 'refill',
            'amount' => '+' . $refill_amount,
            'date' => current_time('Y-m-d H:i:s'),
            'description' => 'Automatic refill triggered due to low balance.'
        ];

        update_user_meta($user_id, 'balance_history', $balance_history);
    }
}

// Function to pause subscription based on balance.
function maybe_pause_subscription($user_id) {
    $current_balance = (float) get_user_meta($user_id, 'user_balance', true);
    $threshold = 150; // Threshold below which renewal is postponed

    if ($current_balance > $threshold) {
        // Logic to pause the subscription if the user has sufficient balance
        $subscriptions = wcs_get_users_subscriptions($user_id);

        foreach ($subscriptions as $subscription) {
            if ($subscription->has_status('active')) {
                $subscription->update_status('on-hold', 'Balance sufficient, postponing renewal');
            }
        }
    }
}
