<?php

/**

 * Flywire Webhook Callback Handler

 * 

 * Receives and processes webhook callbacks from Flywire

 * Endpoint: /wp-json/flywire/callback/

 */



// ============================================================================

// REST API ENDPOINT

// ============================================================================



function jse_register_flywire_webhook() {

    register_rest_route('flywire', '/callback/', [

        'methods' => ['GET', 'POST'],

        'callback' => 'jse_handle_flywire_webhook',

        'permission_callback' => '__return_true'

    ]);

}

add_action('rest_api_init', 'jse_register_flywire_webhook');



// ============================================================================

// WEBHOOK HANDLER

// ============================================================================



function jse_handle_flywire_webhook(WP_REST_Request $request) {

    try {

        $body = $request->get_json_params();

        // body example from Flywire documentation (adjust based on actual payload structure)

        // {

            // "event_type": "initiated", // or "guaranteed", "processed", "cancelled", "failed", etc.

            // "event_date": "2026-05-19T16:53:13Z",

            // "event_resource": "payments",

            // "data": {

                // "payment_id": "JSE338376742", // transactionId

                // "amount_from": "108248",

                // "currency_from": "GBP",

                // "amount_to": "226665",

                // "currency_to": "JPY",

                // "status": "guaranteed",

                // "expiration_date": "2026-05-20T18:40:32Z",

                // "external_reference": "BK-1779216007-1704", // booking_reference

                // "country": "GB",

                // "payment_method": {

                    // "type": "card"

                // },

                // "fields": {

                    // "booking_reference": "BK-1779216007-1704", // booking_reference

                    // "card_issued_redirect": "true"

                // }

            // }

        // }



        // Log incoming webhook

        jse_log_flywire('Webhook Header: ', $request->get_headers());

        jse_log_flywire('Webhook Body: ', $body);



        if( $body && is_object($body) ) {

            $body = json_decode($body, true);

        }

        // pre($body, 1);



        // Validate webhook signature (optional but recommended)

        // if (!jse_verify_flywire_webhook_signature($body, $request)) {

        //     jse_log_flywire('Webhook signature verification failed');

        //     return new WP_REST_Response([

        //         'success' => false,

        //         'message' => 'Signature verification failed'

        //     ], 401);

        // }



        // Extract webhook data

        $flywire_transaction_id = @$body['data']['payment_id'] ?? null;

        $flywire_reference = @$body['data']['external_reference'] ?? @$body['data']['fields']['booking_reference'] ?? null;

        $status = $body['event_type'] ?? null;



        if (!$flywire_transaction_id || !$status) {

            return new WP_REST_Response([

                'success' => false,

                'message' => 'Missing required fields: payment_id or event_type'

            ], 400);

        }



        // Extract original booking reference from Flywire formatted reference (e.g., "JSE1844_1" -> "1844")

        $booking_reference = jse_extract_booking_reference_from_flywire($flywire_reference ?? '');



        // Find transaction - try multiple lookup strategies

        $transaction = null;



        // 1. First try lookup by flywire_transaction_id (for webhook retries)

        $transaction = jse_get_flywire_transaction_by_id($flywire_transaction_id);

        // pre([

        //     'flywire_transaction_id' => $flywire_transaction_id,

        //     'flywire_reference' => $flywire_reference,

        //     'booking_reference' => $booking_reference,

        //     'event_type' => $status

        // ], 0);

        // pre($transaction, 1);

        

        // 2. If not found and we have booking_reference, lookup by that (initial webhook, ID not yet saved)

        if (!$transaction && $booking_reference) {

            $transaction = jse_get_flywire_transaction($booking_reference);

            

            if ($transaction && !$transaction->flywire_transaction_id) {

                jse_log_flywire('Found transaction by booking_reference, adding flywire_transaction_id', [

                    'booking_reference' => $booking_reference,

                    'flywire_reference' => $flywire_reference,

                    'flywire_transaction_id' => $flywire_transaction_id

                ]);

                // Update transaction with flywire_transaction_id

                global $wpdb;

                $wpdb->update(

                    $wpdb->prefix . 'flywire_transactions',

                    ['flywire_transaction_id' => $flywire_transaction_id],

                    ['id' => $transaction->id]

                );

                // Refresh transaction object

                $transaction = jse_get_flywire_transaction($booking_reference);

            }

        }



        if (!$transaction) {

            jse_log_flywire('Transaction not found', [

                'flywire_transaction_id' => $flywire_transaction_id,

                'flywire_reference' => $flywire_reference,

                'booking_reference' => $booking_reference,

                'event_type' => $status

            ]);

            return new WP_REST_Response([

                'success' => false,

                'message' => 'Transaction not found'

            ], 404);

        }



        // Log webhook reception

        jse_log_flywire('Webhook received and transaction found', [

            'flywire_transaction_id' => $flywire_transaction_id,

            'flywire_reference' => $flywire_reference,

            'booking_reference' => $booking_reference,

            'event_type' => $status,

            'transaction' => $transaction

        ]);



        // Process based on status

        $result = null;

        // Handle different statuses based on Flywire's documentation

        /*

          - Initiated

            -- initiated (The payment status initiated is the status for all new payments in Flywire. No funds have been received by Flywire at the initiated stage.)

            -- authorized (The payment has been authorized and is now in the holding period. How long funds can be reserved depends on the card issuer and other circumstances. At the moment, Flywire can reserve the funds for 7 days. The period starts from the day the Pre-Authorization Payment is created.)

            -- adjusted (The amount that is being held on the card has been adjusted.)

          - Processed

            -- processed (The payment status goes from initiated to processed when Flywire has the confirmation that the funds have been received or captured via one of these ways:

                - Bank transfer: funds have been received in a Flywire bank account.

                - Card payments: funds have been captured in payer’s card.

                - Direct debit: instructions to capture the funds are in progress and correct.)

          - Guaranteed

            -- guaranteed (The status changes to guaranteed when funds are received by Flywire and all the necessary validations for that payment method and corridor are successful (since Flywire performs a security check on every payment once the funds have been received).

                At this point, you are guaranteed that Flywire will send you the funds.)

          - Delivered

            -- delivered

            -- reversed

          - Failed

            -- failed

          - Cancelled

            -- cancelled

          - Reversed

            -- reversed

        */

        switch (strtolower($status)) {

            case 'guaranteed':

            case 'authorized':

                $result = jse_handle_flywire_webhook_completed($transaction, $body);

                break;



            case 'initiated':

            case 'processed':

                $result = jse_handle_flywire_webhook_pending($transaction, $body);

                break;



            case 'cancelled':

                $result = jse_handle_flywire_webhook_cancelled($transaction, $body);

                break;



            case 'failed':

            case 'reversed':

                $result = jse_handle_flywire_webhook_failed($transaction, $body);

                break;



            default:

                jse_log_flywire('Unknown webhook status: ' . $status);

                $result = [

                    'success' => false,

                    'message' => 'Unknown status'

                ];

        }



        if ($result['success']) {

            return new WP_REST_Response([

                'success' => true,

                'message' => 'Webhook processed successfully'

            ], 200);

        } else {

            return new WP_REST_Response([

                'success' => false,

                'message' => $result['message']

            ], 400);

        }



    } catch (Exception $e) {

        jse_log_flywire('Webhook error: ' . $e->getMessage());

        

        return new WP_REST_Response([

            'success' => false,

            'message' => 'Internal server error'

        ], 500);

    }

}



// ============================================================================

// QUOTATION API HELPERS (Server-side sync)

// ============================================================================



/**

 * Sync authorized Flywire payment to quotation system

 * 

 * Calls the quotation payment API to record payment authorization

 * Endpoint: POST https://trip.japanskiexperience.com/api/quotations/wp-payment/{quotation_id}

 */

function jse_sync_authorized_payment_to_quotation($quotation_id, $booking_reference, $flywire_response) {

    if (empty($quotation_id)) {

        jse_log_flywire('Cannot sync payment: missing quotation_id');

        return false;

    }



    try {

        $api_url = sprintf(

            'https://stay.japanskiexperience.com/api/quotations/wp-payment/%s',

            urlencode($quotation_id)

        );



        jse_log_flywire('Trip Payment URL: ', $api_url);

        jse_log_flywire('Trip Payment payload: ', [

            'headers' => [

                'Content-Type' => 'application/json',

                'Accept' => 'application/json'

            ],

            'body' => wp_json_encode([

                'quotation_id' => (int) $quotation_id,

                'booking_reference' => (string) $booking_reference,

                'paymentResponse' => $flywire_response ?: []

            ]),

            'timeout' => 15,

            'sslverify' => true

        ]);



        $response = wp_remote_post($api_url, [

            'headers' => [

                'Content-Type' => 'application/json',

                'Accept' => 'application/json'

            ],

            'body' => wp_json_encode([

                'quotation_id' => (int) $quotation_id,

                'booking_reference' => (string) $booking_reference,

                'paymentResponse' => $flywire_response ?: []

            ]),

            'timeout' => 15,

            'sslverify' => true

        ]);



        if (is_wp_error($response)) {

            jse_log_flywire('Failed to sync authorized payment to quotation', [

                'quotation_id' => $quotation_id,

                'booking_reference' => $booking_reference,

                'error' => $response->get_error_message()

            ]);

            return false;

        }



        $status_code = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);



        if ($status_code >= 200 && $status_code < 300) {

            jse_log_flywire('Successfully synced authorized payment to quotation', [

                'quotation_id' => $quotation_id,

                'booking_reference' => $booking_reference,

                'status_code' => $status_code

            ]);

            return true;

        }



        jse_log_flywire('Quotation payment sync returned error status', [

            'quotation_id' => $quotation_id,

            'booking_reference' => $booking_reference,

            'status_code' => $status_code,

            'response' => $body

        ]);

        return false;



    } catch (Exception $e) {

        jse_log_flywire('Exception syncing authorized payment to quotation', [

            'quotation_id' => $quotation_id,

            'booking_reference' => $booking_reference,

            'error' => $e->getMessage()

        ]);

        return false;

    }

}



/**

 * Delete quotation from quotation system

 * 

 * Calls the quotation delete API to cancel/delete a quotation

 * Used when payment fails, is cancelled, or is reversed

 * Endpoint: DELETE https://trip.japanskiexperience.com/api/quotations/wp-delete/{quotation_id}

 */

function jse_delete_quotation_by_id($quotation_id) {

    if (empty($quotation_id)) {

        jse_log_flywire('Cannot delete quotation: missing quotation_id');

        return false;

    }



    try {

        $api_url = sprintf(

            'https://stay.japanskiexperience.com/api/quotations/wp-delete/%s',

            urlencode($quotation_id)

        );



        $response = wp_remote_request($api_url, [

            'method' => 'DELETE',

            'headers' => [

                'Accept' => 'application/json'

            ],

            'timeout' => 15,

            'sslverify' => true

        ]);



        if (is_wp_error($response)) {

            jse_log_flywire('Failed to delete quotation', [

                'quotation_id' => $quotation_id,

                'error' => $response->get_error_message()

            ]);

            return false;

        }



        $status_code = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);



        if ($status_code >= 200 && $status_code < 300) {

            jse_log_flywire('Successfully deleted quotation', [

                'quotation_id' => $quotation_id,

                'status_code' => $status_code

            ]);

            return true;

        }



        jse_log_flywire('Quotation delete returned error status', [

            'quotation_id' => $quotation_id,

            'status_code' => $status_code,

            'response' => $body

        ]);

        return false;



    } catch (Exception $e) {

        jse_log_flywire('Exception deleting quotation', [

            'quotation_id' => $quotation_id,

            'error' => $e->getMessage()

        ]);

        return false;

    }

}



// ============================================================================

// WEBHOOK STATUS HANDLERS

// ============================================================================



/**

 * Handle guaranteed/approved payment webhook

 * 

 * Validates state transitions and prevents duplicate updates

 * Only allows transition to guaranteed from pending/processed states

 */

function jse_handle_flywire_webhook_completed($transaction, $payload) {

    try {

        $current_status = strtolower($transaction->flywire_status ?? '');

        $flywire_transaction_id = $payload['data']['payment_id'] ?? '';



        // Prevent downgrade: if already in final state, skip

        if ($current_status === 'guaranteed') {

            jse_log_flywire('Payment already guaranteed, skipping duplicate webhook', [

                'booking_reference' => $transaction->booking_reference,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            cf_log( $transaction, 'hz-webhook-transaction' );

            $data['data'] = $transaction['data'] ?? [];
            $event_type = $transaction['event_type'] ?? '';
            $event_date = $transaction['event_date'] ?? '';
            $event_resource = $transaction['event_resource'] ?? '';

            $data['data']['event_type'] = $event_type;
            $data['data']['event_date'] = $event_date;
            $data['data']['event_resource'] = $event_resource;

            // Log the actual array payload (Changed $data to $tm_data)
            jse_log_flywire(  'hz-webhook-body', $data );

            // Configure the remote post to properly send pure JSON
            $resp = wp_remote_post( KV_BOOKING_SYSTEM_BASE . '/api/payment-notification', [
                'headers' => [
                    'Content-Type'    => 'application/json',
                    'User-Agent'      => 'PostmanRuntime/7.32.3', // Mimic Postman
                    'Accept'          => '*/*',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection'      => 'keep-alive',
                ],
                'body'        => wp_json_encode( $data ),
                'timeout'     => 45,
                'data_format' => 'body',
            ]);

            jse_log_flywire( 'hz-webhook-response',$resp);

            return [

                'success' => true,

                'message' => 'Payment already guaranteed (duplicate webhook)'

            ];

        }



        // Prevent overwriting other final states

        if ($current_status === 'failed' || $current_status === 'cancelled') {

            jse_log_flywire('Payment in final state, cannot complete', [

                'booking_reference' => $transaction->booking_reference,

                'current_status' => $current_status,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            return [

                'success' => true,

                'message' => 'Payment in final state (failed/cancelled), cannot complete'

            ];

        }



        $payment_method = '';

        if (!empty($payload['data']['payment_method']['type'])) {

            $payment_method = $payload['data']['payment_method']['type'];

        }



        // Map Flywire status to payment_status

        // Authorized and Guaranteed -> complete

        $flywire_status = strtolower($payload['data']['status'] ?? 'guaranteed');

        $payment_status = in_array($flywire_status, ['guaranteed', 'authorized']) ? 'complete' : 'pending';



        $update_data = [

            'booking_reference' => $transaction->booking_reference,

            'payment_id' => $transaction->payment_id,

            'customer_id' => $transaction->customer_id,

            'payment_status' => $payment_status,

            'flywire_transaction_id' => $flywire_transaction_id,

            'flywire_payment_method' => $payment_method,

            'flywire_status' => $flywire_status,

            'flywire_response' => $payload

        ];



        $transaction_id = jse_save_flywire_transaction($update_data);

        

        jse_log_flywire('Payment completion webhook processed', [

            'booking_reference' => $transaction->booking_reference,

            'flywire_transaction_id' => $flywire_transaction_id,

            'transaction_id' => $transaction_id

        ]);



        // Send email notification

        jse_send_payment_confirmation_email($transaction, $payload);



        // Sync authorized payment to quotation system

        if (!empty($transaction->quotation_id)) {

            jse_sync_authorized_payment_to_quotation(

                $transaction->quotation_id,

                $transaction->booking_reference,

                $payload

            );

        }



        // Trigger custom action hook

        do_action('jse_flywire_payment_completed_webhook', $transaction, $payload);



        return [

            'success' => true,

            'message' => 'Payment guaranteed'

        ];



    } catch (Exception $e) {

        jse_log_flywire('Error handling guaranteed webhook: ' . $e->getMessage());

        

        return [

            'success' => false,

            'message' => $e->getMessage()

        ];

    }

}



/**

 * Handle failed/declined payment webhook

 * 

 * Validates state transitions and prevents duplicate updates

 * Cannot overwrite guaranteed or already-failed states

 */

function jse_handle_flywire_webhook_failed($transaction, $payload) {

    try {

        $current_status = strtolower($transaction->flywire_status ?? '');

        $flywire_transaction_id = $payload['data']['payment_id'] ?? '';



        // Prevent duplicate updates

        if ($current_status === 'failed') {

            jse_log_flywire('Payment already marked as failed, skipping duplicate webhook', [

                'booking_reference' => $transaction->booking_reference,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            return [

                'success' => true,

                'message' => 'Payment already failed (duplicate webhook)'

            ];

        }



        // Prevent overwriting guaranteed payments

        if ($current_status === 'guaranteed') {

            jse_log_flywire('Payment already guaranteed, cannot mark as failed', [

                'booking_reference' => $transaction->booking_reference,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            return [

                'success' => true,

                'message' => 'Payment already guaranteed, cannot fail'

            ];

        }



        // Prevent overwriting cancelled payments

        if ($current_status === 'cancelled') {

            jse_log_flywire('Payment already cancelled, cannot mark as failed', [

                'booking_reference' => $transaction->booking_reference,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            return [

                'success' => true,

                'message' => 'Payment already cancelled, cannot fail'

            ];

        }



        $payment_method = '';

        if (!empty($payload['data']['payment_method']['type'])) {

            $payment_method = $payload['data']['payment_method']['type'];

        }



        // Map Flywire status to payment_status

        // Failed, Reversed, Cancelled -> void

        $flywire_status = strtolower($payload['data']['status'] ?? 'failed');

        $payment_status = 'void';



        $update_data = [

            'booking_reference' => $transaction->booking_reference,

            'payment_id' => $transaction->payment_id,

            'customer_id' => $transaction->customer_id,

            'payment_status' => $payment_status,

            'flywire_transaction_id' => $flywire_transaction_id,

            'flywire_payment_method' => $payment_method,

            'flywire_status' => $flywire_status,

            'flywire_response' => $payload

        ];



        $transaction_id = jse_save_flywire_transaction($update_data);

        

        jse_log_flywire('Payment failure webhook processed', [

            'booking_reference' => $transaction->booking_reference,

            'flywire_transaction_id' => $flywire_transaction_id,

            'error' => $payload['data']['error_message'] ?? 'Payment declined',

            'transaction_id' => $transaction_id

        ]);



        // Delete quotation for failed payment

        if (!empty($transaction->quotation_id)) {

            jse_delete_quotation_by_id($transaction->quotation_id);

        }



        // Send failure notification email

        jse_send_payment_failure_email($transaction, $payload);



        // Trigger custom action hook

        do_action('jse_flywire_payment_failed_webhook', $transaction, $payload);



        return [

            'success' => true,

            'message' => 'Payment failure processed'

        ];



    } catch (Exception $e) {

        jse_log_flywire('Error handling failed webhook: ' . $e->getMessage());

        

        return [

            'success' => false,

            'message' => $e->getMessage()

        ];

    }

}



/**

 * Handle pending payment webhook

 * 

 * Handles initial payment statuses: initiated, authorized, processed

 * Validates state transitions and prevents downgrades

 * Only allows: NULL -> pending -> processed; Cannot downgrade from processed

 */

function jse_handle_flywire_webhook_pending($transaction, $payload) {

    try {

        $current_status = strtolower($transaction->flywire_status ?? '');

        $flywire_transaction_id = $payload['data']['payment_id'] ?? '';

        $event_type = strtolower($payload['event_type'] ?? 'initiated');

        $status_type = strtolower($payload['data']['status'] ?? $event_type);

        

        // Map Flywire event types to our status

        $pending_status = 'pending'; // default

        if ($event_type === 'initiated' || $event_type === 'authorized') {

            $pending_status = 'pending';

        } elseif ($event_type === 'processed') {

            $pending_status = 'processed';

        }



        // Prevent state changes if already in final state

        if ($current_status === 'guaranteed' || $current_status === 'failed' || $current_status === 'cancelled') {

            jse_log_flywire('Payment already in final state, ignoring pending webhook', [

                'booking_reference' => $transaction->booking_reference,

                'current_status' => $current_status,

                'webhook_status' => $status_type,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            return [

                'success' => true,

                'message' => 'Payment in final state, ignoring status change'

            ];

        }



        // Prevent downgrade from processed to pending

        if ($current_status === 'processed' && $event_type === 'initiated') {

            jse_log_flywire('Cannot downgrade from processed to pending', [

                'booking_reference' => $transaction->booking_reference,

                'flywire_transaction_id' => $flywire_transaction_id,

                'event_type' => $event_type

            ]);

            return [

                'success' => true,

                'message' => 'Payment already processed, ignoring initiated event'

            ];

        }



        // Prevent duplicate pending status updates

        // if ($current_status === $pending_status && !empty($transaction->flywire_transaction_id)) {

        //     jse_log_flywire('Payment already in pending status, skipping duplicate webhook', [

        //         'booking_reference' => $transaction->booking_reference,

        //         'status' => $status_type,

        //         'flywire_transaction_id' => $flywire_transaction_id

        //     ]);

        //     return [

        //         'success' => true,

        //         'message' => 'Payment status unchanged (duplicate webhook)'

        //     ];

        // }



        $payment_method = '';

        if (!empty($payload['data']['payment_method']['type'])) {

            $payment_method = $payload['data']['payment_method']['type'];

        }



        // Map Flywire status to payment_status

        // Initiated, Processed, Authorized -> pending

        $flywire_status = strtolower($payload['data']['status'] ?? $event_type);

        $payment_status = 'pending';



        $update_data = [

            'booking_reference' => $transaction->booking_reference,

            'payment_id' => $transaction->payment_id,

            'customer_id' => $transaction->customer_id,

            'payment_status' => $payment_status,

            'flywire_transaction_id' => $flywire_transaction_id,

            'flywire_payment_method' => $payment_method,

            'flywire_status' => $flywire_status,

            'flywire_response' => $payload

        ];



        $transaction_id = jse_save_flywire_transaction($update_data);

        

        jse_log_flywire('Payment pending/processing webhook processed', [

            'booking_reference' => $transaction->booking_reference,

            'flywire_transaction_id' => $flywire_transaction_id,

            'event_type' => $event_type,

            'status' => $status_type,

            'transaction_id' => $transaction_id

        ]);



        // Trigger custom action hook

        do_action('jse_flywire_payment_pending_webhook', $transaction, $payload);



        return [

            'success' => true,

            'message' => 'Payment pending'

        ];



    } catch (Exception $e) {

        jse_log_flywire('Error handling pending webhook: ' . $e->getMessage());

        

        return [

            'success' => false,

            'message' => $e->getMessage()

        ];

    }

}



/**

 * Handle cancelled payment webhook

 * 

 * Validates state transitions and prevents duplicate updates

 * Cannot overwrite completed or already-cancelled states

 */

function jse_handle_flywire_webhook_cancelled($transaction, $payload) {

    try {

        $current_status = strtolower($transaction->flywire_status ?? '');

        $flywire_transaction_id = $payload['data']['payment_id'] ?? '';

        $event_type = strtolower($payload['event_type'] ?? 'initiated');

        $status_type = strtolower($payload['data']['status'] ?? $event_type);



        // Prevent duplicate updates

        if ($current_status === 'cancelled') {

            jse_log_flywire('Payment already marked as cancelled, skipping duplicate webhook', [

                'booking_reference' => $transaction->booking_reference,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            return [

                'success' => true,

                'message' => 'Payment already cancelled (duplicate webhook)'

            ];

        }



        // Prevent overwriting completed payments

        if ($current_status === 'guaranteed') {

            jse_log_flywire('Payment already guaranteed, cannot cancel', [

                'booking_reference' => $transaction->booking_reference,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            return [

                'success' => true,

                'message' => 'Payment already guaranteed, cannot cancel'

            ];

        }



        // Prevent overwriting failed payments

        if ($current_status === 'failed') {

            jse_log_flywire('Payment already failed, cannot cancel', [

                'booking_reference' => $transaction->booking_reference,

                'flywire_transaction_id' => $flywire_transaction_id

            ]);

            return [

                'success' => true,

                'message' => 'Payment already failed, cannot cancel'

            ];

        }



        $payment_method = '';

        if (!empty($payload['data']['payment_method']['type'])) {

            $payment_method = $payload['data']['payment_method']['type'];

        }



        // Map Flywire status to payment_status

        // Cancelled -> void

        $flywire_status = strtolower($status_type);

        $payment_status = 'void';



        $update_data = [

            'booking_reference' => $transaction->booking_reference,

            'payment_id' => $transaction->payment_id,

            'customer_id' => $transaction->customer_id,

            'payment_status' => $payment_status,

            'flywire_transaction_id' => $flywire_transaction_id,

            'flywire_payment_method' => $payment_method,

            'flywire_status' => $flywire_status,

            'flywire_response' => $payload

        ];



        $transaction_id = jse_save_flywire_transaction($update_data);

        

        jse_log_flywire('Payment cancellation webhook processed', [

            'booking_reference' => $transaction->booking_reference,

            'flywire_transaction_id' => $flywire_transaction_id,

            'transaction_id' => $transaction_id

        ]);



        // Delete quotation for cancelled payment

        if (!empty($transaction->quotation_id)) {

            jse_delete_quotation_by_id($transaction->quotation_id);

        }



        // Trigger custom action hook

        do_action('jse_flywire_payment_cancelled_webhook', $transaction, $payload);



        return [

            'success' => true,

            'message' => 'Payment cancelled'

        ];



    } catch (Exception $e) {

        jse_log_flywire('Error handling cancelled webhook: ' . $e->getMessage());

        

        return [

            'success' => false,

            'message' => $e->getMessage()

        ];

    }

}



// ============================================================================

// TRANSACTION LOOKUP HELPERS

// ============================================================================



/**

 * Find transaction by multiple lookup strategies

 * 

 * Tries to find transaction in this order:

 * 1. By flywire_transaction_id (for webhook retries)

 * 2. By booking_reference (for initial webhooks before ID is stored)

 * 

 * Returns transaction object with items or null if not found

 */

function jse_find_transaction_for_webhook($flywire_transaction_id, $booking_reference = null) {

    // Strategy 1: Lookup by flywire_transaction_id (webhook retry)

    if (!empty($flywire_transaction_id)) {

        $transaction = jse_get_flywire_transaction_by_id($flywire_transaction_id);

        if ($transaction) {

            return $transaction;

        }

    }

    

    // Strategy 2: Lookup by booking_reference (initial webhook, ID not yet saved)

    if (!empty($booking_reference)) {

        $transaction = jse_get_flywire_transaction($booking_reference);

        if ($transaction) {

            return $transaction;

        }

    }

    

    return null;

}



// ============================================================================

// EMAIL NOTIFICATIONS

// ============================================================================



/**

 * Send payment confirmation email

 */

function jse_send_payment_confirmation_email($transaction, $payload) {

    $to = $transaction->customer_email;

    $subject = 'Payment Confirmation - ' . $transaction->booking_reference;

    

    $message = sprintf(

        "Dear %s,\n\n",

        $transaction->customer_first_name

    );

    $message .= sprintf(

        "Your payment for %s has been successfully processed.\n\n",

        @$transaction->accommodation_name

    );

    $message .= sprintf(

        "Booking Reference: %s\n",

        $transaction->booking_reference

    );

    $message .= sprintf(

        "Amount: ¥%s\n",

        number_format($transaction->payment_amount)

    );

    $message .= sprintf(

        "Check-in: %s\n",

        @$transaction->check_in_date

    );

    $message .= sprintf(

        "Check-out: %s\n\n",

        @$transaction->check_out_date

    );

    $message .= "Thank you for your booking!\n\n";

    $message .= "Best regards,\n";

    $message .= get_bloginfo('name');



    wp_mail($to, $subject, $message);

}



/**

 * Send payment failure email

 */

function jse_send_payment_failure_email($transaction, $payload) {

    $to = $transaction->customer_email;

    $subject = 'Payment Failed - ' . $transaction->booking_reference;

    

    $message = sprintf(

        "Dear %s,\n\n",

        $transaction->customer_first_name

    );

    $message .= "Unfortunately, your payment could not be processed.\n\n";

    $message .= sprintf(

        "Booking Reference: %s\n",

        $transaction->booking_reference

    );

    $message .= sprintf(

        "Reason: %s\n\n",

        $payload['error'] ?? 'Payment declined'

    );

    $message .= "Please try again or contact our support team for assistance.\n\n";

    $message .= "Best regards,\n";

    $message .= get_bloginfo('name');



    wp_mail($to, $subject, $message);

}



// ============================================================================

// WEBHOOK VERIFICATION

// ============================================================================



/**

 * Verify webhook signature (optional)

 * Implement based on Flywire's webhook signature verification method

 */

function jse_verify_flywire_webhook_signature($payload, WP_REST_Request $request) {

    // Get the signature from headers

    $signature = $request->get_header('X-Flywire-Signature');



    if (empty($signature)) {

        // If no signature header, allow webhook (can be made stricter)

        return true;

    }



    // Get API key

    $api_key = jse_get_flywire_api_key();



    if (empty($api_key)) {

        return false;

    }



    // Verify signature (implement based on Flywire's method)

    // This is a placeholder - adjust according to Flywire's documentation

    $expected_signature = hash_hmac('sha256', json_encode($payload), $api_key);



    return hash_equals($expected_signature, $signature);

}

