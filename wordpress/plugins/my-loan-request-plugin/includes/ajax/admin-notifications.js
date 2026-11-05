jQuery(document).ready(function($) {

    // Function to fetch and display notifications
    function mlr_fetch_notifications() {
        $.ajax({
            url: ajaxurl, // Assuming ajaxurl is defined by WordPress
            type: 'POST',
            data: {
                action: 'mlr_get_admin_notifications', // Define this function to get notifications
                nonce: 'YOUR_NONCE', // Replace with your actual nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the notification list and badge
                    $('#mlr-notification-list').html(response.data.notifications);
                    $('#mlr-notification-badge').text(response.data.unread_count);

                    // Hide the badge if there are no unread notifications
                    if (response.data.unread_count == 0) {
                        $('#mlr-notification-badge').hide();
                    } else {
                        $('#mlr-notification-badge').show();
                    }
                } else {
                    console.error('Failed to fetch notifications:', response.data);
                }
            }
        });
    }

    // Toggle notification list visibility
    $('#mlr-notification-menu').click(function() {
        $('#mlr-notification-list').toggle();
    });

    // Mark a notification as read (example)
    $(document).on('click', '.mlr-notification-item', function() {
        // Implement AJAX call to mark as read and update UI
    });

    mlr_fetch_notifications();
});