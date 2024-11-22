<?php

// Including all files in the setup.
require_once get_stylesheet_directory() . '/balance-management/balance-hooks.php';
require_once get_stylesheet_directory() . '/balance-management/balance-functions.php';
require_once get_stylesheet_directory() . '/balance-management/subscription-management.php';
require_once get_stylesheet_directory() . '/balance-management/subscription_balance_check.php';
require_once get_stylesheet_directory() . '/balance-management/display-user-shortcode.php';
require_once get_stylesheet_directory() . '/balance-management/balance-ajax.php';


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
