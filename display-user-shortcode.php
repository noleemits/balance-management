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


// Shortcode to display the provider information container
function provider_info_shortcode() {
    // Output a container where the user information will be displayed
    ob_start();
?>
    <div id="provider-info-container">
        <p>Please select a provider to see the details.</p>
    </div>
<?php
    return ob_get_clean();
}
add_shortcode('provider_info', 'provider_info_shortcode');

// AJAX handler to get provider information (extended to include balance and appointment cost input)
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
            // Get basic user information
            $user_name = get_the_author_meta('display_name', $related_user_id);
            $user_email = get_the_author_meta('user_email', $related_user_id);

            // Get JetEngine fields
            $profile_image = get_user_meta($related_user_id, 'profile_image', true);
            $weekly_budget = get_user_meta($related_user_id, 'weekly_budget', true);
            $service_areas = get_user_meta($related_user_id, 'service-areas', true);

            // Get current balance
            $current_balance = (float) get_user_meta($related_user_id, 'user_balance', true);

            // Calculate the current spent budget for the week
            $current_spent_budget = get_weekly_spent_amount_by_provider($provider_id);

            // Start output
            $output = "<strong>Name:</strong> " . esc_html($user_name) . "<br>";
            $output .= "<strong>Email:</strong> " . esc_html($user_email) . "<br>";

            // Profile image (if exists)
            if (!empty($profile_image)) {
                $output .= "<strong>Profile Image:</strong><br><img src='" . esc_url($profile_image) . "' alt='Profile Image' style='max-width:150px; height:auto;'><br>";
            }

            // Weekly budget
            if (!empty($weekly_budget)) {
                $output .= "<strong>Weekly Budget:</strong> $" . esc_html($weekly_budget) . "<br>";
            }

            // Current Spent Budget for the Week
            $output .= "<strong>Current Spent Budget (This Week):</strong> $" . number_format($current_spent_budget, 2) . "<br>";

            // Current Balance
            $output .= "<strong>Current Balance:</strong> $" . number_format($current_balance, 2) . "<br>";

            // Service Areas (if exists)
            if (!empty($service_areas) && is_array($service_areas)) {
                $output .= "<strong>Service Areas:</strong><br>";
                foreach ($service_areas as $area) {
                    $output .= "- " . esc_html($area['service-area']) . "<br>";
                }
            } else {
                $output .= "No service areas found<br>";
            }

            echo $output;
        } else {
            echo 'No user found with that email.';
        }
    } else {
        echo 'Invalid request. Provider ID is missing.';
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('wp_ajax_get_provider_info', 'get_provider_info');
add_action('wp_ajax_nopriv_get_provider_info', 'get_provider_info');
