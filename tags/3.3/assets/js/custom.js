jQuery(document).ready(function($) {
    // Check if the notice has been dismissed
    if (localStorage.getItem('dismissed_notice')) {
        // Remove the notice from the DOM
        $('#wp-online-active-users-notice').remove();
    }

    // Handle the close button click
    $('#wp-online-active-users-notice .notice-dismiss').on('click', function() {
        // Remove the notice from the DOM
        $('#wp-online-active-users-notice').remove();

        // Set a flag in local storage to indicate notice dismissal
        localStorage.setItem('dismissed_notice', true);
    });
});

jQuery(document).ready(function($) {
    $('#deactivate-online-active-users').on('click', function() {
        localStorage.removeItem('dismissed_notice');
    });
});