<?php
if (! defined('ABSPATH')) exit;

/**
 * Generate a human friendly booking reference, e.g. ORC-240712-0182
 */
function oracle_booking_generate_reference($id)
{
    return 'ORC-' . date('ymd') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

/**
 * Send a styled HTML notification to the site admin so a lead can be actioned quickly.
 */
function oracle_booking_send_admin_notification($booking)
{
    $to        = get_option('admin_email');
    $site_name = get_bloginfo('name');
    $subject   = sprintf('New Booking Lead — %s (%s)', $booking['full_name'], $booking['reference']);
    $admin_url = admin_url('admin.php?page=oracle-bookings&view=' . $booking['id']);

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f9fafb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.05);border:1px solid #e5e7eb;">
                        <tr>
                            <td style="padding:40px;text-align:center;background:#1e3a8a;border-bottom:3px solid #3b82f6;">
                                <div style="color:#93c5fd;font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;margin-bottom:8px;">Action Required</div>
                                <div style="color:#ffffff;font-size:24px;font-weight:700;letter-spacing:0.5px;">New Booking Lead</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:40px;">
                                <p style="color:#374151;font-size:16px;line-height:1.6;margin:0 0 24px 0;">A new booking request has been submitted on <strong><?php echo esc_html($site_name); ?></strong>. Please review the details below and take action from the dashboard.</p>

                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;border-radius:8px;border:1px solid #e5e7eb;">
                                    <tr>
                                        <td style="padding:20px;border-bottom:1px solid #e5e7eb;">
                                            <span style="color:#6b7280;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Booking Reference</span><br>
                                            <span style="color:#111827;font-size:18px;font-weight:700;"><?php echo esc_html($booking['reference']); ?></span>
                                        </td>
                                    </tr>
                                    <?php
                                    $rows = [
                                        'Customer Name'    => $booking['full_name'],
                                        'Phone Number'     => $booking['phone'],
                                        'Email Address'    => $booking['email'],
                                        'Journey Type'     => ucfirst($booking['journey_type']),
                                        'Vehicle'          => $booking['vehicle'] . ' (' . $booking['vehicle_price'] . ')',
                                        'Passengers'       => $booking['passengers'] . ' | Suitcases: ' . $booking['suitcases'],
                                        'Pickup'           => $booking['pickup_address'],
                                        'Destination'      => $booking['destination'],
                                        'Date & Time'      => $booking['pickup_date'] . ' at ' . $booking['pickup_time'],
                                        'Flight Number'    => $booking['flight_number'] ?: '—',
                                        'Special Requests' => $booking['special_requests'] ?: '—',
                                    ];
                                    foreach ($rows as $label => $value): ?>
                                        <tr>
                                            <td style="padding:16px 20px;border-bottom:1px solid #e5e7eb;">
                                                <span style="color:#6b7280;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;"><?php echo esc_html($label); ?></span><br>
                                                <span style="color:#111827;font-size:15px;font-weight:500;white-space:pre-wrap;"><?php echo esc_html($value); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>

                                <div style="text-align:center;margin-top:32px;">
                                    <a href="<?php echo esc_url($admin_url); ?>" style="display:inline-block;background:#3b82f6;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:14px 28px;border-radius:8px;">View &amp; Action Lead</a>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 40px;background:#f9fafb;text-align:center;border-top:1px solid #e5e7eb;">
                                <p style="color:#6b7280;font-size:13px;margin:0;">This is an automated notification from your website.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    if (! empty($booking['email'])) {
        $headers[] = 'Reply-To: ' . $booking['full_name'] . ' <' . $booking['email'] . '>';
    }

    return wp_mail($to, $subject, $html, $headers);
}

/**
 * Send a styled HTML "invoice" style confirmation to the customer.
 */
function oracle_booking_send_customer_invoice($booking)
{
    $site_name = get_bloginfo('name');
    $to        = $booking['email'];
    $subject   = sprintf('Your booking is confirmed — %s (%s)', $site_name, $booking['reference']);

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f9fafb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.05);border:1px solid #e5e7eb;">
                        <tr>
                            <td style="padding:40px;text-align:center;background:#111827;border-bottom:3px solid #d4af37;">
                                <div style="color:#d4af37;font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;margin-bottom:8px;">Booking Confirmed</div>
                                <div style="color:#ffffff;font-size:24px;font-weight:700;letter-spacing:0.5px;"><?php echo esc_html($site_name); ?></div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:40px;">
                                <p style="color:#374151;font-size:16px;line-height:1.6;margin:0 0 16px 0;">Dear <?php echo esc_html($booking['full_name']); ?>,</p>
                                <p style="color:#4b5563;font-size:15px;line-height:1.6;margin:0 0 32px 0;">Thank you for choosing <strong><?php echo esc_html($site_name); ?></strong>. Your booking has been successfully confirmed. Below is a summary of your journey details.</p>

                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;border-radius:8px;border:1px solid #e5e7eb;">
                                    <tr>
                                        <td style="padding:20px;border-bottom:1px solid #e5e7eb;">
                                            <span style="color:#6b7280;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Booking Reference</span><br>
                                            <span style="color:#111827;font-size:18px;font-weight:700;"><?php echo esc_html($booking['reference']); ?></span>
                                        </td>
                                    </tr>
                                    <?php
                                    $rows = [
                                        'Journey Type'     => ucfirst($booking['journey_type']),
                                        'Vehicle'          => $booking['vehicle'] . ' (' . $booking['vehicle_price'] . ')',
                                        'Passengers'       => $booking['passengers'],
                                        'Pickup'           => $booking['pickup_address'],
                                        'Destination'      => $booking['destination'],
                                        'Date & Time'      => $booking['pickup_date'] . ' at ' . $booking['pickup_time'],
                                    ];
                                    foreach ($rows as $label => $value): ?>
                                        <tr>
                                            <td style="padding:16px 20px;border-bottom:1px solid #e5e7eb;">
                                                <span style="color:#6b7280;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;"><?php echo esc_html($label); ?></span><br>
                                                <span style="color:#111827;font-size:15px;font-weight:500;"><?php echo esc_html($value); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>

                                <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:24px 0 0 0;">Our team will allocate your driver and share their details prior to the journey. If you need to make any changes, simply reply to this email.</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 40px;background:#f9fafb;text-align:center;border-top:1px solid #e5e7eb;">
                                <p style="color:#6b7280;font-size:13px;margin:0;">&copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_email = get_option('admin_email');
    $headers[]  = 'From: ' . $site_name . ' <' . $from_email . '>';

    return wp_mail($to, $subject, $html, $headers);
}

/**
 * Send a styled HTML rejection notification to the customer.
 */
function oracle_booking_send_customer_rejection($booking)
{
    $site_name = get_bloginfo('name');
    $to        = $booking['email'];
    $subject   = sprintf('Update regarding your booking — %s (%s)', $site_name, $booking['reference']);

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f9fafb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.05);border:1px solid #e5e7eb;">
                        <tr>
                            <td style="padding:40px;text-align:center;background:#111827;border-bottom:3px solid #ef4444;">
                                <div style="color:#ef4444;font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;margin-bottom:8px;">Booking Update</div>
                                <div style="color:#ffffff;font-size:24px;font-weight:700;letter-spacing:0.5px;"><?php echo esc_html($site_name); ?></div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:40px;">
                                <p style="color:#374151;font-size:16px;line-height:1.6;margin:0 0 16px 0;">Dear <?php echo esc_html($booking['full_name']); ?>,</p>
                                <p style="color:#4b5563;font-size:15px;line-height:1.6;margin:0 0 24px 0;">Thank you for your interest in <strong><?php echo esc_html($site_name); ?></strong>. We have reviewed your booking request for reference <strong><?php echo esc_html($booking['reference']); ?></strong>.</p>
                                <p style="color:#4b5563;font-size:15px;line-height:1.6;margin:0 0 32px 0;">Unfortunately, we are unable to fulfill this journey at the requested date and time due to lack of availability.</p>
                                
                                <div style="background:#fef2f2;border-left:4px solid #ef4444;padding:16px 20px;border-radius:0 8px 8px 0;margin-bottom:32px;">
                                    <p style="color:#991b1b;font-size:14px;margin:0;line-height:1.5;"><strong>What happens next?</strong><br>Your booking has been cancelled and no charges have been made. We sincerely apologize for any inconvenience caused.</p>
                                </div>

                                <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0;">If you would like to discuss alternative times or have any questions, please reply directly to this email and our team will be happy to assist you.</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 40px;background:#f9fafb;text-align:center;border-top:1px solid #e5e7eb;">
                                <p style="color:#6b7280;font-size:13px;margin:0;">&copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_email = get_option('admin_email');
    $headers[]  = 'From: ' . $site_name . ' <' . $from_email . '>';

    return wp_mail($to, $subject, $html, $headers);
}
