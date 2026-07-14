<?php
/**
 * Plugin Name: Oracle Booking Form
 * Description: 3-step AJAX booking form (shortcode [oracle_booking_form]) with a lead dashboard, Accept/Reject workflow, and an SMTP settings page so emails actually deliver.
 * Version: 1.2.0
 * Author: Oracle Private Hire
 * Text Domain: oracle-booking
 */

if (! defined('ABSPATH')) exit; // no direct access

define('ORACLE_BOOKING_VERSION', '1.3.1');
define('ORACLE_BOOKING_PATH', plugin_dir_path(__FILE__));
define('ORACLE_BOOKING_URL', plugin_dir_url(__FILE__));
define('ORACLE_BOOKING_TABLE', 'oracle_bookings');

/**
 * Create DB table on activation
 */
function oracle_booking_activate()
{
    global $wpdb;
    $table_name      = $wpdb->prefix . ORACLE_BOOKING_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        reference VARCHAR(20) NOT NULL,
        journey_type VARCHAR(50) NOT NULL,
        passengers SMALLINT NOT NULL DEFAULT 1,
        pickup_address TEXT NOT NULL,
        pickup_lat VARCHAR(30) NULL,
        pickup_lng VARCHAR(30) NULL,
        destination TEXT NOT NULL,
        destination_lat VARCHAR(30) NULL,
        destination_lng VARCHAR(30) NULL,
        pickup_date DATE NULL,
        pickup_time TIME NULL,
        suitcases SMALLINT NULL,
        flight_number VARCHAR(50) NULL,
        vehicle VARCHAR(100) NOT NULL,
        vehicle_price VARCHAR(50) NULL,
        special_requests TEXT NULL,
        full_name VARCHAR(150) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        email VARCHAR(150) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'new',
        email_sent TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    add_option('oracle_booking_db_version', ORACLE_BOOKING_VERSION);
}
register_activation_hook(__FILE__, 'oracle_booking_activate');

/**
 * Auto-upgrade the DB schema on version bumps (adds new columns to sites
 * that installed an earlier version of the plugin, no reactivation needed).
 */
function oracle_booking_maybe_upgrade()
{
    if (get_option('oracle_booking_db_version') !== ORACLE_BOOKING_VERSION) {
        oracle_booking_activate();
        update_option('oracle_booking_db_version', ORACLE_BOOKING_VERSION);
    }
}
add_action('plugins_loaded', 'oracle_booking_maybe_upgrade');

/**
 * Includes
 */
require_once ORACLE_BOOKING_PATH . 'includes/shortcode.php';
require_once ORACLE_BOOKING_PATH . 'includes/ajax-handler.php';
require_once ORACLE_BOOKING_PATH . 'includes/emails.php';
require_once ORACLE_BOOKING_PATH . 'includes/admin-page.php';
require_once ORACLE_BOOKING_PATH . 'includes/smtp-settings.php';

/**
 * NOTE on front-end CSS/JS: instead of relying on wp_enqueue_scripts +
 * wp_head()/wp_footer() (which broke on this theme because the shortcode
 * is echoed directly inside a PHP template, and/or the theme's footer
 * doesn't call wp_footer() reliably), the form's CSS and JS are output
 * INLINE directly inside the shortcode's own HTML in includes/shortcode.php.
 * This guarantees the styles/scripts are always present no matter how or
 * where the shortcode is used.
 */
