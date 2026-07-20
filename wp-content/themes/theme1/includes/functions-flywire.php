<?php
/**
 * Flywire Payment Gateway Integration
 * 
 * Handles Flywire payment processing, settings, and transaction management
 */

// ============================================================================
// DATABASE TABLE SETUP - NORMALIZED RELATIONAL STRUCTURE
// ============================================================================

/**
 * Create relational database tables for Flywire transactions
 */
function jse_create_flywire_transactions_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // ========== CUSTOMERS TABLE ==========
    $customers_table = $wpdb->prefix . 'flywire_customers';
    $customers_sql = "CREATE TABLE IF NOT EXISTS $customers_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        address VARCHAR(255),
        city VARCHAR(100),
        state VARCHAR(100),
        country VARCHAR(5),
        zip VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_email (email),
        KEY idx_created_at (created_at)
    ) $charset_collate;";
    dbDelta($customers_sql);

    // ========== ACCOMMODATIONS TABLE ==========
    $accommodations_table = $wpdb->prefix . 'flywire_accommodations';
    $accommodations_sql = "CREATE TABLE IF NOT EXISTS $accommodations_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        wp_accommodation_id BIGINT(20) UNSIGNED,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(50),
        permission_type VARCHAR(50) NOT NULL DEFAULT 'Reservation',
        resort_name VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_wp_accommodation_id (wp_accommodation_id),
        KEY idx_created_at (created_at)
    ) $charset_collate;";
    dbDelta($accommodations_sql);

    // ========== ROOMS TABLE ==========
    $rooms_table = $wpdb->prefix . 'flywire_rooms';
    $rooms_sql = "CREATE TABLE IF NOT EXISTS $rooms_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        accommodation_id BIGINT(20) UNSIGNED NOT NULL,
        wp_room_id BIGINT(20) UNSIGNED,
        name VARCHAR(255) NOT NULL,
        image_url LONGTEXT,
        rate_plan_id VARCHAR(100),
        rate_plan_name VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_accommodation_id (accommodation_id),
        KEY idx_wp_room_id (wp_room_id),
        FOREIGN KEY (accommodation_id) REFERENCES $accommodations_table(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($rooms_sql);

    // ========== PAYMENTS TABLE ==========
    $payments_table = $wpdb->prefix . 'flywire_payments';
    $payments_sql = "CREATE TABLE IF NOT EXISTS $payments_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        payment_type VARCHAR(20) NOT NULL,
        payment_amount DECIMAL(12, 2) NOT NULL,
        currency_code VARCHAR(3) NOT NULL DEFAULT 'JPY',
        deposit_amount DECIMAL(12, 2),
        balance_due_amount DECIMAL(12, 2),
        payment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_payment_status (payment_status),
        KEY idx_created_at (created_at)
    ) $charset_collate;";
    dbDelta($payments_sql);

    // ========== TRANSACTIONS TABLE ==========
    $transactions_table = $wpdb->prefix . 'flywire_transactions';
    $transactions_sql = "CREATE TABLE IF NOT EXISTS $transactions_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        booking_reference VARCHAR(100) NOT NULL UNIQUE,
        customer_id BIGINT(20) UNSIGNED NOT NULL,
        payment_id BIGINT(20) UNSIGNED NOT NULL,
        
        -- Guest Totals (for entire booking)
        total_adults INT DEFAULT 0,
        total_children INT DEFAULT 0,
        total_infants INT DEFAULT 0,
        total_guests INT DEFAULT 0,
        total_nights INT DEFAULT 0,
        
        -- Quotation Details (NEW)
        quotation_id BIGINT(20) UNSIGNED,
        
        -- Flywire Details
        flywire_transaction_id VARCHAR(255) UNIQUE,
        flywire_payment_method VARCHAR(100),
        flywire_status VARCHAR(50),
        flywire_response LONGTEXT,
        
        -- Meta
        transaction_mode VARCHAR(20) NOT NULL DEFAULT 'sandbox',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Indexes
        KEY idx_booking_reference (booking_reference),
        KEY idx_customer_id (customer_id),
        KEY idx_quotation_id (quotation_id),
        KEY idx_payment_id (payment_id),
        KEY idx_payment_status (flywire_status),
        KEY idx_created_at (created_at),
        KEY idx_flywire_transaction_id (flywire_transaction_id),
        
        -- Foreign Keys
        FOREIGN KEY (customer_id) REFERENCES $customers_table(id) ON DELETE RESTRICT,
        FOREIGN KEY (payment_id) REFERENCES $payments_table(id) ON DELETE RESTRICT
    ) $charset_collate;";
    dbDelta($transactions_sql);

    // ========== TRANSACTION ITEMS TABLE (Multi-property/room support) ==========
    $transaction_items_table = $wpdb->prefix . 'flywire_transaction_items';
    $transaction_items_sql = "CREATE TABLE IF NOT EXISTS $transaction_items_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        transaction_id BIGINT(20) UNSIGNED NOT NULL,
        accommodation_id BIGINT(20) UNSIGNED NOT NULL,
        room_id BIGINT(20) UNSIGNED NOT NULL,
        
        -- Booking Details (per property)
        check_in_date DATE NOT NULL,
        check_out_date DATE NOT NULL,
        number_of_nights INT,
        adults INT DEFAULT 0,
        children INT DEFAULT 0,
        infants INT DEFAULT 0,
        guests_count INT DEFAULT 0,
        
        -- Payment Details (per item)
        deposit DECIMAL(12, 2) DEFAULT 0,
        balance_due DECIMAL(12, 2) DEFAULT 0,
        due_date DATE,
        
        -- Ordering
        item_order INT DEFAULT 0,
        
        -- Meta
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Indexes
        KEY idx_transaction_id (transaction_id),
        KEY idx_accommodation_id (accommodation_id),
        KEY idx_room_id (room_id),
        KEY idx_check_in_date (check_in_date),
        KEY idx_check_out_date (check_out_date),
        
        -- Foreign Keys
        FOREIGN KEY (transaction_id) REFERENCES $transactions_table(id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES $accommodations_table(id) ON DELETE RESTRICT,
        FOREIGN KEY (room_id) REFERENCES $rooms_table(id) ON DELETE RESTRICT
    ) $charset_collate;";
    dbDelta($transaction_items_sql);
}
add_action('wp_loaded', 'jse_create_flywire_transactions_table');

// ============================================================================
// DATABASE MIGRATIONS
// ============================================================================

/**
 * Migration: Normalize payment_status to lowercase
 */
function jse_migrate_payments_table_status() {
    global $wpdb;
    $table = $wpdb->prefix . 'flywire_payments';
    
    // Update existing 'Pending' to 'pending', 'Completed' to 'complete', etc.
    $wpdb->query("UPDATE $table SET payment_status = 'pending' WHERE payment_status = 'Pending'");
    $wpdb->query("UPDATE $table SET payment_status = 'complete' WHERE payment_status = 'Completed'");
    $wpdb->query("UPDATE $table SET payment_status = 'failed' WHERE payment_status = 'Failed'");
    $wpdb->query("UPDATE $table SET payment_status = 'cancelled' WHERE payment_status = 'Cancelled'");
    $wpdb->query("UPDATE $table SET payment_status = 'void' WHERE payment_status = 'Void'");
    
    jse_log_flywire('Migrated payment_status values to lowercase');
}
// add_action('wp_loaded', 'jse_migrate_payments_table_status', 10);

/**
 * Migration: Add deposit, balance_due, due_date to transaction_items and remove balance_due_date from payments
 */
function jse_migrate_transaction_items_and_payments() {
    global $wpdb;
    $items_table = $wpdb->prefix . 'flywire_transaction_items';
    $payments_table = $wpdb->prefix . 'flywire_payments';
    
    // Check and add deposit column to transaction_items
    $deposit_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = %s 
             AND COLUMN_NAME = 'deposit' 
             AND TABLE_SCHEMA = %s",
            $items_table,
            DB_NAME
        )
    );
    
    if (empty($deposit_exists)) {
        $wpdb->query("ALTER TABLE $items_table ADD COLUMN deposit DECIMAL(12, 2) DEFAULT 0 AFTER guests_count");
        jse_log_flywire('Added deposit column to transaction_items table');
    }
    
    // Check and add balance_due column to transaction_items
    $balance_due_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = %s 
             AND COLUMN_NAME = 'balance_due' 
             AND TABLE_SCHEMA = %s",
            $items_table,
            DB_NAME
        )
    );
    
    if (empty($balance_due_exists)) {
        $wpdb->query("ALTER TABLE $items_table ADD COLUMN balance_due DECIMAL(12, 2) DEFAULT 0 AFTER deposit");
        jse_log_flywire('Added balance_due column to transaction_items table');
    }
    
    // Check and add due_date column to transaction_items
    $due_date_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = %s 
             AND COLUMN_NAME = 'due_date' 
             AND TABLE_SCHEMA = %s",
            $items_table,
            DB_NAME
        )
    );
    
    if (empty($due_date_exists)) {
        $wpdb->query("ALTER TABLE $items_table ADD COLUMN due_date DATE AFTER balance_due");
        jse_log_flywire('Added due_date column to transaction_items table');
    }
    
    // Check and remove balance_due_date from payments table
    $balance_due_date_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = %s 
             AND COLUMN_NAME = 'balance_due_date' 
             AND TABLE_SCHEMA = %s",
            $payments_table,
            DB_NAME
        )
    );
    
    if (!empty($balance_due_date_exists)) {
        $wpdb->query("ALTER TABLE $payments_table DROP COLUMN balance_due_date");
        jse_log_flywire('Removed balance_due_date column from payments table');
    }
}
// add_action('wp_loaded', 'jse_migrate_transaction_items_and_payments', 15);

/**
 * Remove flywire_callback_id column if it exists (migration from old schema)
 */
function jse_migrate_remove_flywire_callback_id() {
    global $wpdb;
    $table = $wpdb->prefix . 'flywire_transactions';
    
    // Check if column exists
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = %s 
             AND COLUMN_NAME = 'flywire_callback_id' 
             AND TABLE_SCHEMA = %s",
            $table,
            DB_NAME
        )
    );
    
    if (!empty($column_exists)) {
        // Drop the column
        $wpdb->query("ALTER TABLE $table DROP COLUMN flywire_callback_id");
        jse_log_flywire('Removed flywire_callback_id column from database');
    }

    // ALTER TABLE `wp_flywire_transactions`
    // ADD COLUMN `quotation_id`  bigint UNSIGNED NOT NULL AFTER `total_nights`;


}
// Run migration on WordPress init (once)
// add_action('wp_loaded', 'jse_migrate_remove_flywire_callback_id', 5);

// ============================================================================
// SETTINGS MANAGEMENT
// ============================================================================

/**
 * Get Flywire settings
 */
function jse_get_flywire_settings() {
    $settings = get_option('jse_flywire_settings', []);
    return wp_parse_args($settings, [
        'recipient_code' => 'FWU',
        'sandbox_api_key' => '',
        'live_api_key' => '',
        'mode' => 'sandbox',
        'currency' => 'JPY',
        'enabled' => false
    ]);
}

/**
 * Save Flywire settings
 */
function jse_save_flywire_settings($settings) {
    $defaults = jse_get_flywire_settings();
    $settings = wp_parse_args($settings, $defaults);
    return update_option('jse_flywire_settings', $settings);
}

/**
 * Get current API key based on mode
 */
function jse_get_flywire_api_key() {
    $settings = jse_get_flywire_settings();
    $mode = $settings['mode'];
    $key = ($mode === 'live') ? $settings['live_api_key'] : $settings['sandbox_api_key'];
    return $key;
}

/**
 * Get Flywire environment (demo or production)
 */
function jse_get_flywire_env() {
    $settings = jse_get_flywire_settings();
    return ($settings['mode'] === 'live') ? 'prod' : 'demo';
}

function jse_is_flywire_live() {
    return jse_get_flywire_env() === 'prod';
}

// ============================================================================
// TRANSACTION MANAGEMENT - RELATIONAL OPERATIONS
// ============================================================================

/**
 * Get or create customer
 */
function jse_get_or_create_customer($customer_data) {
    global $wpdb;
    $table = $wpdb->prefix . 'flywire_customers';
    
    // Check if customer exists by email
    // $existing = $wpdb->get_row(
    //     $wpdb->prepare(
    //         "SELECT id FROM $table WHERE email = %s",
    //         $customer_data['email']
    //     )
    // );
    $existing = @$customer_data['customer_id'] > 0;
    
    if ($existing) {
        // Update existing customer
        // $wpdb->update($table, [
        //     'first_name' => $customer_data['first_name'] ?? '',
        //     'last_name' => $customer_data['last_name'] ?? '',
        //     'phone' => $customer_data['phone'] ?? '',
        //     'address' => $customer_data['address'] ?? '',
        //     'city' => $customer_data['city'] ?? '',
        //     'state' => $customer_data['state'] ?? '',
        //     'country' => $customer_data['country'] ?? '',
        //     'zip' => $customer_data['zip'] ?? ''
        // ], ['id' => $customer_data['customer_id']]);
        
        return $customer_data['customer_id'];
    } else {
        // Insert new customer
        $result = $wpdb->insert($table, [
            'first_name' => $customer_data['first_name'] ?? '',
            'last_name' => $customer_data['last_name'] ?? '',
            'email' => $customer_data['email'] ?? '',
            'phone' => $customer_data['phone'] ?? '',
            'address' => $customer_data['address'] ?? '',
            'city' => $customer_data['city'] ?? '',
            'state' => $customer_data['state'] ?? '',
            'country' => $customer_data['country'] ?? '',
            'zip' => $customer_data['zip'] ?? ''
        ]);

        if ($result === false) {
            // WordPress DB error
            jse_log_flywire('WPDB Customer Insert Error: ', $wpdb->last_error);
            // Query debug
            jse_log_flywire('WPDB Customer Last Query: ', $wpdb->last_query);
            // Insert data debug
            jse_log_flywire('Customer Insert Data: ', print_r($customer_data, true));

            return false;
        }

        // Success
        return $wpdb->insert_id;
    }
}

/**
 * Get or create accommodation
 */
function jse_get_or_create_accommodation($accommodation_data) {
    global $wpdb;
    $table = $wpdb->prefix . 'flywire_accommodations';
    
    // Check if accommodation exists
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM $table WHERE wp_accommodation_id = %d AND resort_name = %s",
            $accommodation_data['wp_accommodation_id'] ?? 0,
            $accommodation_data['resort_name'] ?? ''
        )
    );
    
    if ($existing) {
        // Update existing accommodation
        $wpdb->update($table, [
            'name' => $accommodation_data['name'] ?? '',
            'type' => $accommodation_data['type'] ?? '',
            'permission_type' => $accommodation_data['permission_type'] ?? 'Reservation'
        ], ['id' => $existing->id]);
        
        return $existing->id;
    } else {
        // Insert new accommodation
        $result = $wpdb->insert($table, [
            'wp_accommodation_id' => $accommodation_data['wp_accommodation_id'] ?? null,
            'name' => $accommodation_data['name'] ?? '',
            'type' => $accommodation_data['type'] ?? '',
            'permission_type' => $accommodation_data['permission_type'] ?? 'Reservation',
            'resort_name' => $accommodation_data['resort_name'] ?? ''
        ]);
        
        if ($result === false) {
            // WordPress DB error
            jse_log_flywire('WPDB Accommodation Insert Error: ', $wpdb->last_error);
            // Query debug
            jse_log_flywire('WPDB Accommodation Last Query: ', $wpdb->last_query);
            // Insert data debug
            jse_log_flywire('Accommodation Insert Data: ', print_r($accommodation_data, true));

            return false;
        }

        // Success
        return $wpdb->insert_id;
    }
}

/**
 * Get or create room
 */
function jse_get_or_create_room($accommodation_id, $room_data) {
    global $wpdb;
    $table = $wpdb->prefix . 'flywire_rooms';
    
    // Check if room exists
    // $existing = $wpdb->get_row(
    //     $wpdb->prepare(
    //         "SELECT id FROM $table WHERE accommodation_id = %d AND wp_room_id = %d",
    //         $accommodation_id,
    //         $room_data['wp_room_id'] ?? 0
    //     )
    // );

    $existing = false;
    
    if ($existing) {
        // Update existing room
        $wpdb->update($table, [
            'name' => $room_data['name'] ?? '',
            'image_url' => $room_data['image_url'] ?? '',
            'rate_plan_id' => $room_data['rate_plan_id'] ?? '',
            'rate_plan_name' => $room_data['rate_plan_name'] ?? ''
        ], ['id' => $existing->id]);
        
        return $existing->id;
    } else {
        // Insert new room
        $result = $wpdb->insert($table, [
            'accommodation_id' => $accommodation_id,
            'wp_room_id' => $room_data['wp_room_id'] ?? null,
            'name' => $room_data['name'] ?? '',
            'image_url' => $room_data['image_url'] ?? '',
            'rate_plan_id' => $room_data['rate_plan_id'] ?? '',
            'rate_plan_name' => $room_data['rate_plan_name'] ?? ''
        ]);
        
        if ($result === false) {
            // WordPress DB error
            jse_log_flywire('WPDB Room Insert Error: ', $wpdb->last_error);
            // Query debug
            jse_log_flywire('WPDB Room Last Query: ', $wpdb->last_query);
            // Insert data debug
            jse_log_flywire('Room Insert Data: ', print_r($room_data, true));

            return false;
        }

        // Success
        return $wpdb->insert_id;
    }
}

/**
 * Create payment record
 */
function jse_create_payment($payment_data) {
    global $wpdb;
    $table = $wpdb->prefix . 'flywire_payments';
    
    $result = $wpdb->insert($table, [
        'payment_type' => $payment_data['payment_type'] ?? 'Full Payment',
        'payment_amount' => $payment_data['payment_amount'] ?? 0,
        'currency_code' => $payment_data['currency_code'] ?? 'JPY',
        'deposit_amount' => $payment_data['deposit_amount'] ?? null,
        'balance_due_amount' => $payment_data['balance_due_amount'] ?? null,
        'payment_status' => $payment_data['payment_status'] ?? 'Pending'
    ]);
        
    if ($result === false) {
        // WordPress DB error
        jse_log_flywire('WPDB Payment Insert Error: ', $wpdb->last_error);
        // Query debug
        jse_log_flywire('WPDB Payment Last Query: ', $wpdb->last_query);
        // Insert data debug
        jse_log_flywire('Payment Insert Data: ', print_r($payment_data, true));

        return false;
    }

    // Success
    return $wpdb->insert_id;
}

/**
 * Save or update Flywire transaction with multiple properties/rooms support
 * 
 * Supports both single-property and multi-property bookings:
 * 
 * Single property (backward compatible):
 * jse_save_flywire_transaction([
 *     'booking_reference' => 'BK-123',
 *     'customer_email' => '...',
 *     'accommodation_id' => 1,
 *     'room_id' => 1,
 *     'check_in_date' => '2026-12-19',
 *     'check_out_date' => '2026-12-25',
 *     'payment_amount' => 100000,
 *     // ...
 * ]);
 * 
 * Multiple properties:
 * jse_save_flywire_transaction([
 *     'booking_reference' => 'BK-456',
 *     'customer_email' => '...',
 *     'transaction_items' => [
 *         [
 *             'accommodation_id' => 1,
 *             'room_id' => 1,
 *             'check_in_date' => '2026-12-19',
 *             'check_out_date' => '2026-12-22',
 *             'adults' => 2,
 *             'children' => 1
 *         ],
 *         [
 *             'accommodation_id' => 2,
 *             'room_id' => 5,
 *             'check_in_date' => '2026-12-22',
 *             'check_out_date' => '2026-12-25',
 *             'adults' => 2
 *         ]
 *     ],
 *     'payment_amount' => 200000,
 *     // ...
 * ]);
 */
function jse_save_flywire_transaction($transaction_data) {
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'flywire_transactions';
    $items_table = $wpdb->prefix . 'flywire_transaction_items';
    
    try {
        // 1. Get or create customer
        $customer_id = jse_get_or_create_customer([
            'customer_id' => $transaction_data['customer_id'] ?? 0,
            'first_name' => $transaction_data['customer_first_name'] ?? '',
            'last_name' => $transaction_data['customer_last_name'] ?? '',
            'email' => $transaction_data['customer_email'] ?? '',
            'phone' => $transaction_data['customer_phone'] ?? '',
            'address' => $transaction_data['customer_address'] ?? '',
            'city' => $transaction_data['customer_city'] ?? '',
            'state' => $transaction_data['customer_state'] ?? '',
            'country' => $transaction_data['customer_country'] ?? '',
            'zip' => $transaction_data['customer_zip'] ?? ''
        ]);
        // var_dump('customer_id', $customer_id);
        
        // 2. Prepare items and calculate totals
        $items = !empty(@$transaction_data['transaction_items']) ? $transaction_data['transaction_items'] : [];
        
        // Calculate totals from items
        $total_adults = 0;
        $total_children = 0;
        $total_infants = 0;
        $total_nights = 0;
        $total_deposit = 0;
        $total_balance_due = 0;
        
        foreach ($items as $item) {
            $total_adults += intval($item['adults'] ?? 0);
            $total_children += intval($item['children'] ?? 0);
            $total_infants += intval($item['infants'] ?? 0);
            $total_deposit += floatval($item['deposit'] ?? 0);
            $total_balance_due += floatval($item['balance_due'] ?? 0);
            
            $check_in = strtotime($item['check_in_date'] ?? 'now');
            $check_out = strtotime($item['check_out_date'] ?? 'now');
            if ($check_in && $check_out) {
                $nights = intval(($check_out - $check_in) / 86400);
                $total_nights = max($total_nights, $nights);
            }
        }
        
        $total_guests = $total_adults + $total_children + $total_infants;
        
        // 3. Check if transaction already exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $transactions_table WHERE booking_reference = %s",
                $transaction_data['booking_reference']
            )
        );
            // var_dump('existing', $existing);
        
        // 4. Create or update payment
        if (isset($transaction_data['payment_id'])) {
            // Update existing payment
            $payment_id = $transaction_data['payment_id'];
            $wpdb->update(
                $wpdb->prefix . 'flywire_payments',
                [
                    // 'deposit_amount' => $total_deposit,
                    // 'balance_due_amount' => $total_balance_due,
                    'payment_status' => $transaction_data['payment_status'] ?? 'pending'
                ],
                ['id' => $payment_id]
            );
        } else {
            // Create new payment
            $payment_id = jse_create_payment([
                'payment_type' => $transaction_data['payment_type'] ?? 'Full Payment',
                'payment_amount' => $transaction_data['payment_amount'] ?? 0,
                'currency_code' => $transaction_data['currency_code'] ?? 'JPY',
                'deposit_amount' => $total_deposit,
                'balance_due_amount' => $total_balance_due,
                'payment_status' => $transaction_data['payment_status'] ?? 'pending'
            ]);
            // var_dump('payment_id', $payment_id);
        }
        
        // 5. Insert or update main transaction
        $transaction_record = [
            'booking_reference' => $transaction_data['booking_reference'] ?? '',
            'customer_id' => $customer_id,
            'payment_id' => $payment_id,
            'total_adults' => $total_adults,
            'total_children' => $total_children,
            'total_infants' => $total_infants,
            'total_guests' => $total_guests,
            'total_nights' => $total_nights,
            'quotation_id' => $transaction_data['quotation_id'] ?? null,
            'flywire_transaction_id' => $transaction_data['flywire_transaction_id'] ?? null,
            'flywire_payment_method' => $transaction_data['flywire_payment_method'] ?? null,
            'flywire_status' => $transaction_data['flywire_status'] ?? null,
            'transaction_mode' => $transaction_data['transaction_mode'] ?? 'sandbox'
        ];
        
        if ($existing) {
            $transaction_record = [
                'flywire_transaction_id' => $transaction_data['flywire_transaction_id'] ?? null,
                'flywire_payment_method' => $transaction_data['flywire_payment_method'] ?? null,
                'flywire_status' => $transaction_data['flywire_status'] ?? null,
                'flywire_response' => json_encode($transaction_data['flywire_response']) ?? null,
            ];
            // Update existing transaction - DO NOT delete items
            $wpdb->update($transactions_table, $transaction_record, ['id' => $existing->id]);
            $transaction_id = $existing->id;

            jse_log_flywire('update transaction: ', print_r($transaction_record, true));
            
            // Update transaction items only if new items are provided
            // This prevents unnecessary delete/reinsert on webhook updates
            if (!empty($items)) {
                // Check if items already exist for this transaction
                $existing_items_count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $items_table WHERE transaction_id = %d",
                        $transaction_id
                    )
                );
                
                // Only insert items if none exist (first time creation)
                // On webhook updates, items are already stored, so skip re-insertion
                if ($existing_items_count == 0) {
                    // Items need to be inserted (first save with items)
                    $item_order = 0;
                    foreach ($items as $item) {
                        // Get or create accommodation
                        $accommodation_id = jse_get_or_create_accommodation([
                            'wp_accommodation_id' => $item['wp_accommodation_id'] ?? null,
                            'name' => $item['accommodation_name'] ?? '',
                            'type' => $item['accommodation_type'] ?? '',
                            'permission_type' => $item['accommodation_permission'] ?? 'Reservation',
                            'resort_name' => $item['resort_name'] ?? ''
                        ]);
                        
                        // Get or create room
                        $room_id = jse_get_or_create_room($accommodation_id, [
                            'wp_room_id' => $item['wp_room_id'] ?? null,
                            'name' => $item['room_name'] ?? '',
                            'image_url' => $item['room_image_url'] ?? '',
                            'rate_plan_id' => $item['rate_plan_id'] ?? '',
                            'rate_plan_name' => $item['rate_plan_name'] ?? ''
                        ]);
                        
                        // Calculate nights for this item
                        $check_in = strtotime($item['check_in_date'] ?? 'now');
                        $check_out = strtotime($item['check_out_date'] ?? 'now');
                        $nights = $check_in && $check_out ? intval(($check_out - $check_in) / 86400) : 0;
                        
                        // Insert transaction item
                        $wpdb->insert($items_table, [
                            'transaction_id' => $transaction_id,
                            'accommodation_id' => $accommodation_id,
                            'room_id' => $room_id,
                            'check_in_date' => $item['check_in_date'] ?? '',
                            'check_out_date' => $item['check_out_date'] ?? '',
                            'number_of_nights' => $nights,
                            'adults' => intval($item['adults'] ?? 0),
                            'children' => intval($item['children'] ?? 0),
                            'infants' => intval($item['infants'] ?? 0),
                            'guests_count' => intval(($item['adults'] ?? 0) + ($item['children'] ?? 0) + ($item['infants'] ?? 0)),
                            'deposit' => $item['deposit'] ?? 0,
                            'balance_due' => $item['balance_due'] ?? 0,
                            'due_date' => $item['due_date'] ?? null,
                            'item_order' => $item_order++
                        ]);
                    }
                }
            }
        } else {
            // Insert new transaction
            $wpdb->insert($transactions_table, $transaction_record);
            $transaction_id = $wpdb->insert_id;
            jse_log_flywire('insert transaction: ', print_r($transaction_record, true));
            
            // Insert transaction items for new transactions
            if (!empty($items)) {
                $item_order = 0;
                foreach ($items as $item) {
                    // Get or create accommodation
                    $accommodation_id = jse_get_or_create_accommodation([
                        'wp_accommodation_id' => $item['wp_accommodation_id'] ?? null,
                        'name' => $item['accommodation_name'] ?? '',
                        'type' => $item['accommodation_type'] ?? '',
                        'permission_type' => $item['accommodation_permission'] ?? 'Reservation',
                        'resort_name' => $item['resort_name'] ?? ''
                    ]);
                    
                    // Get or create room
                    $room_id = jse_get_or_create_room($accommodation_id, [
                        'wp_room_id' => $item['wp_room_id'] ?? null,
                        'name' => $item['room_name'] ?? '',
                        'image_url' => $item['room_image_url'] ?? '',
                        'rate_plan_id' => $item['rate_plan_id'] ?? '',
                        'rate_plan_name' => $item['rate_plan_name'] ?? ''
                    ]);
                    
                    // Calculate nights for this item
                    $check_in = strtotime($item['check_in_date'] ?? 'now');
                    $check_out = strtotime($item['check_out_date'] ?? 'now');
                    $nights = $check_in && $check_out ? intval(($check_out - $check_in) / 86400) : 0;
                    
                    // Insert transaction item
                    $wpdb->insert($items_table, [
                        'transaction_id' => $transaction_id,
                        'accommodation_id' => $accommodation_id,
                        'room_id' => $room_id,
                        'check_in_date' => $item['check_in_date'] ?? '',
                        'check_out_date' => $item['check_out_date'] ?? '',
                        'number_of_nights' => $nights,
                        'adults' => intval($item['adults'] ?? 0),
                        'children' => intval($item['children'] ?? 0),
                        'infants' => intval($item['infants'] ?? 0),
                        'guests_count' => intval(($item['adults'] ?? 0) + ($item['children'] ?? 0) + ($item['infants'] ?? 0)),
                        'deposit' => $item['deposit'] ?? 0,
                        'balance_due' => $item['balance_due'] ?? 0,
                        'due_date' => $item['due_date'] ?? null,
                        'item_order' => $item_order++
                    ]);
                }
            }
        }
        
        return $transaction_id;
        
    } catch (Exception $e) {
        jse_log_flywire('Error saving transaction: ' . $e->getMessage());
        // pre($e->getMessage(), 0);
        // pre($e->getTraceAsString(), 0);
        return false;
    }
}

/**
 * Get transaction with all related data and items (multi-property support)
 * 
 * Returns transaction object with:
 * - Main transaction details (customer, payment, flywire info)
 * - 'items' array containing all properties and rooms in this booking
 * - Each item has accommodation, room, and booking details
 */

/**
 * Get transaction by Flywire transaction ID (from webhook payload)
 * 
 * Used to look up transaction when webhook arrives with payment_id
 */
function jse_get_flywire_transaction_by_id($flywire_transaction_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'flywire_transactions';
    $c = $wpdb->prefix . 'flywire_customers';
    $ti = $wpdb->prefix . 'flywire_transaction_items';
    $a = $wpdb->prefix . 'flywire_accommodations';
    $r = $wpdb->prefix . 'flywire_rooms';
    $p = $wpdb->prefix . 'flywire_payments';
    
    // Get main transaction by flywire_transaction_id
    $query = "
        SELECT 
            t.*,
            c.id as customer_id,
            c.first_name as customer_first_name,
            c.last_name as customer_last_name,
            c.email as customer_email,
            c.phone as customer_phone,
            c.address as customer_address,
            c.city as customer_city,
            c.state as customer_state,
            c.country as customer_country,
            c.zip as customer_zip,
            p.id as payment_id,
            p.payment_type,
            p.payment_amount,
            p.currency_code,
            p.deposit_amount,
            p.balance_due_amount,
            p.payment_status
        FROM $t as t
        LEFT JOIN $c as c ON t.customer_id = c.id
        LEFT JOIN $p as p ON t.payment_id = p.id
        WHERE t.flywire_transaction_id = %s
        LIMIT 1
    ";
    
    $transaction = $wpdb->get_row(
        $wpdb->prepare($query, $flywire_transaction_id)
    );
    
    if (!$transaction) {
        return null;
    }
    
    // Parse Flywire response
    if ($transaction->flywire_response) {
        $transaction->flywire_response = json_decode($transaction->flywire_response, true);
    }
    
    // Get transaction items (properties and rooms)
    $items_query = "
        SELECT 
            ti.*,
            a.name as accommodation_name,
            a.type as accommodation_type,
            a.permission_type as accommodation_permission,
            a.resort_name,
            a.wp_accommodation_id,
            r.name as room_name,
            r.image_url as room_image_url,
            r.rate_plan_id,
            r.rate_plan_name,
            r.wp_room_id
        FROM $ti as ti
        LEFT JOIN $a as a ON ti.accommodation_id = a.id
        LEFT JOIN $r as r ON ti.room_id = r.id
        WHERE ti.transaction_id = %d
        ORDER BY ti.item_order ASC
    ";
    
    $transaction->items = $wpdb->get_results(
        $wpdb->prepare($items_query, $transaction->id)
    );
    
    return $transaction;
}

function jse_get_flywire_transaction($booking_reference) {
    global $wpdb;
    $t = $wpdb->prefix . 'flywire_transactions';
    $c = $wpdb->prefix . 'flywire_customers';
    $ti = $wpdb->prefix . 'flywire_transaction_items';
    $a = $wpdb->prefix . 'flywire_accommodations';
    $r = $wpdb->prefix . 'flywire_rooms';
    $p = $wpdb->prefix . 'flywire_payments';
    
    // Get main transaction
    $query = "
        SELECT 
            t.*,
            c.id as customer_id,
            c.first_name as customer_first_name,
            c.last_name as customer_last_name,
            c.email as customer_email,
            c.phone as customer_phone,
            c.address as customer_address,
            c.city as customer_city,
            c.state as customer_state,
            c.country as customer_country,
            c.zip as customer_zip,
            p.id as payment_id,
            p.payment_type,
            p.payment_amount,
            p.currency_code,
            p.deposit_amount,
            p.balance_due_amount,
            p.payment_status
        FROM $t as t
        LEFT JOIN $c as c ON t.customer_id = c.id
        LEFT JOIN $p as p ON t.payment_id = p.id
        WHERE t.booking_reference = %s
        LIMIT 1
    ";
    
    $transaction = $wpdb->get_row(
        $wpdb->prepare($query, $booking_reference)
    );
    
    if (!$transaction) {
        return null;
    }
    
    // Parse Flywire response
    if ($transaction->flywire_response) {
        $transaction->flywire_response = json_decode($transaction->flywire_response, true);
    }
    
    // Get transaction items (properties and rooms)
    $items_query = "
        SELECT 
            ti.*,
            a.name as accommodation_name,
            a.type as accommodation_type,
            a.permission_type as accommodation_permission,
            a.resort_name,
            a.wp_accommodation_id,
            r.name as room_name,
            r.image_url as room_image_url,
            r.rate_plan_id,
            r.rate_plan_name,
            r.wp_room_id
        FROM $ti as ti
        LEFT JOIN $a as a ON ti.accommodation_id = a.id
        LEFT JOIN $r as r ON ti.room_id = r.id
        WHERE ti.transaction_id = %d
        ORDER BY ti.item_order ASC
    ";
    
    $transaction->items = $wpdb->get_results(
        $wpdb->prepare($items_query, $transaction->id)
    );
    
    return $transaction;
}

/**
 * Get transaction items/details for a booking reference
 * 
 * Returns array of transaction items with all property and room details
 */
function jse_get_flywire_transaction_items($booking_reference) {
    $transaction = jse_get_flywire_transaction($booking_reference);
    return $transaction ? $transaction->items : [];
}

/**
 * Get all transactions with filtering and pagination (multi-property aware)
 * 
 * Each transaction includes its items array with all properties and rooms
 */
function jse_get_flywire_transactions($limit = 20, $offset = 0, $filters = []) {
    global $wpdb;
    $t = $wpdb->prefix . 'flywire_transactions';
    $c = $wpdb->prefix . 'flywire_customers';
    $p = $wpdb->prefix . 'flywire_payments';
    $ti = $wpdb->prefix . 'flywire_transaction_items';
    
    // Get main transactions
    $query = "
        SELECT DISTINCT
            t.*,
            c.first_name as customer_first_name,
            c.last_name as customer_last_name,
            c.email as customer_email,
            c.phone as customer_phone,
            c.address as customer_address,
            c.city as customer_city,
            c.state as customer_state,
            c.country as customer_country,
            c.zip as customer_zip,
            p.payment_type,
            p.payment_amount,
            p.currency_code,
            p.deposit_amount,
            p.balance_due_amount,
            p.payment_status
        FROM $t as t
        LEFT JOIN $c as c ON t.customer_id = c.id
        LEFT JOIN $p as p ON t.payment_id = p.id
        LEFT JOIN $ti as ti ON t.id = ti.transaction_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filters
    if (!empty($filters['payment_status'])) {
        $query .= " AND p.payment_status = %s";
        $params[] = $filters['payment_status'];
    }
    
    if (!empty($filters['flywire_status'])) {
        $query .= " AND t.flywire_status = %s";
        $params[] = $filters['flywire_status'];
    }
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(t.created_at) >= %s";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(t.created_at) <= %s";
        $params[] = $filters['end_date'];
    }
    
    if (!empty($filters['email'])) {
        $query .= " AND c.email LIKE %s";
        $params[] = '%' . $wpdb->esc_like($filters['email']) . '%';
    }
    
    if (!empty($filters['accommodation_id'])) {
        $query .= " AND ti.accommodation_id = %d";
        $params[] = $filters['accommodation_id'];
    }
    
    if (!empty($filters['check_in_date'])) {
        $query .= " AND ti.check_in_date >= %s";
        $params[] = $filters['check_in_date'];
    }
    
    if (!empty($filters['check_out_date'])) {
        $query .= " AND ti.check_out_date <= %s";
        $params[] = $filters['check_out_date'];
    }
    
    $query .= " ORDER BY t.created_at DESC LIMIT %d OFFSET %d";
    $params[] = $limit;
    $params[] = $offset;
    
    $transactions = $wpdb->get_results(
        $wpdb->prepare($query, ...$params)
    );
    
    if ($transactions) {
        // Load items for each transaction
        foreach ($transactions as $transaction) {
            if ($transaction->flywire_response) {
                $transaction->flywire_response = json_decode($transaction->flywire_response, true);
            }
            
            // Load items (properties and rooms)
            $items_query = "
                SELECT 
                    ti.*,
                    a.name as accommodation_name,
                    a.type as accommodation_type,
                    a.permission_type as accommodation_permission,
                    a.resort_name,
                    r.name as room_name,
                    r.image_url as room_image_url,
                    r.rate_plan_id,
                    r.rate_plan_name
                FROM $ti as ti
                LEFT JOIN " . $wpdb->prefix . "flywire_accommodations as a ON ti.accommodation_id = a.id
                LEFT JOIN " . $wpdb->prefix . "flywire_rooms as r ON ti.room_id = r.id
                WHERE ti.transaction_id = %d
                ORDER BY ti.item_order ASC
            ";
            
            $transaction->items = $wpdb->get_results(
                $wpdb->prepare($items_query, $transaction->id)
            );
        }
    }
    
    return $transactions;
}

/**
 * Get transaction count with filters (multi-property aware)
 */
function jse_get_flywire_transactions_count($filters = []) {
    global $wpdb;
    $t = $wpdb->prefix . 'flywire_transactions';
    $c = $wpdb->prefix . 'flywire_customers';
    $p = $wpdb->prefix . 'flywire_payments';
    $ti = $wpdb->prefix . 'flywire_transaction_items';
    
    $query = "SELECT COUNT(DISTINCT t.id) as count FROM $t as t
              LEFT JOIN $c as c ON t.customer_id = c.id
              LEFT JOIN $p as p ON t.payment_id = p.id
              LEFT JOIN $ti as ti ON t.id = ti.transaction_id
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['payment_status'])) {
        $query .= " AND p.payment_status = %s";
        $params[] = $filters['payment_status'];
    }
    
    if (!empty($filters['flywire_status'])) {
        $query .= " AND t.flywire_status = %s";
        $params[] = $filters['flywire_status'];
    }
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(t.created_at) >= %s";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(t.created_at) <= %s";
        $params[] = $filters['end_date'];
    }
    
    if (!empty($filters['email'])) {
        $query .= " AND c.email LIKE %s";
        $params[] = '%' . $wpdb->esc_like($filters['email']) . '%';
    }
    
    if (!empty($filters['accommodation_id'])) {
        $query .= " AND ti.accommodation_id = %d";
        $params[] = $filters['accommodation_id'];
    }
    
    $result = $wpdb->get_row(
        $wpdb->prepare($query, ...$params)
    );
    
    return $result ? $result->count : 0;
}

/**
 * Delete transaction and related records
 * 
 * Cascade delete:
 * - Deletes transaction (which cascades to transaction_items)
 * - Optionally deletes orphaned payment if no other transactions reference it
 */
function jse_delete_flywire_transaction($booking_reference) {
    global $wpdb;
    $t = $wpdb->prefix . 'flywire_transactions';
    
    // Get transaction to find payment_id
    $transaction = $wpdb->get_row(
        $wpdb->prepare("SELECT id, payment_id FROM $t WHERE booking_reference = %s", $booking_reference)
    );
    
    if (!$transaction) {
        return false;
    }
    
    // Delete transaction (CASCADE will delete items)
    $wpdb->delete($t, ['id' => $transaction->id]);
    
    // Optionally delete orphaned payment if no other transactions reference it
    if ($transaction->payment_id) {
        $p = $wpdb->prefix . 'flywire_payments';
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $t WHERE payment_id = %d", $transaction->payment_id)
        );
        
        if ($count == 0) {
            $wpdb->delete($p, ['id' => $transaction->payment_id]);
        }
    }
    
    return true;
}

/**
 * Get transaction items by transaction ID
 * 
 * Returns array of items with full accommodation and room details
 */
function jse_get_transaction_items_by_id($transaction_id) {
    global $wpdb;
    $ti = $wpdb->prefix . 'flywire_transaction_items';
    $a = $wpdb->prefix . 'flywire_accommodations';
    $r = $wpdb->prefix . 'flywire_rooms';
    
    $query = "
        SELECT 
            ti.*,
            a.name as accommodation_name,
            a.type as accommodation_type,
            a.permission_type as accommodation_permission,
            a.resort_name,
            a.wp_accommodation_id,
            r.name as room_name,
            r.image_url as room_image_url,
            r.rate_plan_id,
            r.rate_plan_name,
            r.wp_room_id
        FROM $ti as ti
        LEFT JOIN $a as a ON ti.accommodation_id = a.id
        LEFT JOIN $r as r ON ti.room_id = r.id
        WHERE ti.transaction_id = %d
        ORDER BY ti.item_order ASC
    ";
    
    return $wpdb->get_results(
        $wpdb->prepare($query, $transaction_id)
    );
}

/**
 * Add transaction item (property + room to existing transaction)
 * 
 * Useful for adding another property to an existing multi-property booking
 */
function jse_add_transaction_item($transaction_id, $item_data) {
    global $wpdb;
    $ti = $wpdb->prefix . 'flywire_transaction_items';
    
    try {
        // Get or create accommodation
        $accommodation_id = jse_get_or_create_accommodation([
            'wp_accommodation_id' => $item_data['wp_accommodation_id'] ?? null,
            'name' => $item_data['accommodation_name'] ?? '',
            'type' => $item_data['accommodation_type'] ?? '',
            'permission_type' => $item_data['accommodation_permission'] ?? 'Reservation',
            'resort_name' => $item_data['resort_name'] ?? ''
        ]);
        
        // Get or create room
        $room_id = jse_get_or_create_room($accommodation_id, [
            'wp_room_id' => $item_data['wp_room_id'] ?? null,
            'name' => $item_data['room_name'] ?? '',
            'image_url' => $item_data['room_image_url'] ?? '',
            'rate_plan_id' => $item_data['rate_plan_id'] ?? '',
            'rate_plan_name' => $item_data['rate_plan_name'] ?? ''
        ]);
        
        // Calculate nights
        $check_in = strtotime($item_data['check_in_date'] ?? 'now');
        $check_out = strtotime($item_data['check_out_date'] ?? 'now');
        $nights = $check_in && $check_out ? intval(($check_out - $check_in) / 86400) : 0;
        
        // Get next item order
        $item_order = $wpdb->get_var(
            $wpdb->prepare("SELECT MAX(item_order) FROM $ti WHERE transaction_id = %d", $transaction_id)
        );
        $item_order = ($item_order !== null ? $item_order : -1) + 1;
        
        // Insert item
        $wpdb->insert($ti, [
            'transaction_id' => $transaction_id,
            'accommodation_id' => $accommodation_id,
            'room_id' => $room_id,
            'check_in_date' => $item_data['check_in_date'] ?? '',
            'check_out_date' => $item_data['check_out_date'] ?? '',
            'number_of_nights' => $nights,
            'adults' => intval($item_data['adults'] ?? 0),
            'children' => intval($item_data['children'] ?? 0),
            'infants' => intval($item_data['infants'] ?? 0),
            'guests_count' => intval(($item_data['adults'] ?? 0) + ($item_data['children'] ?? 0) + ($item_data['infants'] ?? 0)),
            'item_order' => $item_order
        ]);
        
        return $wpdb->insert_id;
        
    } catch (Exception $e) {
        jse_log_flywire('Error adding transaction item: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update transaction item (one property in a multi-property booking)
 */
function jse_update_transaction_item($item_id, $item_data) {
    global $wpdb;
    $ti = $wpdb->prefix . 'flywire_transaction_items';
    
    try {
        // Calculate nights
        $check_in = strtotime($item_data['check_in_date'] ?? 'now');
        $check_out = strtotime($item_data['check_out_date'] ?? 'now');
        $nights = $check_in && $check_out ? intval(($check_out - $check_in) / 86400) : 0;
        
        $wpdb->update($ti, [
            'check_in_date' => $item_data['check_in_date'] ?? '',
            'check_out_date' => $item_data['check_out_date'] ?? '',
            'number_of_nights' => $nights,
            'adults' => intval($item_data['adults'] ?? 0),
            'children' => intval($item_data['children'] ?? 0),
            'infants' => intval($item_data['infants'] ?? 0),
            'guests_count' => intval(($item_data['adults'] ?? 0) + ($item_data['children'] ?? 0) + ($item_data['infants'] ?? 0))
        ], ['id' => $item_id]);
        
        return true;
        
    } catch (Exception $e) {
        jse_log_flywire('Error updating transaction item: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete transaction item (remove one property from multi-property booking)
 */
function jse_delete_transaction_item($item_id) {
    global $wpdb;
    $ti = $wpdb->prefix . 'flywire_transaction_items';
    
    return $wpdb->delete($ti, ['id' => $item_id]);
}

// ============================================================================
// PAYMENT PROCESSING HELPER FUNCTIONS
// ============================================================================

/**
 * Get accommodation permission type
 */
function jse_get_accommodation_permission($accommodation_id) {
    $permission = get_post_meta($accommodation_id, 'acc_booking_permission', true);
    return !empty($permission) ? $permission : 'Reservation';
}

/**
 * Determine if payment should be authorized only or authorized + captured
 */
function jse_get_payment_action($accommodation_id) {
    $permission = jse_get_accommodation_permission($accommodation_id);
    
    if ($permission === 'Request') {
        return 'authorize'; // Authorization only
    } else {
        return 'authorize_and_capture'; // Full capture
    }
}

/**
 * Format payment amount for Flywire (as integer, no decimals for JPY)
 */
function jse_format_payment_amount($amount, $currency = 'JPY') {
    // Flywire expects amounts as integers
    // For JPY, multiply by 1 (no decimals)
    // For other currencies, multiply by 100
    if ($currency === 'JPY') {
        return intval($amount);
    }
    return intval($amount * 100);
}

/**
 * Generate unique booking reference
 */
function jse_generate_booking_reference() {
    $timestamp = time();
    $random = wp_rand(1000, 9999);
    return 'BK-' . $timestamp . '-' . $random;
}

/**
 * Format booking reference for Flywire
 * 
 * Converts booking reference to Flywire format: JSE{booking_reference}_{counter}
 * Example: "1844" becomes "JSE1844_1"
 * 
 * @param string $booking_reference Booking reference from quotation API (e.g., "1844")
 * @param int $counter Optional counter/sequence number (default: 1)
 * @return string Formatted reference for Flywire
 */
function jse_format_flywire_reference($booking_reference, $counter = 1) {
    // Remove any existing prefix if present
    $ref = $booking_reference;
    if (strpos($ref, '_') !== false) {
        // Extract just the numeric part if it's already formatted
        $parts = explode('_', $ref);
        $ref = preg_replace('/[^0-9]/', '', $parts[0]); // Remove JSE prefix if present
    }
    
    return 'JSE' . $ref . '_' . intval($counter);
}

/**
 * Extract original booking reference from Flywire formatted reference
 * 
 * Converts "JSE1844_1" back to "1844"
 * 
 * @param string $flywire_reference Formatted reference from Flywire (e.g., "JSE1844_1")
 * @return string Original booking reference
 */
function jse_extract_booking_reference_from_flywire($flywire_reference) {
    // Remove JSE prefix and everything after underscore
    $ref = str_replace('JSE', '', $flywire_reference);
    if (strpos($ref, '_') !== false) {
        $ref = explode('_', $ref)[0];
    }
    return $ref;
}

// ============================================================================
// LOGGING
// ============================================================================

/**
 * Log Flywire activity to separate log file (independent of WP_DEBUG)
 */
function jse_log_flywire($message, $data = []) {
    // Create logs directory if it doesn't exist
    $log_dir = WP_CONTENT_DIR . '/logs/flywire';
    
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    
    // Create log file path with date
    $log_file = $log_dir . '/' . gmdate('Y-m-d') . '.log';
    
    // Format log entry with timestamp
    $timestamp = gmdate('Y-m-d H:i:s');
    $log_entry = sprintf('[%s] %s', $timestamp, $message);
    
    // Append data if provided
    if (!empty($data)) {
        if (is_array($data) || is_object($data)) {
            $log_entry .= ' | Data: ' . json_encode($data);
        } else {
            $log_entry .= ' | Data: ' . $data;
        }
    }
    
    // Add newline and write to file
    $log_entry .= "\n";
    // Fallback to direct file write if filesystem API fails
    @file_put_contents($log_file, (string) $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // Also log to WP debug log if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[Flywire] ' . $message . (empty($data) ? '' : ' ' . json_encode($data)));
    }
}
