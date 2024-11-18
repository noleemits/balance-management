<?php

// Add custom user balance meta field to user profile in admin.
function display_user_balance_field($user) {
    // Retrieve the user balance from user meta.
    $user_balance = get_user_meta($user->ID, 'user_balance', true);
?>
    <h3>User Balance Information</h3>
    <table class="form-table">
        <tr>
            <th><label for="user_balance">User Balance ($)</label></th>
            <td>
                <input type="text" name="user_balance" id="user_balance" value="<?php echo esc_attr($user_balance); ?>" class="regular-text" />
                <p class="description">Current balance available for the user.</p>
            </td>
        </tr>
    </table>
<?php
}
add_action('show_user_profile', 'display_user_balance_field');
add_action('edit_user_profile', 'display_user_balance_field');

// Shortcode to display the user's balance and history
function display_user_balance_shortcode($atts) {
    // Extract user ID from shortcode attributes
    $atts = shortcode_atts(array(
        'user_id' => get_current_user_id(), // Defaults to the current logged-in user
    ), $atts);

    $user_id = intval($atts['user_id']);

    if (!$user_id) {
        return 'You need to be logged in to see your balance or provide a valid user ID.';
    }

    // Get the current balance and balance history
    $current_balance = get_user_meta($user_id, 'user_balance', true);

    // Ensure balance is a float, and if not set, default it to zero
    if (empty($current_balance)) {
        $current_balance = 0.0;
    } else {
        $current_balance = (float) $current_balance;
    }

    $balance_history = get_user_meta($user_id, 'balance_history', true);

    ob_start(); // Start output buffering
?>
    <div class="user-balance-info">
        <h3>Current Balance: $<?php echo number_format($current_balance, 2); ?></h3>
        <h4>Balance History:</h4>
        <ul>
            <?php if (!empty($balance_history)) : ?>
                <?php foreach ($balance_history as $entry) : ?>
                    <li>
                        <?php echo esc_html($entry['date']); ?> -
                        <?php echo ucfirst(esc_html($entry['type'])); ?>: $<?php echo number_format((float) $entry['amount'], 2); ?> -
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

add_shortcode('user_balance', 'display_user_balance_shortcode');

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
        $balance_history = get_user_meta($user_id, 'balance_history', true);
        if (!$balance_history) {
            $balance_history = [];
        }

        $balance_history_entry = [
            'type' => 'adjustment',
            'amount' => ($new_balance > $old_balance ? '+' : '-') . abs($new_balance - $old_balance),
            'date' => date('m/d/Y h:i A', strtotime(current_time('mysql'))),
            'description' => 'Credit adjusted by Admin.'
        ];

        array_unshift($balance_history, $balance_history_entry);
        update_user_meta($user_id, 'balance_history', $balance_history);
    }
}
add_action('personal_options_update', 'save_user_balance_field');
add_action('edit_user_profile_update', 'save_user_balance_field');

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

        // Update balance history
        $balance_history = get_user_meta($user_id, 'balance_history', true);
        if (!$balance_history) {
            $balance_history = [];
        }

        // Add the new history entry.
        $balance_history_entry = [
            'type' => 'add', // could also be 'deduct', 'refill', etc.
            'amount' => '+' . $order_total,
            'date' => date('m/d/Y h:i A', strtotime(current_time('mysql'))),
            'description' => 'Order completed. Added balance.'
        ];

        array_unshift($balance_history, $balance_history_entry);
        update_user_meta($user_id, 'balance_history', $balance_history);
    }
}

// Deduct balance when appointment form is submitted by the rep
function deduct_user_balance_on_appointment_submission($user_id, $appointment_cost) {
    // Get the current balance and cast it to a float
    $current_balance = (float) get_user_meta($user_id, 'user_balance', true);

    // Check if the balance is sufficient
    if ($current_balance >= $appointment_cost) {
        // Deduct the appointment cost from the user's balance
        $new_balance = $current_balance - $appointment_cost;
        update_user_meta($user_id, 'user_balance', $new_balance);

        // Update balance history
        $balance_history = get_user_meta($user_id, 'balance_history', true);
        if (!$balance_history) {
            $balance_history = [];
        }

        // Add a new history entry
        $balance_history_entry = [
            'type' => 'deduct',
            'amount' => '-' . number_format($appointment_cost, 2),
            'date' => date('m/d/Y h:i A', strtotime(current_time('mysql'))),
            'description' => 'Appointment cost deducted by Rep.'
        ];

        array_unshift($balance_history, $balance_history_entry);
        update_user_meta($user_id, 'balance_history', $balance_history);

        return true; // Success
    } else {
        return false; // Insufficient balance
    }
}


// AJAX handler to deduct balance on appointment submission by a rep
function deduct_user_balance_for_appointment() {
    if (isset($_POST['provider_id']) && isset($_POST['appointment_cost'])) {
        $provider_id = intval($_POST['provider_id']);
        $appointment_cost = floatval($_POST['appointment_cost']);

        // Query the email address related to the provider ID using the 'select_user' meta key
        $related_user_email = get_post_meta($provider_id, 'select_user', true);

        if ($related_user_email) {
            // Retrieve the user ID using the email address
            $related_user = get_user_by('email', $related_user_email);
            $related_user_id = $related_user ? $related_user->ID : false;

            if ($related_user_id) {
                $deduction_result = deduct_user_balance_on_appointment_submission($related_user_id, $appointment_cost);

                if ($deduction_result) {
                    wp_send_json_success(['message' => 'Balance deducted successfully']);
                } else {
                    wp_send_json_error(['message' => 'Insufficient balance']);
                }
            } else {
                wp_send_json_error(['message' => 'User not found']);
            }
        } else {
            wp_send_json_error(['message' => 'Provider email not found']);
        }
    } else {
        wp_send_json_error(['message' => 'Invalid data provided']);
    }

    wp_die();
}
add_action('wp_ajax_deduct_user_balance_for_appointment', 'deduct_user_balance_for_appointment');
add_action('wp_ajax_nopriv_deduct_user_balance_for_appointment', 'deduct_user_balance_for_appointment');
