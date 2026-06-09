<?php
/**
 * Fired when the plugin is uninstalled.
 * Cleans up all Jukebox settings, logs, transients, and custom post types.
 */

// Exit if uninstall is not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// THE GATEKEEPER: Check if the user explicitly opted to wipe data
if ( ! get_option( 'crjb_wipe_on_uninstall' ) ) {
    // If they didn't check the box, silently exit and preserve all data.
    exit;
}

global $wpdb;

// 1. Delete standard static options
$options_to_delete = [
    'crjb_enable_submissions',
    'crjb_allow_explicit',
    'crjb_exclude_licensed',
    'crjb_strict_event_mode',
    'crjb_submission_url',
    'crjb_broadcast_log',
    'crjb_broadcast_log_months',
    'crjb_catalog_version',
    'crjb_wipe_on_uninstall'
];

foreach ( $options_to_delete as $option ) {
    delete_option( $option );
}

// 2. Delete dynamic station options, sync data, and transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'crjb_station_args_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'crjb_now_playing_sync_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'crjb_play_history_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'crjb_active_listeners_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'crjb_broadcast_log_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_crjb_active_queue_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_crjb_active_queue_%'" );

// 3. Delete Custom Post Types (Songs and Schedules)
$crjb_posts = get_posts( [
    'post_type'   => [ 'crjb_song', 'crjb_schedule' ],
    'numberposts' => -1,
    'post_status' => 'any',
    'fields'      => 'ids'
] );

if ( ! empty( $crjb_posts ) ) {
    foreach ( $crjb_posts as $post_id ) {
        // Force delete bypasses the trash bin
        wp_delete_post( $post_id, true ); 
    }
}
