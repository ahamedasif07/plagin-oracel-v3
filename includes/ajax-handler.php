<?php
if (! defined('ABSPATH')) exit;

function oracle_booking_handle_ajax_submit()
{
    check_ajax_referer('oracle_booking_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . ORACLE_BOOKING_TABLE;

    $full_name = sanitize_text_field($_POST['full_name'] ?? '');
    $phone     = sanitize_text_field($_POST['phone'] ?? '');
    $email     = sanitize_email($_POST['email'] ?? '');
    $pickup    = sanitize_text_field($_POST['pickup_address'] ?? '');
    $dest      = sanitize_text_field($_POST['destination'] ?? '');
    $vehicle   = sanitize_text_field($_POST['vehicle'] ?? '');

    // Basic required-field validation
    if (! $full_name || ! $phone || ! is_email($email) || ! $pickup || ! $dest || ! $vehicle) {
        wp_send_json_error(['message' => 'Please fill in all required fields with valid information.'], 400);
    }

    // Look up the price label for the chosen vehicle
    $vehicle_price = '';
    foreach (oracle_booking_vehicle_options() as $v) {
        if ($v['name'] === $vehicle) {
            $vehicle_price = $v['price'];
            break;
        }
    }

    $data = [
        'reference'        => '', // filled after insert
        'journey_type'     => sanitize_text_field($_POST['journey_type'] ?? ''),
        'passengers'       => intval($_POST['passengers'] ?? 1),
        'pickup_address'   => $pickup,
        'pickup_lat'       => sanitize_text_field($_POST['pickup_lat'] ?? ''),
        'pickup_lng'       => sanitize_text_field($_POST['pickup_lng'] ?? ''),
        'destination'      => $dest,
        'destination_lat'  => sanitize_text_field($_POST['destination_lat'] ?? ''),
        'destination_lng'  => sanitize_text_field($_POST['destination_lng'] ?? ''),
        'pickup_date'      => sanitize_text_field($_POST['pickup_date'] ?? ''),
        'pickup_time'      => sanitize_text_field($_POST['pickup_time'] ?? ''),
        'suitcases'        => intval($_POST['suitcases'] ?? 0),
        'flight_number'    => sanitize_text_field($_POST['flight_number'] ?? ''),
        'vehicle'          => $vehicle,
        'vehicle_price'    => $vehicle_price,
        'special_requests' => sanitize_textarea_field($_POST['special_requests'] ?? ''),
        'full_name'        => $full_name,
        'phone'            => $phone,
        'email'            => $email,
        'status'           => 'new',
        'created_at'       => current_time('mysql'),
    ];

    $inserted = $wpdb->insert($table, $data);

    if (false === $inserted) {
        wp_send_json_error(['message' => 'Something went wrong saving your booking. Please try again.'], 500);
    }

    $booking_id      = $wpdb->insert_id;
    $reference       = oracle_booking_generate_reference($booking_id);
    $wpdb->update($table, ['reference' => $reference], ['id' => $booking_id]);

    $data['id']        = $booking_id;
    $data['reference'] = $reference;

    // Notify the admin immediately so the lead can be reviewed.
    // The customer only receives an email once the admin explicitly
    // accepts the booking from the dashboard (see includes/admin-page.php).
    oracle_booking_send_admin_notification($data);

    wp_send_json_success([
        'message'   => 'Booking received.',
        'reference' => $reference,
    ]);
}
add_action('wp_ajax_oracle_booking_submit', 'oracle_booking_handle_ajax_submit');
add_action('wp_ajax_nopriv_oracle_booking_submit', 'oracle_booking_handle_ajax_submit');
