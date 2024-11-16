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
                        <?php echo ucfirst(esc_html($entry['type'])); ?>: $<?php echo number_format($entry['amount'], 2); ?> -
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


// Save the updated user balance from the user profile page.
function save_user_balance_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (isset($_POST['user_balance'])) {
        update_user_meta($user_id, 'user_balance', sanitize_text_field($_POST['user_balance']));
    }
}

add_action('personal_options_update', 'save_user_balance_field');
add_action('edit_user_profile_update', 'save_user_balance_field');


//Update user balance on order completion and user balance history

function update_user_balance_on_order_completion($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $product_ids = [10125, 10126, 10127]; // Subscription product IDs

    if ($user_id) {
        $current_balance = get_user_meta($user_id, 'user_balance', true);
        $order_total = $order->get_total();

        // Add the order amount to the user's current balance.
        $new_balance = $current_balance + $order_total;
        update_user_meta($user_id, 'user_balance', $new_balance);

        // Update balance history
        $balance_history = get_user_meta($user_id, 'balance_history', true);
        if (!$balance_history) {
            $balance_history = [];
        }

        // Add the new history entry.
        $balance_history[] = [
            'type' => 'add', // could also be 'deduct', 'refill', etc.
            'amount' => $order_total,
            'date' => current_time('Y-m-d H:i:s'),
            'description' => 'Order completed. Added balance.'
        ];

        update_user_meta($user_id, 'balance_history', $balance_history);
    }
}
