// jQuery(document).ready(function ($) {
//     // Handle change event for provider dropdown
//     $('.appointment-provider').on('change', function () {
//         var provider_id = $(this).val();
//         if (provider_id) {
//             $.ajax({
//                 url: ajax_object.ajaxurl,
//                 type: 'POST',
//                 data: {
//                     action: 'get_provider_info',
//                     provider_id: provider_id,
//                 },
//                 success: function (response) {
//                     $('#provider-info-container').html(response);
//                 },
//                 error: function () {
//                     $('#provider-info-container').html('<p>Error retrieving provider information.</p>');
//                 }
//             });
//         } else {
//             $('#provider-info-container').html('<p>Please select a provider to see the details.</p>');
//         }
//     });

//     // Handle input for appointment cost and validate balance
//     $(document).on('input', '#price', function () {
//         var appointmentCost = parseFloat($(this).val());
//         var currentBalanceElement = $('#provider-info-container').find('#user_balance');

//         // Ensure that the balance element is found before proceeding
//         if (currentBalanceElement.length > 0) {
//             var currentBalance = parseFloat(currentBalanceElement.text().replace(/,/g, ''));

//             if (!isNaN(appointmentCost) && !isNaN(currentBalance)) {
//                 if (appointmentCost > currentBalance) {
//                     $('#balance-warning-message').show();
//                 } else {
//                     $('#balance-warning-message').hide();
//                 }
//             }
//         }
//     });
// });

jQuery(document).ready(function ($) {
    // Handle input event for the appointment cost field
    $(document).on('input', '#price', function () {
        var appointmentCost = parseFloat($(this).val());
        console.log("Appointment Cost Entered:", appointmentCost);  // Debug: Check entered cost

        var currentBalanceElement = $('#provider-info-container').find('#user_balance');

        if (currentBalanceElement.length > 0) {
            var currentBalance = parseFloat(currentBalanceElement.text().replace(/,/g, ''));
            console.log("Current Balance:", currentBalance);  // Debug: Check current balance

            if (!isNaN(appointmentCost) && !isNaN(currentBalance)) {
                if (appointmentCost > currentBalance) {
                    console.log("Insufficient balance warning triggered.");  // Debug statement
                    $('#balance-warning-message').show();  // Show warning message

                    // Disable the submit button
                    $('.jet-form-builder__action-button.jet-form-builder__submit')
                        .prop('disabled', true)
                        .css({
                            'cursor': 'not-allowed',
                            'opacity': '0.6' // Optional: Make the button look visually disabled
                        });
                } else {
                    $('#balance-warning-message').hide();  // Hide warning message

                    // Enable the submit button if the balance is enough
                    $('.jet-form-builder__action-button.jet-form-builder__submit')
                        .prop('disabled', false)
                        .css({
                            'cursor': 'pointer',
                            'opacity': '1' // Restore original appearance
                        });
                }
            }
        } else {
            console.error("Could not find the current balance element.");  // Debug: Check if element exists
        }
    });

    // Handle change event for provider dropdown
    $('.appointment-provider').on('change', function () {
        var provider_id = $(this).val();
        if (provider_id) {
            $.ajax({
                url: ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_provider_info',
                    provider_id: provider_id,
                },
                success: function (response) {
                    $('#provider-info-container').html(response);
                },
                error: function () {
                    $('#provider-info-container').html('<p>Error retrieving provider information.</p>');
                }
            });
        } else {
            $('#provider-info-container').html('<p>Please select a provider to see the details.</p>');
        }
    });
});
