<?php
// Function to check if a user has an active SUMO subscription.
function user_has_active_sumo_subscription($user_id) {
    // Use SUMO's subscription retrieval logic (assuming sumosubs_get_subscription_ids() works).
    $subscription_ids = sumosubs_get_subscription_ids();
    if (!$subscription_ids) {
        return false; // No subscriptions found.
    }

    foreach ($subscription_ids as $subscription_id) {
        $subscription = sumo_get_subscription_plan($subscription_id);
        if ($subscription && $subscription['subscription_product_id']) {
            $subscriber_id = get_post_meta($subscription_id, 'sumo_get_user_id', true);
            if ($subscriber_id == $user_id && get_post_meta($subscription_id, 'sumo_get_status', true) == 'Active') {
                return $subscription_id; // Return the active subscription ID.
            }
        }
    }

    return false;
}

// Hook into the WooCommerce process to check for existing subscriptions before allowing a new one.
add_action('woocommerce_checkout_process', 'check_for_existing_sumo_subscription_before_checkout');
function check_for_existing_sumo_subscription_before_checkout() {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();

        // Check if the user already has an active subscription.
        $existing_subscription = user_has_active_sumo_subscription($user_id);

        if ($existing_subscription) {
            wc_add_notice('You already have an active subscription. Please <a href="/contact-us/">contact your admin</a> to change your subscription terms.', 'error');
            // Optionally, redirect them to their subscription management page.
        }
    }
}

// Handle upgrades when a new subscription is created
add_action('woocommerce_checkout_order_processed', 'handle_sumo_subscription_upgrade', 10, 3);
function handle_sumo_subscription_upgrade($order_id, $posted_data, $order) {
    if ($order->get_user_id()) {
        $user_id = $order->get_user_id();

        // Check if there is an existing active subscription.
        $existing_subscription_id = user_has_active_sumo_subscription($user_id);

        if ($existing_subscription_id) {
            // Cancel the existing subscription.
            sumosubs_cancel_subscription($existing_subscription_id, array('note' => __('Subscription automatically cancelled upon new subscription.', 'sumosubscriptions')));
        }
    }
}

// Deactivate old SUMO subscriptions when a new subscription is created
add_action('sumosubscriptions_active_subscription', 'deactivate_old_sumo_subscriptions', 10, 2);
function deactivate_old_sumo_subscriptions($post_id, $order_id) {
    // Get the user associated with the new subscription.
    $user_id = get_post_meta($post_id, 'sumo_get_user_id', true);

    if ($user_id) {
        $subscription_ids = sumosubs_get_subscription_ids();

        foreach ($subscription_ids as $subscription_id) {
            if ($subscription_id != $post_id) {
                $subscriber_id = get_post_meta($subscription_id, 'sumo_get_user_id', true);
                $status = get_post_meta($subscription_id, 'sumo_get_status', true);

                if ($subscriber_id == $user_id && $status == 'Active') {
                    // Cancel the existing active subscription.
                    sumosubs_cancel_subscription($subscription_id, array('note' => __('Subscription automatically cancelled due to new subscription activation.', 'sumosubscriptions')));
                }
            }
        }
    }
}
