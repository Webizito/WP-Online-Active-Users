<?php
// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

// delete plugin transient
delete_transient('users_status');

// Clear any cached data or transients
wp_cache_flush();