jQuery(document).ready(function ($) {
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
