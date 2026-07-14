<?php
if (! defined('ABSPATH')) exit;

function oracle_booking_vehicle_options()
{
    return [
        ['name' => 'Executive Saloon', 'desc' => 'Up to 3 pax · 2 bags', 'price' => 'from £55'],
        ['name' => 'Luxury MPV',       'desc' => 'Up to 7 pax · 7 bags', 'price' => 'from £85'],
        ['name' => 'Prestige SUV',     'desc' => 'Up to 4 pax · 4 bags', 'price' => 'from £95'],
    ];
}

function oracle_booking_render_shortcode($atts = [])
{
    static $printed_assets = false;

    $inputCls   = "ob-input w-full rounded-xl border border-white/10 bg-ink/60 px-4 py-3.5 text-sm text-black placeholder:text-muted-foreground focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold transition-colors";
    $stepNames  = ["Vehicle", "Journey", "Details"];
    $vehicleOptions = oracle_booking_vehicle_options();

    ob_start();

    // Output CSS + JS inline, only once per page load, regardless of
    // whether the theme correctly calls wp_head()/wp_footer().
    if (! $printed_assets) {
        $printed_assets = true;
        $css_path = ORACLE_BOOKING_PATH . 'assets/css/oracle-booking.css';
        $js_path  = ORACLE_BOOKING_PATH . 'assets/js/oracle-booking.js';
        ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <style id="oracle-booking-inline-css"><?php echo file_exists($css_path) ? file_get_contents($css_path) : ''; ?></style>
        <script>
            var oracleBooking = {
                ajax_url: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>",
                nonce: "<?php echo esc_js(wp_create_nonce('oracle_booking_nonce')); ?>"
            };
        </script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script id="oracle-booking-inline-js"><?php echo file_exists($js_path) ? file_get_contents($js_path) : ''; ?></script>
        <?php
    }
    ?>
    <div class="oracle-booking-wrap w-full lg:max-w-6xl xl:max-w-7xl mx-auto">

        <!-- Progress -->
        <div id="booking-progress" class="mb-10 flex items-center justify-center gap-2 sm:gap-4">
            <?php foreach ($stepNames as $i => $s): ?>
                <div class="flex items-center gap-2 sm:gap-4">
                    <div class="flex items-center gap-3">
                        <span class="step-indicator grid h-9 w-9 place-items-center rounded-full border text-sm font-medium transition-all <?php echo $i === 0 ? 'border-gold bg-gold text-ink' : 'border-white/20 text-muted-foreground'; ?>">
                            <?php echo $i + 1; ?>
                        </span>
                        <span class="step-label hidden text-xs uppercase tracking-widest sm:inline <?php echo $i === 0 ? 'text-gold' : 'text-muted-foreground'; ?>"><?php echo esc_html($s); ?></span>
                    </div>
                    <?php if ($i < count($stepNames) - 1): ?>
                        <span class="step-connector h-px w-8 sm:w-16 bg-white/10"></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Success popup (auto closes after 3s unless closed manually) -->
        <div id="ob-success-overlay" class="ob-overlay hidden">
            <div class="ob-success-card">
                <button type="button" id="ob-success-close" class="ob-success-close" aria-label="Close">&times;</button>
                <div class="ob-success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                </div>
                <h2 class="ob-success-title">Booking received.</h2>
                <p class="ob-success-ref"></p>
                <p class="ob-success-text">Thank you. Our team will review your request and, once accepted, you'll receive a confirmation email with your booking invoice.</p>
            </div>
        </div>

        <form id="booking-form" class="glass-card space-y-6 rounded-3xl p-8 md:p-12" method="post" action="" novalidate>
            <?php wp_nonce_field('oracle_booking_nonce', 'oracle_booking_nonce_field'); ?>
            <div id="ob-form-error" class="ob-form-error hidden"></div>

            <!-- Step 0: Vehicle -->
            <div class="booking-step active grid gap-4 sm:grid-cols-3" data-step="0">
                <?php foreach ($vehicleOptions as $i => $v): ?>
                    <label class="cursor-pointer rounded-2xl border border-white/10 bg-ink/60 p-6 transition-all hover:border-gold has-[:checked]:border-gold has-[:checked]:bg-gold/5">
                        <input type="radio" name="vehicle" value="<?php echo esc_attr($v['name']); ?>" data-price="<?php echo esc_attr($v['price']); ?>" <?php echo $i === 0 ? 'checked' : ''; ?> class="sr-only" required>
                        <h3 class="font-display text-xl"><?php echo esc_html($v['name']); ?></h3>
                        <p class="mt-1 text-xs text-muted-foreground"><?php echo esc_html($v['desc']); ?></p>
                        <p class="mt-4 font-display text-2xl text-gradient-gold"><?php echo esc_html($v['price']); ?></p>
                    </label>
                <?php endforeach; ?>
                <div class="sm:col-span-3">
                    <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Special Requests</label>
                    <textarea name="special_requests" rows="3" placeholder="Child seat, additional stop, etc." class="<?php echo esc_attr($inputCls); ?>"></textarea>
                </div>
            </div>

            <!-- Step 1: Journey (form fields + live map, 2 cols on lg, stacked on mobile) -->
            <div class="booking-step grid gap-6 lg:grid-cols-2" data-step="1">

                <div class="grid content-start gap-5 sm:grid-cols-2">
                    <!-- Pickup / Destination first -->
                    <div class="sm:col-span-2 ob-address-field">
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Pickup Address</label>
                        <input type="text" id="ob-pickup-address" name="pickup_address" placeholder="e.g. 10 Downing St, London" autocomplete="off" class="<?php echo esc_attr($inputCls); ?>" required>
                        <input type="hidden" name="pickup_lat" id="ob-pickup-lat">
                        <input type="hidden" name="pickup_lng" id="ob-pickup-lng">
                        <ul class="ob-suggestions hidden" id="ob-pickup-suggestions"></ul>
                    </div>
                    <div class="sm:col-span-2 ob-address-field">
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Destination</label>
                        <input type="text" id="ob-destination-address" name="destination" placeholder="e.g. Heathrow Terminal 5" autocomplete="off" class="<?php echo esc_attr($inputCls); ?>" required>
                        <input type="hidden" name="destination_lat" id="ob-destination-lat">
                        <input type="hidden" name="destination_lng" id="ob-destination-lng">
                        <ul class="ob-suggestions hidden" id="ob-destination-suggestions"></ul>
                    </div>

                    <!-- Date / Time next -->
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Pickup Date</label>
                        <input type="date" name="pickup_date" class="<?php echo esc_attr($inputCls); ?>" required>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Pickup Time</label>
                        <input type="time" name="pickup_time" class="<?php echo esc_attr($inputCls); ?>" required>
                    </div>

                    <!-- Other info last -->
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Journey Type</label>
                        <select name="journey_type" class="<?php echo esc_attr($inputCls); ?>" required>
                            <option value="oneway">One-way</option>
                            <option value="return">Return</option>
                            <option value="hourly">Hourly hire</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Number of Passengers</label>
                        <input type="number" name="passengers" min="1" max="8" value="1" class="<?php echo esc_attr($inputCls); ?>" required>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Suitcases</label>
                        <input type="number" name="suitcases" min="0" max="10" value="1" class="<?php echo esc_attr($inputCls); ?>">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Flight Number (optional)</label>
                        <input type="text" name="flight_number" placeholder="e.g. BA286" class="<?php echo esc_attr($inputCls); ?>">
                    </div>
                </div>

                <div class="ob-map-panel">
                    <div id="ob-map" class="ob-map"></div>
                    <div class="ob-map-summary hidden" id="ob-map-summary">
                        <div class="ob-map-summary-item">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                            <span class="ob-map-summary-label">Distance</span>
                            <span class="ob-map-summary-value" id="ob-distance-value">—</span>
                        </div>
                        <div class="ob-map-summary-item">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                            <span class="ob-map-summary-label">Est. Time</span>
                            <span class="ob-map-summary-value" id="ob-duration-value">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Details -->
            <div class="booking-step grid gap-5 md:grid-cols-2" data-step="2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter your name" class="<?php echo esc_attr($inputCls); ?> !text-black !bg-white placeholder:!text-muted-foreground" required>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Phone</label>
                    <input type="tel" name="phone" placeholder="Enter your phone" class="<?php echo esc_attr($inputCls); ?> !text-black !bg-white placeholder:!text-muted-foreground" required>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-muted-foreground">Email</label>
                    <input type="email" name="email" placeholder="Enter your email" class="<?php echo esc_attr($inputCls); ?> !text-black !bg-white placeholder:!text-muted-foreground" required>
                </div>
            </div>

            <!-- back and continue buttons -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-4">
                <button type="button" id="booking-back" disabled class="inline-flex items-center gap-2 rounded-full btn-ghost-gold px-6 py-3 text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m12 19-7-7 7-7" />
                        <path d="M19 12H5" />
                    </svg>
                    Back
                </button>
                <button type="submit" id="booking-next" class="inline-flex items-center gap-2 rounded-full btn-gold px-8 py-3.5 text-sm">
                    <span class="ob-btn-label">Continue</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ob-btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14" />
                        <path d="m12 5 7 7-7 7" />
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ob-btn-spinner hidden animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9" stroke-opacity="0.25"/>
                        <path d="M21 12a9 9 0 0 0-9-9" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('oracle_booking_form', 'oracle_booking_render_shortcode');
