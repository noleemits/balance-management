jQuery(document).ready(function ($) {
    /**
     * Function to update specific fields based on the provider info response.
     * @param {string} fieldSelector - The field to update (e.g., '#contractor_email_field').
     * @param {string} containerClass - The class of the element containing the value (e.g., 'provider-email').
     * @param {string} fieldName - The name of the field for debugging purposes.
     */
    function updateFieldFromContainer(fieldSelector, containerClass, fieldName) {
        const value = $('#provider-info-container').find(`.${containerClass}`).text();
        if (value) {
            $(fieldSelector).val(value);
            console.log(`${fieldName} updated:`, value); // Debugging output
        } else {
            console.error(`${fieldName} not found in the response.`);
        }
    }

    /**
     * Function to handle the provider dropdown change event.
     */
    function handleProviderChange() {
        const provider_id = $(this).val();
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

                    // Update specific fields if necessary
                    updateFieldFromContainer('#contrator_email_field', 'provider-email', 'Contractor Email');
                    updateFieldFromContainer('#contrator_phone_field', 'provider-phone', 'Contractor Phone');
                },
                error: function () {
                    $('#provider-info-container').html('<p>Error retrieving provider information.</p>');
                },
            });
        } else {
            $('#provider-info-container').html('<p>Please select a provider to see the details.</p>');
        }
    }

    /**
     * Function to handle balance validation for the appointment cost field.
     */
    function handleBalanceValidation() {
        const appointmentCost = parseFloat($(this).val());
        console.log("Appointment Cost Entered:", appointmentCost); // Debug: Check entered cost

        const currentBalanceElement = $('#provider-info-container').find('#user_balance');

        if (currentBalanceElement.length > 0) {
            const currentBalance = parseFloat(currentBalanceElement.text().replace(/,/g, ''));
            console.log("Current Balance:", currentBalance); // Debug: Check current balance

            if (!isNaN(appointmentCost) && !isNaN(currentBalance)) {
                if (appointmentCost > currentBalance) {
                    console.log("Insufficient balance warning triggered."); // Debug statement
                    $('#balance-warning-message').show();
                    $('#appointment-form .jet-form-builder__action-button.jet-form-builder__submit')
                        .prop('disabled', true)
                        .css({ 'cursor': 'not-allowed', 'opacity': '0.6' });
                } else {
                    $('#balance-warning-message').hide();
                    $('#appointment-form .jet-form-builder__action-button.jet-form-builder__submit')
                        .prop('disabled', false)
                        .css({ 'cursor': 'pointer', 'opacity': '1' });
                }
            }
        } else {
            console.error("Could not find the current balance element."); // Debug
        }
    }

    // Attach event listener for provider dropdown change
    $('.appointment-provider').on('change', handleProviderChange);

    // Attach event listener for appointment cost input
    $(document).on('input', '#price', handleBalanceValidation);
});
