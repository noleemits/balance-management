<?php

/**
 * Function to record balance history.
 *
 * @param int    $user_id  The user ID.
 * @param string $type     Type of action ('add', 'deduct', 'refill', 'adjustment').
 * @param float  $amount   The amount of the action.
 * @param string $description Description for the history entry.
 */
function record_balance_history($user_id, $type, $amount, $description) {
    $balance_history = get_user_meta($user_id, 'balance_history', true);
    if (!$balance_history) {
        $balance_history = [];
    }

    $balance_history_entry = [
        'type' => $type,
        'amount' => ($type === 'deduct' ? '-' : '+') . number_format(abs($amount), 2),
        'date' => date('m/d/Y h:i A', strtotime(current_time('mysql'))),
        'description' => $description
    ];

    array_unshift($balance_history, $balance_history_entry);
    update_user_meta($user_id, 'balance_history', $balance_history);
}


// Function to initialize user's balance upon registration.
function initialize_user_balance($user_id) {
    add_user_meta($user_id, 'user_balance', 0, true);
}

// Function to get user's current balance.
function get_user_balance($user_id) {
    $balance = get_user_meta($user_id, 'user_balance', true);
    return $balance ? $balance : 0;
}

// Function to update the user's balance.
function update_user_balance($user_id, $amount, $operation = 'add') {
    $current_balance = get_user_balance($user_id);

    if ($operation === 'add') {
        $new_balance = $current_balance + $amount;
    } elseif ($operation === 'subtract') {
        $new_balance = max(0, $current_balance - $amount); // Balance cannot go below zero.
    } else {
        return false;
    }

    update_user_meta($user_id, 'user_balance', $new_balance);
    return $new_balance;
}

// Hook into user registration to initialize balance.
add_action('user_register', 'initialize_user_balance');


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

    // Determine the subscription amount for the user to calculate the threshold.
    $subscription_amount = get_user_subscription_amount($user_id); // Assume this function will get the subscription amount.
    if (!$subscription_amount) {
        return; // If the user doesn't have a subscription amount, we don't proceed.
    }

    $threshold = $subscription_amount * 0.5; // Set threshold as 50% of subscription amount.
    $refill_amount = $subscription_amount; // Refill amount is the same as the subscription.

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

        record_balance_history($user_id, 'refill', $refill_amount, 'Automatic refill triggered due to low balance.');
    }
}

// Function to pause subscription based on balance.
function maybe_pause_subscription($user_id) {
    // Check if the WooCommerce Subscriptions function exists
    if (!function_exists('wcs_get_users_subscriptions')) {
        return; // Exit if WooCommerce Subscriptions is not available
    }

    $current_balance = (float) get_user_meta($user_id, 'user_balance', true);
    $threshold = 150; // Threshold below which renewal is postponed

    if ($current_balance < $threshold) {
        // Get the user's subscriptions
        $subscriptions = wcs_get_users_subscriptions($user_id);

        foreach ($subscriptions as $subscription) {
            if ($subscription->has_status('active')) {
                // Update subscription status to 'on-hold' if balance is insufficient
                $subscription->update_status('on-hold', 'Balance insufficient, postponing renewal');
            }
        }
    }
}


// Helper function to get the user's subscription amount.
function get_user_subscription_amount($user_id) {
    // Assuming that we get the user's subscription amount from user meta or another reliable source.
    $subscription_products = [10125, 10126, 10127]; // Product IDs representing the subscriptions.
    if (!function_exists('wcs_get_users_subscriptions')) {
        return; // Exit if WooCommerce Subscriptions is not available
    }


    foreach ($subscriptions as $subscription) {
        if ($subscription->has_status(['active', 'on-hold'])) {
            foreach ($subscription->get_items() as $item) {
                if (in_array($item->get_product_id(), $subscription_products)) {
                    return (float) $item->get_total();
                }
            }
        }
    }
    return 0; // Default to 0 if no valid subscription found.
}
