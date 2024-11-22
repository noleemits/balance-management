<?php
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Make sure SUMO Subscriptions plugin is active
if (!is_plugin_active('sumosubscriptions/sumosubscriptions.php')) {
    return;
}

// Include the balance-hooks file to load related functions
require_once get_stylesheet_directory() . '/balance-management/balance-hooks.php';

function daily_subscription_balance_check() {
    // Get all users with subscriptions
    $users = get_users(['role' => 'subscriber', 'fields' => 'ID']);
    foreach ($users as $user_id) {
        // Get the subscription details
        $subscription_details = get_user_subscription_details($user_id);

        if (is_array($subscription_details)) {
            $subscription_id = $subscription_details['subscription_id'];
            $subscription_status = get_post_meta($subscription_id, 'sumo_get_status', true); // Explicitly retrieve subscription status

            // Get subscription fee from serialized product details
            $product_details_serialized = get_post_meta($subscription_id, 'sumo_subscription_product_details', true);

            if ($product_details_serialized) {
                $product_details = maybe_unserialize($product_details_serialized);
                $subscription_fee = isset($product_details['subfee']) ? (float)$product_details['subfee'] : 0;
            } else {
                $subscription_fee = 0;
            }

            if ($subscription_fee <= 0) {
                continue; // Skip if subscription fee is invalid
            }

            // Calculate 50% of the subscription fee.
            $threshold_amount = $subscription_fee * 0.5;

            // Get the current balance of the user.
            $current_balance = (float)get_user_meta($user_id, 'user_balance', true);

            // Check manual_pause meta to ensure subscription wasn't manually paused
            $manual_pause = get_user_meta($user_id, 'manual_pause', true);

            // **Pausing Criteria**: If the balance is sufficient (>= 50%)
            if ($current_balance >= $threshold_amount) {
                if ($subscription_status !== 'Pause') {
                    maybe_pause_sumo_subscription($subscription_id);
                    update_user_meta($user_id, 'appointment_paused', 'Yes'); // Set to 'Yes' to reflect automatic pause
                    update_user_meta($user_id, 'manual_pause', 'No'); // Clear manual pause
                }
            }
            // **Resuming Criteria**: If the balance is insufficient (< 50%) and was not manually paused
            elseif ($current_balance < $threshold_amount) {
                if ($subscription_status === 'Pause') {
                    if ($manual_pause !== 'Yes') {
                        maybe_resume_sumo_subscription($subscription_id);
                        update_user_meta($user_id, 'appointment_paused', 'No'); // Set to 'No' to reflect automatic resume
                        update_user_meta($user_id, 'manual_pause', 'No'); // Clear manual pause
                    }
                }
            }
        }
    }
}

// Helper function to pause SUMO subscription
function maybe_pause_sumo_subscription($subscription_id) {
    if (function_exists('sumo_get_subscription')) {
        $subscription = sumo_get_subscription($subscription_id);
        if ($subscription) {
            update_post_meta($subscription_id, 'sumo_get_status', 'Pause'); // Update metadata to pause
            do_action('sumosubscription_paused', $subscription); // Trigger SUMO action for pausing
        }
    }
}

// Helper function to resume SUMO subscription
function maybe_resume_sumo_subscription($subscription_id) {
    if (function_exists('sumo_get_subscription')) {
        $subscription = sumo_get_subscription($subscription_id);
        if ($subscription) {
            update_post_meta($subscription_id, 'sumo_get_status', 'Active'); // Update metadata to resume
            do_action('sumosubscription_resumed', $subscription); // Trigger SUMO action for resuming
        }
    }
}

// Hook to call this function daily (cron job)
add_action('daily_subscription_balance_check_hook', 'daily_subscription_balance_check');

// Schedule the event to run daily
if (!wp_next_scheduled('daily_subscription_balance_check_hook')) {
    wp_schedule_event(time(), 'daily', 'daily_subscription_balance_check_hook');
}
