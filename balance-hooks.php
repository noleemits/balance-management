<?php
// Function to retrieve provider-related user data
function get_provider_data($related_user_id) {
    // Get basic user information
    $user_name = get_the_author_meta('display_name', $related_user_id);
    $user_email = get_the_author_meta('user_email', $related_user_id);

    // Get JetEngine fields
    $profile_image = get_user_meta($related_user_id, 'profile_image', true);
    $service_areas = get_user_meta($related_user_id, 'service-areas', true);

    // Get current balance
    $current_balance = (float) get_user_meta($related_user_id, 'user_balance', true);

    // Calculate the current spent budget for the week
    $provider_id = $related_user_id; // Assuming the provider_id is the user ID.
    $current_spent_budget = get_weekly_spent_amount_by_provider($provider_id);

    // Retrieve subscription details
    $subscription_details = get_user_subscription_details($related_user_id);

    return [
        'user_name' => $user_name,
        'user_email' => $user_email,
        'profile_image' => $profile_image,
        'service_areas' => $service_areas,
        'current_balance' => $current_balance,
        'current_spent_budget' => $current_spent_budget,
        'subscription_details' => $subscription_details
    ];
}

// Function to get user's subscription details
function get_user_subscription_details($user_id) {
    // Get the subscription object for the user
    $subscription = sumosubs_get_user_subscriptions($user_id);

    if ($subscription) {
        $subscription_id = $subscription->get_id();
        $subscription_status = get_post_meta($subscription_id, 'sumo_get_status', true);
        $next_payment_date = get_post_meta($subscription_id, 'sumo_get_next_payment_date', true);

        return [
            'status' => $subscription_status,
            'subscription_id' => $subscription_id,
            'product' => preg_replace('/\s*\(#\d+\)$/', '', strip_tags(sumo_display_subscription_name($subscription_id, false, true))),
            'next_payment_date' => $next_payment_date ? $next_payment_date : 'No upcoming payment',
        ];
    }

    return 'No active subscription';
}


// Function to get next renewal date based on SUMO subscription metadata.
function get_next_cycle_date($user_id) {
    // Get the subscription details
    $subscription_details = get_user_subscription_details($user_id);

    if (is_array($subscription_details) && isset($subscription_details['next_payment_date'])) {
        // Return the next payment date if available
        if ($subscription_details['status'] === 'Paused') {
            return 'Profile has been paused';
        } else {
            return date('F j, Y g:i a', strtotime($subscription_details['next_payment_date']));
        }
    }

    return 'No subscription start date found';
}


// Helper function to update the user's balance history
function update_balance_history($user_id, $type, $amount, $description) {
    $balance_history = get_user_meta($user_id, 'balance_history', true);
    if (!$balance_history) {
        $balance_history = [];
    }

    $balance_history_entry = [
        'type' => $type,
        'amount' => ($type === 'deduct' ? '-' : '+') . abs($amount),
        'date' => date('m/d/Y h:i A', strtotime(current_time('mysql'))),
        'description' => $description
    ];

    array_unshift($balance_history, $balance_history_entry);
    update_user_meta($user_id, 'balance_history', $balance_history);
}

// Shortcode to display the user's balance information
function user_balance_shortcode($atts) {
    // Extract user ID from shortcode attributes
    $atts = shortcode_atts(array(
        'user_id' => get_current_user_id(), // Defaults to the current logged-in user
    ), $atts);

    $user_id = intval($atts['user_id']);

    if (!$user_id) {
        return 'You need to be logged in to see your balance or provide a valid user ID.';
    }

    // Use the get_provider_data function to retrieve relevant data
    $provider_data = get_provider_data($user_id);

    ob_start(); // Start output buffering
?>
    <div class="user-balance-info">
        <h3>Current Balance: <span id="user_balance">$<?php echo number_format((float)$provider_data['current_balance'], 2); ?></span></h3>
        <h4>Next Refill Date: <span id="next_cycle_date"><?php echo esc_html(get_next_cycle_date($user_id)); ?></span></h4>
        <h4>Subscription Details:</h4>
        <ul>
            <?php if (is_array($provider_data['subscription_details'])) : ?>
                <li>Status: <?php echo esc_html($provider_data['subscription_details']['status']); ?></li>
                <li>Product: <?php echo esc_html(preg_replace('/\s*\(\#\d+\)$/', '', strip_tags($provider_data['subscription_details']['product']))); ?></li>
            <?php else : ?>
                <li><?php echo esc_html($provider_data['subscription_details']); ?></li>
            <?php endif; ?>
        </ul>
        <h4>Balance History:</h4>
        <ul>
            <?php
            $balance_history = get_user_meta($user_id, 'balance_history', true);
            if (!empty($balance_history)) : ?>
                <?php foreach ($balance_history as $entry) : ?>
                    <li>
                        <?php echo esc_html($entry['date']); ?> -
                        <?php echo ucfirst(esc_html($entry['type'])); ?>: $<?php echo number_format((float)str_replace(',', '', $entry['amount']), 2); ?> -
                        <?php echo esc_html($entry['description']); ?>
                    </li>
                <?php endforeach; ?>
            <?php else : ?>
                <li>No balance history available.</li>
            <?php endif; ?>
        </ul>
    </div>
<?php

    return ob_get_clean(); // Return output buffer content
}
add_shortcode('user_balance', 'user_balance_shortcode');

// Save the updated user balance from the user profile page and record history.
function save_user_balance_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (isset($_POST['user_balance'])) {
        $old_balance = (float) get_user_meta($user_id, 'user_balance', true);
        $new_balance = (float) sanitize_text_field($_POST['user_balance']);

        // Update user balance
        update_user_meta($user_id, 'user_balance', $new_balance);

        // Record balance adjustment in history
        $adjustment_amount = $new_balance - $old_balance;
        update_balance_history($user_id, 'adjustment', $adjustment_amount, 'Credit adjusted by Admin.');
    }
}
add_action('personal_options_update', 'save_user_balance_field');
add_action('edit_user_profile_update', 'save_user_balance_field');

// Add the balance field to the user profile page but make it non-editable
// Add the balance field to the user profile page but make it non-editable
function add_user_balance_field($user) {
    if (!current_user_can('edit_user', $user->ID)) {
        return;
    }

    $user_balance = get_user_meta($user->ID, 'user_balance', true);
?>
    <h3>User Balance</h3>
    <table class="form-table">
        <tr>
            <th><label for="user_balance">User Balance ($)</label></th>
            <td>
                <input type="text" name="user_balance" id="user_balance" value="<?php echo esc_attr($user_balance); ?>" class="regular-text" readonly="readonly" />
                <button type="button" id="enable-edit-balance" class="button">Edit Balance</button>
                <button type="button" id="disable-edit-balance" class="button" style="display: none;">Disable Edit</button>
                <p class="description">Click "Edit Balance" to modify the user's balance. Be careful with changes.</p>
            </td>
        </tr>
    </table>
    <script type="text/javascript">
        document.getElementById('enable-edit-balance').addEventListener('click', function() {
            var balanceField = document.getElementById('user_balance');
            balanceField.readOnly = false;
            balanceField.focus();
            document.getElementById('enable-edit-balance').style.display = 'none';
            document.getElementById('disable-edit-balance').style.display = 'inline';
        });

        document.getElementById('disable-edit-balance').addEventListener('click', function() {
            var balanceField = document.getElementById('user_balance');
            balanceField.readOnly = true;
            document.getElementById('enable-edit-balance').style.display = 'inline';
            document.getElementById('disable-edit-balance').style.display = 'none';
        });
    </script>
<?php
}
add_action('show_user_profile', 'add_user_balance_field');
add_action('edit_user_profile', 'add_user_balance_field');


// Function to update user balance on order completion and record in balance history.
function update_user_balance_on_order_completion($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    if ($user_id) {
        // Get current balance and make sure it's treated as a float, defaulting to 0 if not set.
        $current_balance = (float) get_user_meta($user_id, 'user_balance', true);
        $order_total = (float) $order->get_total(); // Cast order total to float to prevent issues.

        // Add the order amount to the user's current balance.
        $new_balance = $current_balance + $order_total;
        update_user_meta($user_id, 'user_balance', $new_balance);

        // Record the addition in history
        update_balance_history($user_id, 'add', $order_total, 'Order completed. Added balance.');
    }
}
add_action('woocommerce_order_status_completed', 'update_user_balance_on_order_completion');

// Hook to listen for changes in user meta 'appointment_paused' and pause/resume subscription accordingly
add_action('updated_user_meta', 'check_and_pause_sumo_subscription', 10, 4);

function check_and_pause_sumo_subscription($meta_id, $user_id, $meta_key, $meta_value) {
    if ($meta_key === 'appointment_paused') {
        // Get the SUMO subscription for the user
        $subscription = sumosubs_get_user_subscriptions($user_id);

        if ($subscription) {
            // Get the current status from metadata
            $subscription_status = get_post_meta($subscription->get_id(), 'sumo_get_status', true);

            if ($meta_value === 'Yes' && $subscription_status === 'Active') {
                update_post_meta($subscription->get_id(), 'sumo_get_status', 'Pause'); // Pause the subscription
            } elseif ($meta_value === 'No' && $subscription_status === 'Pause') {
                update_post_meta($subscription->get_id(), 'sumo_get_status', 'Active'); // Resume the subscription
            }
        }
    }
}

// Function to retrieve the active SUMO subscription for a user
function sumosubs_get_user_subscriptions($user_id) {
    // Assuming that there is a function to get SUMO subscription for a user
    $subscriptions = sumosubscriptions()->query->get(array(
        'type'       => 'sumosubscriptions',
        'status'     => 'publish',
        'meta_key'   => 'sumo_get_user_id',
        'meta_value' => $user_id,
    ));

    if (!empty($subscriptions)) {
        return sumo_get_subscription(current($subscriptions)); // Return the first subscription found
    }

    return false;
}

// Hook into the status change of JetAppointment to detect "Completed" status.
add_action('jet-form-builder/custom-action/deduct_balance_after_appointment', 'deduct_balance_after_appointment_handler', 10, 3);

function deduct_balance_after_appointment_handler($request, $action_handler) {
    // Extract provider-related data from the request
    $provider_id = !empty($request['provider_id']) ? intval($request['provider_id']) : 0; // Assuming `provider_id` is a field in your form
    $appointment_cost = !empty($request['price']) ? floatval($request['price']) : 0;  // Replace 'price' with the field name in your form

    if ($provider_id) {
        // Assuming that we have a mapping between provider and user via the select_user meta field
        $related_user_email = get_post_meta($provider_id, 'select_user', true);

        if ($related_user_email) {
            // Retrieve the user by email
            $related_user = get_user_by('email', $related_user_email);
            $related_user_id = $related_user ? $related_user->ID : 0;
        } else {
            throw new Exception('Invalid appointment provider data.');
        }
    } else {
        $related_user_id = 0; // Fallback if provider ID is not provided
    }

    // Validate the user ID and appointment cost
    if ($related_user_id && $appointment_cost > 0) {
        // Get the current balance from user meta
        $current_balance = (float) get_user_meta($related_user_id, 'user_balance', true);

        // Check if balance is enough and deduct if possible
        if ($current_balance >= $appointment_cost) {
            $new_balance = $current_balance - $appointment_cost;
            update_user_meta($related_user_id, 'user_balance', $new_balance);

            // Record in balance history
            update_balance_history($related_user_id, 'deduct', $appointment_cost, 'Appointment cost deducted upon submission.');
        } else {
            // Throwing an exception to indicate form processing failure
            throw new Exception('Insufficient balance to complete this appointment.');
        }
    } else {
        // Throwing an exception to indicate invalid data
        throw new Exception('Invalid appointment cost or user data.');
    }
}
