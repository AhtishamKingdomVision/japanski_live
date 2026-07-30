<?php
/**
 * Flywire Payment Processor
 * 
 * Handles payment processing and Flywire configuration
 */

// ============================================================================
// PAYMENT INITIATION
// ============================================================================

/**
 * Prepare payment configuration for Flywire
 * 
 * This function builds the complete Flywire payment configuration
 * based on the booking cart data and customer information
 */
function jse_prepare_flywire_payment_config($cart_data) {
    // Get Flywire settings
    $settings = jse_get_flywire_settings();
    
    if (!$settings['enabled']) {
        return [
            'success' => false,
            'message' => 'Flywire payment is not enabled'
        ];
    }

    // Validate cart data
    if (empty($cart_data['items']) || !is_array($cart_data['items'])) {
        return [
            'success' => false,
            'message' => 'Invalid cart data'
        ];
    }

    try {
        // Generate booking reference if not provided
        $booking_reference = $cart_data['booking_reference'] ?? jse_generate_booking_reference();
        // pre($booking_reference, 0);
        // pre($cart_data, 1);
        
        // Format reference for Flywire (e.g., "1844" becomes "JSE1844_1")
        $flywire_reference = jse_format_flywire_reference($booking_reference);
        
        // ===== MULTI-PROPERTY SUPPORT: Process ALL items =====
        
        // Calculate totals across ALL cart items
        $total_payment_amount = 0;
        $total_guests = 0;
        $total_nights = 0;
        $has_deposit = false;
        $all_items_data = [];
        $first_item = null;
        
        // Process each item in cart
        foreach ($cart_data['items'] as $index => $item) {
            // Store first item for fallback reference
            if ($first_item === null) {
                $first_item = $item;
            }
            
            // Check if this item is a deposit
            $is_item_deposit = !empty($item['payment']['isDeposit']);
            if ($is_item_deposit) {
                $has_deposit = true;
            }
            
            // Get payment amount for this item
            $item_amount = $is_item_deposit 
                ? ($item['payment']['depositAmount'] ?? 0)
                : ($item['payment']['totalAmount'] ?? 0);
            
            $total_payment_amount += Number($item_amount);
            
            // Calculate guests for this item
            $adults = Number($item['guests']['adults'] ?? 0);
            $children = Number($item['guests']['children'] ?? 0);
            $infants = Number($item['guests']['infants'] ?? 0);
            $item_guests = $adults + $children + $infants;
            $total_guests = max($total_guests, $item_guests);
            
            // Calculate nights for this item
            $item_nights = Number($item['duration'] ?? 0);
            $total_nights = max($total_nights, $item_nights);
            
            // Store item summary data
            $all_items_data[] = [
                'property_name' => $item['property_name'] ?? 'Property ' . ($index + 1),
                'room_name' => $item['room_name'] ?? 'Room ' . ($index + 1),
                'check_in' => $item['dates']['check_in'] ?? '',
                'check_out' => $item['dates']['check_out'] ?? '',
                'nights' => $item_nights,
                'guests' => $item_guests,
                'amount' => $item_amount
            ];
        }
        
        // Determine payment type based on all items
        $payment_type = $has_deposit ? 'Deposit' : 'Full Payment';
        
        // Get first item's accommodation for permission type
        $accommodation_id = $first_item['hotel_type_id'] ?? 0;
        $permission_type = jse_get_accommodation_permission($accommodation_id);
        $payment_action = jse_get_payment_action($accommodation_id);

        // Get customer information (should be passed separately)
        $customer = $cart_data['customer'] ?? [];

        // Build summary string for all accommodations and rooms
        $accommodations_summary = implode(', ', array_map(
            function($item) {
                return $item['property_name'] . ' - ' . $item['room_name'];
            },
            $all_items_data
        ));
        
        // Build detailed items string for recipient fields
        $items_details = array_map(
            function($index, $item) {
                return sprintf(
                    "(%d) %s - %s [%s to %s, %d nights, %d guests, %s]",
                    $index + 1,
                    $item['property_name'],
                    $item['room_name'],
                    $item['check_in'],
                    $item['check_out'],
                    $item['nights'],
                    $item['guests'],
                    number_format($item['amount'], 0)
                );
            },
            array_keys($all_items_data),
            $all_items_data
        );

        // Build Flywire configuration
        $config = [
            'env' => jse_get_flywire_env(),
            'recipientCode' => $settings['recipient_code'],
            'amount' => jse_format_payment_amount($total_payment_amount, $settings['currency']),
            // 'amount' => "210.00",
            // 'amount' => 210,
            'currency' => $settings['currency'],

            // Mark this payment as a pre-authorization 
            'paymentAuthorization' => [
                'type' => 'pre_auth'
            ],
            
            // Customer Information
            'email' => $customer['email'] ?? '',
            'firstName' => $customer['first_name'] ?? '',
            'lastName' => $customer['last_name'] ?? '',
            'phone' => $customer['phone'] ?? '',
            
            // Billing Address
            'address' => $customer['address'] ?? '',
            'city' => $customer['city'] ?? '',
            // 'state' => $customer['state'] ?? '',
            'state' => '',
            'country' => ( jse_is_flywire_live() ? ($customer['country'] ?? '') : 'JP' ),
            'zip' => $customer['zip'] ?? '',
            
            // Redirect URL
            'returnUrl' => home_url('/payment-success/'),
            
            // Request info collection
            'requestPayerInfo' => true,
            'requestRecipientInfo' => false,
            // 'readonlyFields' => ["country"],
            
            // Payment options
            // 'paymentOptionsConfig' => [
            //     'filters' => [
            //         // 'type' => ["bank_transfer", "credit_card", "online"],
            //         'type' => ["credit_card"],
            //         'currency' => ['nonFX', 'fx', 'local', 'foreign']
            //     ]
            // ],
            
            // Custom fields for tracking (multi-property aware)
            'recipientFields' => [
                'booking_reference' => $flywire_reference,
                'item_count' => count($cart_data['items']),
                'accommodations' => $accommodations_summary,
                'payment_type' => $payment_type,
                'permission_type' => $permission_type,
                'total_guests' => $total_guests,
                'total_nights' => $total_nights,
                'check_in' => $first_item['dates']['check_in'] ?? '',
                'check_out' => $first_item['dates']['check_out'] ?? '',
                'items_detail' => implode(' | ', $items_details)
            ],
            
            // Callback tracking - Use formatted Flywire reference
            'callbackId' => $flywire_reference,
            'callbackUrl' => home_url('/wp-json/flywire/callback/'),
            'callbackVersion' => '2',
            
            // Metadata for transaction (complete cart data)
            'metadata' => [
                'booking_reference' => $booking_reference,
                'flywire_reference' => $flywire_reference,
                'accommodation_id' => $accommodation_id,
                'permission_type' => $permission_type,
                'payment_action' => $payment_action,
                'payment_type' => $payment_type,
                'is_deposit' => $has_deposit,
                'total_payment_amount' => $total_payment_amount,
                'total_guests' => $total_guests,
                'total_nights' => $total_nights,
                'item_count' => count($cart_data['items']),
                'cart_items' => $cart_data['items'],
                'items_summary' => $all_items_data,
                'customer' => $customer
            ]
        ];

        return [
            'success' => true,
            'config' => $config,
            'booking_reference' => $booking_reference,
            'payment_type' => $payment_type
        ];

    } catch (Exception $e) {
        jse_log_flywire('Error preparing payment config: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Error preparing payment: ' . $e->getMessage()
        ];
    }
}

// ============================================================================
// FLYWIRE RESPONSE HANDLERS
// ============================================================================

/**
 * Handle successful payment completion
 * 
 * This is called when the Flywire payment is completed and 
 * the customer is redirected back to the site
 */
function jse_handle_flywire_payment_success($payload) {
    try {
        if (empty($payload['callbackId'])) {
            throw new Exception('Missing callbackId in payment response');
        }

        // Extract original booking reference from Flywire formatted reference (e.g., "JSE1844_1" -> "1844")
        $flywire_reference = $payload['callbackId'];
        $booking_reference = jse_extract_booking_reference_from_flywire($flywire_reference);
        
        $transaction = jse_get_flywire_transaction($booking_reference);

        if (!$transaction) {
            throw new Exception('Transaction not found: ' . $booking_reference);
        }

        // Extract payment information from Flywire response
        $update_data = [
            'flywire_transaction_id' => $payload['transactionId'] ?? '',
            'flywire_payment_method' => $payload['paymentMethod'] ?? '',
            'flywire_status' => $payload['status'] ?? 'completed',
            'flywire_response' => $payload,
            'payment_status' => 'Completed'
        ];

        // Update transaction
        jse_save_flywire_transaction(array_merge((array)$transaction, $update_data));

        // Log success
        jse_log_flywire('Payment completed', [
            'booking_reference' => $booking_reference,
            'flywire_reference' => $flywire_reference,
            'flywire_transaction_id' => $payload['transactionId'] ?? ''
        ]);

        /**
         * Perform post-payment actions:
         * - Send confirmation email
         * - Create booking in system
         * - Update order status
         * - Trigger any custom hooks
         */
        do_action('jse_flywire_payment_completed', $transaction, $payload);

        return [
            'success' => true,
            'message' => 'Payment processed successfully',
            'booking_reference' => $booking_reference
        ];

    } catch (Exception $e) {
        jse_log_flywire('Error handling payment success: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Error processing payment: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle payment failure
 */
function jse_handle_flywire_payment_failure($payload) {
    try {
        if (empty($payload['callbackId'])) {
            throw new Exception('Missing callbackId in failure response');
        }

        // Extract original booking reference from Flywire formatted reference (e.g., "JSE1844_1" -> "1844")
        $flywire_reference = $payload['callbackId'];
        $booking_reference = jse_extract_booking_reference_from_flywire($flywire_reference);
        
        $transaction = jse_get_flywire_transaction($booking_reference);

        if (!$transaction) {
            throw new Exception('Transaction not found: ' . $booking_reference);
        }

        // Update transaction with failure information
        $update_data = [
            'flywire_transaction_id' => $payload['transactionId'] ?? '',
            'flywire_status' => $payload['status'] ?? 'failed',
            'flywire_response' => $payload,
            'payment_status' => 'Failed'
        ];

        jse_save_flywire_transaction(array_merge((array)$transaction, $update_data));

        // Log failure
        jse_log_flywire('Payment failed', [
            'booking_reference' => $booking_reference,
            'flywire_reference' => $flywire_reference,
            'error' => $payload['error'] ?? 'Unknown error'
        ]);

        /**
         * Perform post-failure actions:
         * - Send failure notification
         * - Update order status
         * - Trigger any custom hooks
         */
        do_action('jse_flywire_payment_failed', $transaction, $payload);

        return [
            'success' => false,
            'message' => 'Payment processing failed',
            'booking_reference' => $booking_reference,
            'error' => $payload['error'] ?? 'Payment declined'
        ];

    } catch (Exception $e) {
        jse_log_flywire('Error handling payment failure: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Error processing failure: ' . $e->getMessage()
        ];
    }
}

// ============================================================================
// AJAX ENDPOINTS FOR FRONTEND
// ============================================================================

/**
 * AJAX endpoint to get Flywire payment configuration
 */
function jse_ajax_get_flywire_config() {
    check_ajax_referer('jse_flywire_booking_nonce', 'nonce');

    // Get cart from localStorage (passed from frontend)
    $cart_data = isset($_POST['cart']) ? $_POST['cart'] : [];
    $customer_data = isset($_POST['customer']) ? $_POST['customer'] : [];
    $booking_reference = isset($_POST['booking_reference']) ? $_POST['booking_reference'] : null;
    // pre($cart_data);
    // pre($customer_data);

    if (empty($cart_data)) {
        wp_send_json_error([
            'message' => 'No cart data provided'
        ]);
    }

    $cart_data = json_decode(stripslashes($cart_data), true);
    $customer_data = json_decode(stripslashes($customer_data), true);
    // pre($cart_data);
    // pre($customer_data,1);

    // Prepare configuration
    $result = jse_prepare_flywire_payment_config(array_merge(
        $cart_data,
        ['customer' => $customer_data],
        ['booking_reference' => $booking_reference ?? null]
    ));

    if (!$result['success']) {
        wp_send_json_error([
            'message' => $result['message']
        ]);
    }

    wp_send_json_success([
        'config' => $result['config'],
        'booking_reference' => $result['booking_reference'],
        'payment_type' => $result['payment_type']
    ]);
}
add_action('wp_ajax_jse_get_flywire_config', 'jse_ajax_get_flywire_config');
add_action('wp_ajax_nopriv_jse_get_flywire_config', 'jse_ajax_get_flywire_config');

/**
 * AJAX endpoint to save transaction
 */
function jse_ajax_save_flywire_transaction() {
    check_ajax_referer('jse_flywire_booking_nonce', 'nonce');

    // pre($_POST, 0);
    $transaction_data = isset($_POST['transaction']) ? json_decode(stripslashes($_POST['transaction']), true) : [];
    // var_dump($transaction_data);
    // pre($transaction_data, 1);
    // die;

    if (empty($transaction_data['booking_reference'])) {
        wp_send_json_error([
            'message' => 'Missing booking reference'
        ]);
    }

    $transaction_id = jse_save_flywire_transaction($transaction_data);

    if ($transaction_id) {
        wp_send_json_success([
            'transaction_id' => $transaction_id,
            'message' => 'Transaction saved successfully'
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Error saving transaction'
        ]);
    }
}
add_action('wp_ajax_jse_save_flywire_transaction', 'jse_ajax_save_flywire_transaction');
add_action('wp_ajax_nopriv_jse_save_flywire_transaction', 'jse_ajax_save_flywire_transaction');

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Convert value to number (intval or floatval)
 */
function Number($value) {
    if (is_numeric($value)) {
        return floatval($value);
    }
    return 0;
}
