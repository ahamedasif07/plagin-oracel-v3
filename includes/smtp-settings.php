<?php
if (! defined('ABSPATH')) exit;

/**
 * SMTP Settings
 * -------------
 * Many hosts block or silently drop PHP's default mail() function, which is
 * what wp_mail() uses unless something reconfigures it. This page lets the
 * admin plug in real SMTP credentials (Gmail, SendGrid, Mailgun, cPanel
 * mail, etc.) from inside wp-admin — no separate file or plugin needed.
 */

function oracle_booking_smtp_menu()
{
    add_submenu_page(
        'oracle-bookings',
        'Email / SMTP Settings',
        'Email Settings',
        'manage_options',
        'oracle-bookings-smtp',
        'oracle_booking_smtp_page'
    );
}
add_action('admin_menu', 'oracle_booking_smtp_menu');

function oracle_booking_smtp_defaults()
{
    return [
        'enabled'     => 0,
        'host'        => '',
        'port'        => 587,
        'encryption'  => 'tls', // tls | ssl | none
        'username'    => '',
        'password'    => '',
        'from_email'  => get_option('admin_email'),
        'from_name'   => get_bloginfo('name'),
    ];
}

function oracle_booking_get_smtp_settings()
{
    $saved = get_option('oracle_booking_smtp', []);
    return wp_parse_args($saved, oracle_booking_smtp_defaults());
}

function oracle_booking_smtp_page()
{
    if (! current_user_can('manage_options')) return;

    $notice = '';

    // Save settings
    if (isset($_POST['oracle_booking_smtp_save']) && check_admin_referer('oracle_booking_smtp_save_action')) {
        $existing = oracle_booking_get_smtp_settings();
        $settings = [
            'enabled'    => isset($_POST['enabled']) ? 1 : 0,
            'host'       => sanitize_text_field($_POST['host'] ?? ''),
            'port'       => intval($_POST['port'] ?? 587),
            'encryption' => in_array($_POST['encryption'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? $_POST['encryption'] : 'tls',
            'username'   => sanitize_text_field($_POST['username'] ?? ''),
            // Only overwrite the stored password if a new one was typed in
            // (the field is left blank on reload for security).
            'password'   => $_POST['password'] !== '' ? $_POST['password'] : $existing['password'],
            'from_email' => sanitize_email($_POST['from_email'] ?? ''),
            'from_name'  => sanitize_text_field($_POST['from_name'] ?? ''),
        ];
        update_option('oracle_booking_smtp', $settings);
        $notice = '<div class="notice notice-success is-dismissible"><p>SMTP settings saved.</p></div>';
    }

    // Send test email
    if (isset($_POST['oracle_booking_smtp_test']) && check_admin_referer('oracle_booking_smtp_test_action')) {
        $test_to = sanitize_email($_POST['test_email'] ?? get_option('admin_email'));
        $sent = wp_mail($test_to, 'Oracle Booking — SMTP test email', "This is a test email sent from the Oracle Booking plugin's Email Settings page.\n\nIf you received this, your SMTP setup is working correctly.");
        $notice = $sent
            ? '<div class="notice notice-success is-dismissible"><p>Test email sent to ' . esc_html($test_to) . '. Please check the inbox (and spam folder).</p></div>'
            : '<div class="notice notice-error is-dismissible"><p>Test email failed to send. Double-check your SMTP host, port, username and password below.</p></div>';
    }

    $s = oracle_booking_get_smtp_settings();
    ?>
    <div class="wrap">
        <h1>Email / SMTP Settings</h1>
        <p>By default WordPress tries to send email using your server's built-in <code>mail()</code> function, which many hosts block or mark as spam. Fill in your SMTP provider's details below (Gmail, Outlook, SendGrid, Mailgun, your hosting company's mail server, etc.) so booking notifications and customer confirmation emails actually arrive.</p>
        <?php echo $notice; ?>

        <form method="post" style="max-width:640px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:24px;margin-bottom:24px;">
            <?php wp_nonce_field('oracle_booking_smtp_save_action'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable SMTP</th>
                    <td>
                        <label><input type="checkbox" name="enabled" value="1" <?php checked($s['enabled'], 1); ?>> Send emails through the SMTP server below instead of the server's default mail()</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ob-host">SMTP Host</label></th>
                    <td><input id="ob-host" type="text" name="host" value="<?php echo esc_attr($s['host']); ?>" class="regular-text" placeholder="smtp.gmail.com"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ob-port">SMTP Port</label></th>
                    <td>
                        <input id="ob-port" type="number" name="port" value="<?php echo esc_attr($s['port']); ?>" class="small-text">
                        <p class="description">Common ports: 587 (TLS), 465 (SSL), 25 (none)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ob-enc">Encryption</label></th>
                    <td>
                        <select id="ob-enc" name="encryption">
                            <option value="tls" <?php selected($s['encryption'], 'tls'); ?>>TLS (recommended)</option>
                            <option value="ssl" <?php selected($s['encryption'], 'ssl'); ?>>SSL</option>
                            <option value="none" <?php selected($s['encryption'], 'none'); ?>>None</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ob-user">SMTP Username</label></th>
                    <td><input id="ob-user" type="text" name="username" value="<?php echo esc_attr($s['username']); ?>" class="regular-text" autocomplete="off"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ob-pass">SMTP Password</label></th>
                    <td>
                        <input id="ob-pass" type="password" name="password" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $s['password'] ? '••••••••  (leave blank to keep current password)' : ''; ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ob-from-email">From Email</label></th>
                    <td><input id="ob-from-email" type="email" name="from_email" value="<?php echo esc_attr($s['from_email']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ob-from-name">From Name</label></th>
                    <td><input id="ob-from-name" type="text" name="from_name" value="<?php echo esc_attr($s['from_name']); ?>" class="regular-text"></td>
                </tr>
            </table>
            <button type="submit" name="oracle_booking_smtp_save" value="1" class="button button-primary">Save SMTP Settings</button>
        </form>

        <form method="post" style="max-width:640px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:24px;">
            <h2 style="margin-top:0;">Send a test email</h2>
            <?php wp_nonce_field('oracle_booking_smtp_test_action'); ?>
            <input type="email" name="test_email" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text" placeholder="you@example.com">
            <button type="submit" name="oracle_booking_smtp_test" value="1" class="button">Send Test Email</button>
        </form>
    </div>
    <?php
}

/**
 * Route wp_mail() through the configured SMTP server.
 */
function oracle_booking_configure_phpmailer($phpmailer)
{
    $s = oracle_booking_get_smtp_settings();

    if (empty($s['enabled']) || empty($s['host'])) {
        return; // fall back to default mail() behaviour
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = $s['host'];
    $phpmailer->Port       = $s['port'];
    $phpmailer->SMTPAuth   = ! empty($s['username']);
    $phpmailer->Username   = $s['username'];
    $phpmailer->Password   = $s['password'];
    $phpmailer->SMTPSecure = $s['encryption'] === 'none' ? '' : $s['encryption'];
    $phpmailer->SMTPAutoTLS = $s['encryption'] !== 'none';

    if (! empty($s['from_email'])) {
        $phpmailer->setFrom($s['from_email'], $s['from_name'] ?: get_bloginfo('name'));
    }
}
add_action('phpmailer_init', 'oracle_booking_configure_phpmailer');

/**
 * Surface mail failures in the admin, and in the accept-lead notice logic
 * (see includes/admin-page.php), instead of failing silently.
 */
add_action('wp_mail_failed', function ($wp_error) {
    error_log('Oracle Booking wp_mail failed: ' . $wp_error->get_error_message());
});
