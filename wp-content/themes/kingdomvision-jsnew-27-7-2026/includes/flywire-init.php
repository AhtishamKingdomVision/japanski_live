<?php
/**
 * Flywire Payment Gateway Integration Loader
 * 
 * Add this to your functions.php or require it from functions.php
 * 
 * require_once get_template_directory() . '/includes/flywire-init.php';
 */

// Load all Flywire integration files
require_once get_template_directory() . '/includes/functions-flywire.php';
require_once get_template_directory() . '/includes/admin-flywire-settings.php';
require_once get_template_directory() . '/includes/admin-flywire-transactions.php';
require_once get_template_directory() . '/includes/flywire-payment-processor.php';
require_once get_template_directory() . '/includes/flywire-webhook.php';

// ============================================================================
// SCRIPT AND STYLE ENQUEUING
// ============================================================================

/**
 * Enqueue Flywire scripts on booking confirmation page
 */
function jse_enqueue_flywire_scripts() {
    // Only enqueue on booking page
    if ( ! is_page_template( 'booking_confirmation.php' ) || ! is_page( 26812 ) ) {
        return;
    }

    // Enqueue payment styles
    wp_enqueue_style(
        'jse-flywire-styles',
        get_template_directory_uri() . '/css/flywire-styles.css',
        [],
        filemtime(get_template_directory() . '/css/flywire-styles.css')
    );

    // Enqueue Flywire SDK from CDN
    wp_enqueue_script(
        'flywire-sdk',
        'https://checkout.flywire.com/flywire-payment.js',
        [],
        null,
        false
    );

    // Enqueue our payment manager script
    wp_enqueue_script(
        'jse-flywire-payment',
        get_template_directory_uri() . '/js/flywire-payment.js',
        ['jquery', 'flywire-sdk'],
        filemtime(get_template_directory() . '/js/flywire-payment.js'),
        true
    );

    wp_localize_script(
        'jse-flywire-payment',
        'flywireData',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'booking_nonce' => wp_create_nonce('jse_flywire_booking_nonce'),
        )
    );
}
add_action('wp_enqueue_scripts', 'jse_enqueue_flywire_scripts');

/**
 * Enqueue admin scripts for Flywire settings page
 */
function jse_enqueue_flywire_admin_scripts($hook) {
    // Only on Flywire admin pages
    if (strpos($hook, 'jse_flywire') === false) {
        return;
    }

    wp_enqueue_style(
        'jse-flywire-admin',
        get_template_directory_uri() . '/css/flywire-admin.css',
        [],
        '1.0'
    );
}
add_action('admin_enqueue_scripts', 'jse_enqueue_flywire_admin_scripts');

// ============================================================================
// PAYMENT SUCCESS/FAILURE PAGES
// ============================================================================

/**
 * Create payment success page if it doesn't exist
 */
function jse_create_payment_pages() {
    $pages = [
        [
            'post_name' => 'payment-success',
            'post_title' => 'Payment Success',
            'post_content' => '[flywire_payment_success]'
        ],
        [
            'post_name' => 'payment-failure',
            'post_title' => 'Payment Failed',
            'post_content' => '[flywire_payment_failure]'
        ]
    ];

    foreach ($pages as $page_data) {
        $page = get_page_by_path($page_data['post_name']);
        
        if (!$page) {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_name' => $page_data['post_name'],
                'post_title' => $page_data['post_title'],
                'post_content' => $page_data['post_content']
            ]);
        }
    }
}
add_action('wp_loaded', 'jse_create_payment_pages');

/**
 * Shortcode for payment success page
 * 
 * Displays transaction and booking details after successful payment
 * URL parameter: reference = flywire_transaction_id (payment_id from Flywire)
 */
function jse_payment_success_shortcode() {
    // Flywire returns transaction reference via "reference".
    // BedBank direct booking redirects with "booking_ref".
    $flywire_transaction_id = isset($_GET['reference']) ? sanitize_text_field($_GET['reference']) : '';
    $booking_reference = isset($_GET['booking_ref']) ? sanitize_text_field($_GET['booking_ref']) : '';
    
    // Prefer Flywire transaction lookup; fallback to booking reference for Book Now flow.
    $transaction = !empty($flywire_transaction_id)
        ? jse_get_flywire_transaction_by_id($flywire_transaction_id)
        : null;
    
    if (!$transaction && !empty($booking_reference)) {
        $transaction = jse_get_flywire_transaction($booking_reference);
    }
    // pre($transaction, 1);

    ob_start();
    ?>
    <div class="payment-success-container" style="text-align: center; padding: 40px 20px;">
        <div style="max-width: 600px; margin: 0 auto;">
            <h1 style="color: #28a745;">✓ Booking Initiated</h1>
            <p style="font-size: 18px; margin: 20px 0;">Thank you for pre-authorising payment</p>

            <?php if ($transaction): ?>
                <?php /* ?>
                <div class="payment-details" style="padding: 20px; border-radius: 8px; text-align: left;">
                    <h3>Booking Details</h3>
                    <table style="width: 100%; margin-top: 15px;">
                        <tr>
                            <td style="font-weight: bold; padding: 8px 0;">Booking Reference:</td>
                            <td style="padding: 8px 0; color: #c72a2f; font-weight: bold;"><?php echo esc_html($transaction->booking_reference); ?></td>
                        </tr>
                        
                        <!-- Display all items in this booking -->
                        <?php if (!empty($transaction->items) && is_array($transaction->items)): ?>
                            <?php foreach ($transaction->items as $index => $item): ?>
                                <tr style="<?php echo $index > 0 ? 'border-top: 1px solid #ddd; padding-top: 10px;' : ''; ?>">
                                    <td style="font-weight: bold; padding: 8px 0;">
                                        <?php echo $index > 0 ? 'Property ' . ($index + 1) . ':' : 'Property:'; ?>
                                    </td>
                                    <td style="padding: 8px 0;">
                                        <strong><?php echo esc_html($item->accommodation_name); ?></strong>
                                        <?php if (!empty($item->room_name)): ?>
                                            <br/><small><?php echo esc_html($item->room_name); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; padding: 8px 0;">Check-in:</td>
                                    <td style="padding: 8px 0;"><?php echo esc_html($item->check_in_date); ?></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; padding: 8px 0;">Check-out:</td>
                                    <td style="padding: 8px 0;"><?php echo esc_html($item->check_out_date); ?></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; padding: 8px 0;">Nights:</td>
                                    <td style="padding: 8px 0;"><?php echo esc_html($item->number_of_nights); ?></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; padding: 8px 0;">Guests:</td>
                                    <td style="padding: 8px 0;">
                                        <?php 
                                        $guests = [];
                                        if ($item->adults > 0) $guests[] = $item->adults . ' adult' . ($item->adults > 1 ? 's' : '');
                                        if ($item->children > 0) $guests[] = $item->children . ' child' . ($item->children > 1 ? 'ren' : '');
                                        if ($item->infants > 0) $guests[] = $item->infants . ' infant' . ($item->infants > 1 ? 's' : '');
                                        echo implode(', ', $guests);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <tr style="border-top: 2px solid #ddd; margin-top: 10px;">
                            <td style="font-weight: bold; padding: 8px 0; margin-top: 10px;">Total Guests:</td>
                            <td style="padding: 8px 0;"><?php echo esc_html($transaction->total_guests); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 8px 0;">Amount Paid:</td>
                            <td style="padding: 8px 0; color: #28a745; font-weight: bold;">¥<?php echo number_format($transaction->payment_amount); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 8px 0;">Payment Status:</td>
                            <td style="padding: 8px 0;">
                                <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 3px;">
                                    <?php echo esc_html($transaction->payment_status); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php */ ?>

                <div style="background: #1c2936; color: #fff; padding: 20px; border-radius: 20px; margin-top: 30px;">
                    <p>
                        Your card will not be charged until accommodation is confirmed. An email has been sent to <strong><?php echo esc_html($transaction->customer_email); ?></strong>
                    </p>
                </div>
            <?php else: ?>
                <div style="background: #1c2936; color: #fff; padding: 20px; border-radius: 20px;">
                    <p>Payment information could not be found. Please check your email for confirmation details.</p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 30px;">
                <a href="<?php echo home_url('/'); ?>" class="btn">Return to Home</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('flywire_payment_success', 'jse_payment_success_shortcode');

/**
 * Shortcode for payment failure page
 */
function jse_payment_failure_shortcode() {
    ob_start();
    ?>
    <div class="payment-failure-container" style="text-align: center; padding: 40px 20px;">
        <div style="max-width: 600px; margin: 0 auto;">
            <h1 style="color: #dc3545;">✗ Payment Failed</h1>
            <p style="font-size: 18px; margin: 20px 0; color: #666;">Unfortunately, your payment could not be processed.</p>

            <div class="error-info" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <p>This could be due to:</p>
                <ul style="text-align: left;">
                    <li>Insufficient funds</li>
                    <li>Incorrect payment information</li>
                    <li>Network connectivity issue</li>
                    <li>Card declined by issuer</li>
                </ul>
            </div>

            <div style="margin-top: 30px;">
                <p>Please try again or contact our support team for assistance.</p>
                <div style="margin: 20px 0;">
                    <a href="<?php echo home_url('/booking/'); ?>" class="button" style="background: #c72a2f; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;">Try Again</a>
                    <a href="<?php echo home_url('/contact/'); ?>" class="button" style="background: #6c757d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Contact Support</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('flywire_payment_failure', 'jse_payment_failure_shortcode');

// ============================================================================
// HOOKS FOR PAYMENT EVENTS
// ============================================================================

/**
 * Example hook: After payment is completed
 * Use this to trigger custom actions (send emails, update booking status, etc.)
 */
add_action('jse_flywire_payment_completed', function($transaction, $payload) {
    jse_log_flywire('Custom action: Payment completed', [
        'booking_reference' => $transaction->booking_reference
    ]);
}, 10, 2);

/**
 * Example hook: After payment fails
 */
add_action('jse_flywire_payment_failed', function($transaction, $payload) {
    jse_log_flywire('Custom action: Payment failed', [
        'booking_reference' => $transaction->booking_reference
    ]);
}, 10, 2);

// ============================================================================
// DEBUGGING & HELP
// ============================================================================

/**
 * Add admin notice for Flywire configuration
 */
function jse_flywire_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = jse_get_flywire_settings();

    // Show notice if not configured
    if (!$settings['enabled']) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Flywire Payment Gateway:</strong> 
                Payment gateway is disabled. 
                <a href="<?php echo admin_url('admin.php?page=jse_flywire_settings'); ?>">Configure now</a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'jse_flywire_admin_notice');

// ============================================================================
// DATABASE TABLE CREATION (ON THEME ACTIVATION)
// ============================================================================

/**
 * Create database table on theme activation
 */
function jse_flywire_activate() {
    jse_create_flywire_transactions_table();
    jse_create_payment_pages();
}
add_action('after_switch_theme', 'jse_flywire_activate');
