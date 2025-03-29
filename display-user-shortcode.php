<?php

function get_weekly_spent_amount_by_provider($provider_id) {
    global $wpdb;

    $start_of_week = strtotime('monday this week');
    $end_of_week = strtotime('sunday this week 23:59:59');

    // Adjusted query to use the new 'booking_ordered_date' column
    $query = $wpdb->prepare(
        "
        SELECT SUM(appointment_price)
        FROM {$wpdb->prefix}jet_appointments
        WHERE provider = %d
        AND status = 'completed'
        AND order_id IS NOT NULL
        AND booking_ordered_date >= %s
        AND booking_ordered_date <= %s
        ",
        $provider_id,
        date('Y-m-d H:i:s', $start_of_week),
        date('Y-m-d H:i:s', $end_of_week)
    );

    return $wpdb->get_var($query) ?: 0;
}


// Shortcode to display the provider information (for reps when placing an appointment)
function provider_info_shortcode() {
    ob_start();
?>
    <div id="provider-info-container">
        <p>Please select a provider to see the details.</p>
    </div>
<?php
    return ob_get_clean();
}
add_shortcode('provider_info', 'provider_info_shortcode');

// AJAX handler to get provider information (extended to include balance, subscription details, and appointment cost input)
function get_provider_info() {
    if (isset($_POST['provider_id'])) {
        $provider_id = sanitize_text_field($_POST['provider_id']);

        if (empty($provider_id)) {
            echo 'Provider ID is empty.';
            wp_die();
        }

        // Query the email address related to the provider ID using the 'select_user' meta key
        $related_user_email = get_post_meta($provider_id, 'select_user', true);

        if (!$related_user_email) {
            echo 'No email found for the provider.';
            wp_die();
        }

        // Retrieve the user ID using the email address
        $related_user = get_user_by('email', $related_user_email);
        $related_user_id = $related_user ? $related_user->ID : false;

        if ($related_user_id) {
            // Use provider data function to get information
            $provider_data = get_provider_data($related_user_id);
            $next_cycle_date = get_next_cycle_date($related_user_id);
            // Retrieve the phone number from user meta
            $phone_number = get_user_meta($related_user_id, 'meta_billing_phone_number', true);

            // Start output
            $output = "<strong>Name:</strong> " . esc_html($provider_data['user_name']) . "<br>";
            $output .= "<strong>Email:</strong><span class='provider-email'> " . esc_html($provider_data['user_email']) . "</span><br>";
            // Add the phone number below the email address
            if (!empty($phone_number)) {
                $output .= "<strong>Phone Number:</strong><span class='provider-phone'>  " . esc_html($phone_number) . "</span><br>";
            }

            // Profile image (if exists)
            if (!empty($provider_data['profile_image'])) {
                $output .= "<strong>Profile Image:</strong><br><img src='" . esc_url($provider_data['profile_image']) . "' alt='Profile Image' style='max-width:150px; height:auto;'><br>";
            }

            // Current Balance
            $output .= "<strong>Current Balance:</strong> $<span id='user_balance'>" . number_format($provider_data['current_balance'], 2) . "</span><br>";

            // Next Billing Cycle Date
            $output .= "<strong>Next Billing Cycle:</strong> " . esc_html($next_cycle_date) . "<br>";

            // Subscription Details
            if (is_array($provider_data['subscription_details'])) {
                $output .= "<strong>Subscription Status:</strong> " . esc_html($provider_data['subscription_details']['status']) . "<br>";
                $output .= "<strong>Subscription Product:</strong> " . esc_html(preg_replace('/\s*\(\#\d+\)$/', '', strip_tags($provider_data['subscription_details']['product']))) . "<br>";
            } else {
                $output .= "<strong>Subscription:</strong> " . esc_html($provider_data['subscription_details']) . "<br>";
            }

            // Warning message if the balance is insufficient for the price entered
            $output .= "<div id='balance-warning-message' style='display: none;'>
            <p class='warning-text'>Warning: The user's balance is insufficient for the requested appointment price.</p>" . do_shortcode('[jet_fb_form form_id="10288" submit_type="reload" required_mark="*" fields_layout="column" fields_label_tag="div" enable_progress="" clear=""]') . "
            </div>";
            echo $output;
        } else {
            echo 'No user found with that email.';
        }
    } else {
        echo 'Invalid request. Provider ID is missing.';
    }

    wp_die();
}

add_action('wp_ajax_get_provider_info', 'get_provider_info');
add_action('wp_ajax_nopriv_get_provider_info', 'get_provider_info');
