<?php
if (! defined('ABSPATH')) exit;

function oracle_booking_admin_menu()
{
    add_menu_page(
        'Bookings',
        'Bookings',
        'manage_options',
        'oracle-bookings',
        'oracle_booking_admin_page',
        'dashicons-car',
        26
    );
}
add_action('admin_menu', 'oracle_booking_admin_menu');

function oracle_booking_status_badge($status)
{
    $map = [
        'new'       => '#d4af37',
        'confirmed' => '#2f9e44',
        'completed' => '#1c7ed6',
        'rejected'  => '#e03131',
    ];
    $color = $map[$status] ?? '#888';
    return '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#fff;background:' . esc_attr($color) . ';">' . esc_html(ucfirst($status)) . '</span>';
}

/**
 * Accept a lead: mark it confirmed and, if we haven't already, email the
 * customer their confirmation/invoice. Safe to call more than once —
 * the email only ever goes out a single time per booking.
 */
function oracle_booking_accept_lead($id, $table)
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    if (! $row) return false;

    $wpdb->update($table, ['status' => 'confirmed'], ['id' => $id]);

    if (empty($row['email_sent'])) {
        $sent = oracle_booking_send_customer_invoice($row);
        if ($sent) {
            $wpdb->update($table, ['email_sent' => 1], ['id' => $id]);
        }
        return $sent;
    }
    return true; // already sent previously
}

/**
 * Reject a lead: mark it rejected and send the customer a rejection notification.
 */
function oracle_booking_reject_lead($id, $table)
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    if (! $row) return false;

    $wpdb->update($table, ['status' => 'rejected'], ['id' => $id]);

    if (empty($row['email_sent'])) {
        $sent = oracle_booking_send_customer_rejection($row);
        if ($sent) {
            $wpdb->update($table, ['email_sent' => 1], ['id' => $id]);
        }
        return $sent;
    }
    return true;
}

function oracle_booking_admin_page()
{
    if (! current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . ORACLE_BOOKING_TABLE;

    // Handle actions (accept / reject / update_status / delete)
    if (isset($_POST['oracle_booking_action']) && check_admin_referer('oracle_booking_admin_action')) {
        $id     = intval($_POST['booking_id'] ?? 0);
        $action = sanitize_text_field($_POST['oracle_booking_action']);

        if ($id && $action === 'accept') {
            $sent = oracle_booking_accept_lead($id, $table);
            echo $sent
                ? '<div class="notice notice-success is-dismissible"><p>Booking accepted — confirmation email sent to the customer.</p></div>'
                : '<div class="notice notice-warning is-dismissible"><p>Booking accepted, but the confirmation email could not be sent. Check your site\'s email/SMTP settings.</p></div>';
        } elseif ($id && $action === 'reject') {
            $sent = oracle_booking_reject_lead($id, $table);
            echo $sent
                ? '<div class="notice notice-success is-dismissible"><p>Booking rejected — email notification sent to the customer.</p></div>'
                : '<div class="notice notice-warning is-dismissible"><p>Booking rejected, but the email could not be sent. Check your site\'s email/SMTP settings.</p></div>';
        } elseif ($id && $action === 'update_status') {
            $status = sanitize_text_field($_POST['status'] ?? 'new');
            $wpdb->update($table, ['status' => $status], ['id' => $id]);
            echo '<div class="notice notice-success is-dismissible"><p>Status updated.</p></div>';
        } elseif ($id && $action === 'delete') {
            $wpdb->delete($table, ['id' => $id]);
            echo '<div class="notice notice-success is-dismissible"><p>Lead deleted.</p></div>';
            echo '<script>setTimeout(function(){window.location = "' . esc_url_raw(admin_url('admin.php?page=oracle-bookings')) . '";}, 600);</script>';
        }
    }

    $view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;

    echo '<div class="wrap oracle-booking-admin">';
    echo '<h1 class="wp-heading-inline">Bookings</h1>';

    if ($view_id) {
        oracle_booking_admin_render_detail($view_id, $table);
    } else {
        oracle_booking_admin_render_list($table);
    }

    echo '</div>';
}

function oracle_booking_admin_render_list($table)
{
    global $wpdb;

    $status_filter = sanitize_text_field($_GET['status'] ?? '');
    $where = '';
    if ($status_filter) {
        $where = $wpdb->prepare(' WHERE status = %s', $status_filter);
    }

    $rows   = $wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY created_at DESC");
    $counts = $wpdb->get_results("SELECT status, COUNT(*) as c FROM {$table} GROUP BY status", OBJECT_K);
    $total  = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

    $statuses = ['new', 'confirmed', 'completed', 'rejected'];
    ?>
    <ul class="subsubsub">
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=oracle-bookings')); ?>" class="<?php echo ! $status_filter ? 'current' : ''; ?>">All <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
        <?php foreach ($statuses as $i => $s):
            $c = isset($counts[$s]) ? intval($counts[$s]->c) : 0; ?>
            <li><a href="<?php echo esc_url(admin_url('admin.php?page=oracle-bookings&status=' . $s)); ?>" class="<?php echo $status_filter === $s ? 'current' : ''; ?>"><?php echo esc_html(ucfirst($s)); ?> <span class="count">(<?php echo $c; ?>)</span></a><?php echo $i < count($statuses) - 1 ? ' |' : ''; ?></li>
        <?php endforeach; ?>
    </ul>
    <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Customer</th>
                <th>Journey</th>
                <th>Vehicle</th>
                <th>Pickup</th>
                <th>Status</th>
                <th>Submitted</th>
                <th style="width:230px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows): foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?php echo esc_html($row->reference); ?></strong></td>
                    <td><?php echo esc_html($row->full_name); ?><br><small><?php echo esc_html($row->email); ?></small></td>
                    <td><?php echo esc_html($row->pickup_address); ?> → <?php echo esc_html($row->destination); ?></td>
                    <td><?php echo esc_html($row->vehicle); ?></td>
                    <td><?php echo esc_html($row->pickup_date . ' ' . $row->pickup_time); ?></td>
                    <td><?php echo oracle_booking_status_badge($row->status); ?></td>
                    <td><?php echo esc_html(mysql2date('d M Y, H:i', $row->created_at)); ?></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=oracle-bookings&view=' . $row->id)); ?>">View</a>
                        <?php if ($row->status === 'new'): ?>
                            <form method="post" style="display:inline-block;">
                                <?php wp_nonce_field('oracle_booking_admin_action'); ?>
                                <input type="hidden" name="oracle_booking_action" value="accept">
                                <input type="hidden" name="booking_id" value="<?php echo intval($row->id); ?>">
                                <button class="button button-small button-primary">Accept</button>
                            </form>
                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Reject this booking? A rejection email WILL be sent to the customer.');">
                                <?php wp_nonce_field('oracle_booking_admin_action'); ?>
                                <input type="hidden" name="oracle_booking_action" value="reject">
                                <input type="hidden" name="booking_id" value="<?php echo intval($row->id); ?>">
                                <button class="button button-small" style="color:#e03131;border-color:#e03131;">Reject</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8">No booking leads yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <script>
    (function() {
        var isFetching = false;

        function refreshOracleBookingsTable() {
            if (document.hidden || isFetching) {
                return;
            }

            isFetching = true;
            var refreshUrl = new URL(window.location.href);
            refreshUrl.searchParams.set('oracle_booking_refresh', Date.now().toString());

            fetch(refreshUrl.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store'
                })
                .then(function(response) {
                    return response.text();
                })
                .then(function(html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var nextBody = doc.querySelector('.wp-list-table tbody');
                    var currentBody = document.querySelector('.wp-list-table tbody');

                    if (nextBody && currentBody && nextBody.innerHTML !== currentBody.innerHTML) {
                        currentBody.innerHTML = nextBody.innerHTML;
                    }

                    var nextSub = doc.querySelector('.subsubsub');
                    var currentSub = document.querySelector('.subsubsub');
                    if (nextSub && currentSub && nextSub.innerHTML !== currentSub.innerHTML) {
                        currentSub.innerHTML = nextSub.innerHTML;
                    }
                })
                .catch(function() {})
                .finally(function() {
                    isFetching = false;
                });
        }

        window.setInterval(refreshOracleBookingsTable, 2500);

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshOracleBookingsTable();
            }
        });
    })();
    </script>
    <?php
}

function oracle_booking_admin_render_detail($id, $table)
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

    if (! $row) {
        echo '<p>Lead not found.</p><p><a href="' . esc_url(admin_url('admin.php?page=oracle-bookings')) . '">&larr; Back to list</a></p>';
        return;
    }
    ?>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=oracle-bookings')); ?>">&larr; Back to all leads</a></p>
    <div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:10px;">
        <div style="flex:2;min-width:340px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:24px;">
            <h2 style="margin-top:0;"><?php echo esc_html($row->reference); ?> <?php echo oracle_booking_status_badge($row->status); ?></h2>
            <table class="form-table">
                <tr><th>Full name</th><td><?php echo esc_html($row->full_name); ?></td></tr>
                <tr><th>Phone</th><td><?php echo esc_html($row->phone); ?></td></tr>
                <tr><th>Email</th><td><?php echo esc_html($row->email); ?></td></tr>
                <tr><th>Journey type</th><td><?php echo esc_html(ucfirst($row->journey_type)); ?></td></tr>
                <tr><th>Passengers</th><td><?php echo esc_html($row->passengers); ?></td></tr>
                <tr><th>Suitcases</th><td><?php echo esc_html($row->suitcases); ?></td></tr>
                <tr><th>Pickup address</th><td><?php echo esc_html($row->pickup_address); ?></td></tr>
                <tr><th>Destination</th><td><?php echo esc_html($row->destination); ?></td></tr>
                <tr><th>Pickup date</th><td><?php echo esc_html($row->pickup_date); ?></td></tr>
                <tr><th>Pickup time</th><td><?php echo esc_html($row->pickup_time); ?></td></tr>
                <tr><th>Flight number</th><td><?php echo esc_html($row->flight_number ?: '—'); ?></td></tr>
                <tr><th>Vehicle</th><td><?php echo esc_html($row->vehicle . ' (' . $row->vehicle_price . ')'); ?></td></tr>
                <tr><th>Special requests</th><td><?php echo esc_html($row->special_requests ?: '—'); ?></td></tr>
                <tr><th>Confirmation email</th><td><?php echo $row->email_sent ? '✅ Sent to customer' : '— Not sent yet'; ?></td></tr>
                <tr><th>Submitted</th><td><?php echo esc_html(mysql2date('d M Y, H:i', $row->created_at)); ?></td></tr>
            </table>
        </div>
        <div style="flex:1;min-width:260px;">
            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:20px;margin-bottom:16px;">
                <h3 style="margin-top:0;">Review this lead</h3>
                <p style="color:#666;font-size:13px;">Accepting sends the customer a confirmation email. Rejecting sends a rejection email.</p>
                <form method="post" style="margin-bottom:8px;">
                    <?php wp_nonce_field('oracle_booking_admin_action'); ?>
                    <input type="hidden" name="oracle_booking_action" value="accept">
                    <input type="hidden" name="booking_id" value="<?php echo intval($row->id); ?>">
                    <button class="button button-primary" style="width:100%;">✔ Accept &amp; email customer</button>
                </form>
                <form method="post" onsubmit="return confirm('Reject this booking? A rejection email WILL be sent to the customer.');">
                    <?php wp_nonce_field('oracle_booking_admin_action'); ?>
                    <input type="hidden" name="oracle_booking_action" value="reject">
                    <input type="hidden" name="booking_id" value="<?php echo intval($row->id); ?>">
                    <button class="button" style="width:100%;color:#e03131;border-color:#e03131;">✕ Reject &amp; email customer</button>
                </form>
            </div>
            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:20px;margin-bottom:16px;">
                <h3 style="margin-top:0;">Other status</h3>
                <form method="post">
                    <?php wp_nonce_field('oracle_booking_admin_action'); ?>
                    <input type="hidden" name="oracle_booking_action" value="update_status">
                    <input type="hidden" name="booking_id" value="<?php echo intval($row->id); ?>">
                    <select name="status" style="width:100%;margin-bottom:10px;">
                        <?php foreach (['new', 'confirmed', 'completed', 'rejected'] as $s): ?>
                            <option value="<?php echo esc_attr($s); ?>" <?php selected($row->status, $s); ?>><?php echo esc_html(ucfirst($s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button" style="width:100%;">Save status (no email sent)</button>
                </form>
            </div>
            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:20px;">
                <h3 style="margin-top:0;">Danger zone</h3>
                <form method="post" onsubmit="return confirm('Delete this lead permanently?');">
                    <?php wp_nonce_field('oracle_booking_admin_action'); ?>
                    <input type="hidden" name="oracle_booking_action" value="delete">
                    <input type="hidden" name="booking_id" value="<?php echo intval($row->id); ?>">
                    <button class="button" style="width:100%;color:#e03131;border-color:#e03131;">Delete lead</button>
                </form>
            </div>
        </div>
    </div>
    <?php
}
