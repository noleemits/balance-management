<?php
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
