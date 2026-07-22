<?php
/**
 * Flywire Transactions Management Page
 */

function jse_render_flywire_transactions_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'flywire_transactions';

    // Get filters from query string
    $payment_status = isset($_GET['payment_status']) ? sanitize_text_field($_GET['payment_status']) : '';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    $search_email = isset($_GET['search_email']) ? sanitize_text_field($_GET['search_email']) : '';
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

    $admin_notice = '';

    // Handle individual actions (view, delete)
    if (isset($_GET['action']) && isset($_GET['transaction_id'])) {
        $transaction_id = intval($_GET['transaction_id']);

        if ($_GET['action'] === 'view' && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'view_transaction_' . $transaction_id)) {
                wp_die('Nonce verification failed');
            }
            jse_render_transaction_detail($transaction_id);
            return;
        }

        if ($_GET['action'] === 'delete' && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_transaction_' . $transaction_id)) {
                wp_die('Nonce verification failed');
            }

            $trx = jse_get_flywire_transaction_by_id_admin($transaction_id);
            if ($trx && !empty($trx->booking_reference)) {
                jse_delete_flywire_transaction($trx->booking_reference);
                $admin_notice = '<div class="notice notice-success is-dismissible"><p>Transaction deleted successfully.</p></div>';
            } else {
                $admin_notice = '<div class="notice notice-error is-dismissible"><p>Transaction not found.</p></div>';
            }
        }
    }

    // Handle bulk delete
    if (
        isset($_POST['bulk_action']) &&
        $_POST['bulk_action'] === 'delete' &&
        isset($_POST['transaction_ids']) &&
        is_array($_POST['transaction_ids'])
    ) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk_delete_transactions')) {
            wp_die('Nonce verification failed');
        }

        $deleted = 0;
        foreach ($_POST['transaction_ids'] as $transaction_id_raw) {
            $transaction_id = intval($transaction_id_raw);
            if ($transaction_id <= 0) {
                continue;
            }

            $trx = jse_get_flywire_transaction_by_id_admin($transaction_id);
            if ($trx && !empty($trx->booking_reference) && jse_delete_flywire_transaction($trx->booking_reference)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $admin_notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html($deleted) . ' transaction(s) deleted successfully.</p></div>';
        } else {
            $admin_notice = '<div class="notice notice-warning is-dismissible"><p>No transactions were deleted.</p></div>';
        }
    }

    $per_page = 20;
    $offset = ($paged - 1) * $per_page;

    // Build filters array
    $filters = [];
    if (!empty($payment_status)) {
        $filters['payment_status'] = $payment_status;
    }
    if (!empty($start_date)) {
        $filters['start_date'] = $start_date;
    }
    if (!empty($end_date)) {
        $filters['end_date'] = $end_date;
    }
    if (!empty($search_email)) {
        $filters['email'] = $search_email;
    }

    // Get transactions and count
    $transactions = jse_get_flywire_transactions($per_page, $offset, $filters);
    $total = jse_get_flywire_transactions_count($filters);
    $total_pages = ceil($total / $per_page);

    ?>
    <div class="wrap">
        <h1>Flywire Transactions</h1>

        <?php echo $admin_notice; ?>

        <!-- Filter Form - Horizontal Layout -->
        <div class="card" style="margin: 20px 0; padding: 15px;">
            <form method="get" action="" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="jse_flywire_transactions" />
                
                <!-- Payment Status Filter -->
                <div style="flex: 0 1 auto;">
                    <label for="payment_status" style="display: block; margin-bottom: 5px; font-weight: bold;">Status</label>
                    <select name="payment_status" id="payment_status" style="width: 150px;">
                        <option value="">All</option>
                        <option value="pending" <?php selected($payment_status, 'pending'); ?>>Pending</option>
                        <option value="complete" <?php selected($payment_status, 'complete'); ?>>Complete</option>
                        <option value="failed" <?php selected($payment_status, 'failed'); ?>>Failed</option>
                        <option value="cancelled" <?php selected($payment_status, 'cancelled'); ?>>Cancelled</option>
                        <option value="void" <?php selected($payment_status, 'void'); ?>>Void</option>
                    </select>
                </div>

                <!-- Start Date -->
                <div style="flex: 0 1 auto;">
                    <label for="start_date" style="display: block; margin-bottom: 5px; font-weight: bold;">From</label>
                    <input 
                        type="date" 
                        name="start_date" 
                        id="start_date" 
                        value="<?php echo esc_attr($start_date); ?>"
                        style="width: 150px;"
                    />
                </div>

                <!-- End Date -->
                <div style="flex: 0 1 auto;">
                    <label for="end_date" style="display: block; margin-bottom: 5px; font-weight: bold;">To</label>
                    <input 
                        type="date" 
                        name="end_date" 
                        id="end_date" 
                        value="<?php echo esc_attr($end_date); ?>"
                        style="width: 150px;"
                    />
                </div>

                <!-- Email Search -->
                <div style="flex: 1 1 auto; min-width: 200px;">
                    <label for="search_email" style="display: block; margin-bottom: 5px; font-weight: bold;">Email</label>
                    <input 
                        type="email" 
                        name="search_email" 
                        id="search_email" 
                        placeholder="Search email"
                        value="<?php echo esc_attr($search_email); ?>"
                        style="width: 100%;"
                    />
                </div>

                <!-- Buttons -->
                <div style="flex: 0 1 auto; display: flex; gap: 10px;">
                    <button type="submit" class="button button-primary">Filter</button>
                    <a href="?page=jse_flywire_transactions" class="button">Reset</a>
                </div>
            </form>
        </div>

        <!-- Transactions Table -->
        <form method="post" action="">
            <?php wp_nonce_field('bulk_delete_transactions'); ?>
            <div style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="jse_flywire_transactions" />
                <select name="bulk_action">
                    <option value="">Bulk actions</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="button" onclick="return confirm('Are you sure you want to delete selected transactions?');">Apply</button>
            </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 3%;"><input type="checkbox" id="jse-select-all-transactions" /></th>
                    <th style="width: 10%;">Booking Ref</th>
                    <th style="width: 12%;">Customer</th>
                    <th style="width: 12%;">Email</th>
                    <th style="width: 8%;">Properties</th>
                    <th style="width: 10%;">Amount (¥)</th>
                    <th style="width: 10%;">Total Deposit</th>
                    <th style="width: 10%;">Balance Due</th>
                    <th style="width: 8%;">Payment Status</th>
                    <th style="width: 8%;">Flywire Status</th>
                    <th style="width: 10%;">Date</th>
                    <th style="width: 9%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 20px;">No transactions found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td style="padding: 20px; text-align: center;">
                                <input type="checkbox" name="transaction_ids[]" value="<?php echo esc_attr($transaction->id); ?>" class="jse-transaction-checkbox" />
                            </td>
                            <td>
                                <strong><?php echo esc_html($transaction->booking_reference); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html($transaction->customer_first_name . ' ' . $transaction->customer_last_name); ?>
                            </td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($transaction->customer_email); ?>">
                                    <?php echo esc_html($transaction->customer_email); ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                $item_count = !empty($transaction->items) ? count($transaction->items) : 0;
                                echo esc_html($item_count . ' property' . ($item_count !== 1 ? 'ies' : ''));
                                if (!empty($transaction->items) && $item_count <= 3) {
                                    echo '<br/><small style="color: #666;">';
                                    $names = array_map(function($item) {
                                        return esc_html($item->accommodation_name);
                                    }, $transaction->items);
                                    echo esc_html(implode(', ', $names));
                                    echo '</small>';
                                }
                                ?>
                            </td>
                            <td>
                                ¥<?php echo number_format($transaction->payment_amount); ?>
                            </td>
                            <td>
                                <?php if ($transaction->deposit_amount > 0): ?>
                                    ¥<?php echo number_format($transaction->deposit_amount); ?>
                                <?php else: ?>
                                    <span style="color: #999;">–</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($transaction->balance_due_amount > 0): ?>
                                    ¥<?php echo number_format($transaction->balance_due_amount); ?>
                                <?php else: ?>
                                    <span style="color: #999;">–</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="transaction-status-<?php echo esc_attr(strtolower($transaction->payment_status)); ?>">
                                    <?php echo esc_html(ucfirst($transaction->payment_status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($transaction->flywire_status): ?>
                                    <span style="font-size: 11px; color: #666; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">
                                        <?php echo esc_html($transaction->flywire_status); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">–</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($transaction->created_at))); ?>
                            </td>
                            <td>
                                <a href="<?php echo wp_nonce_url('?page=jse_flywire_transactions&action=view&transaction_id=' . $transaction->id, 'view_transaction_' . $transaction->id); ?>" class="button button-small">View</a>
                                <a href="<?php echo wp_nonce_url('?page=jse_flywire_transactions&action=delete&transaction_id=' . $transaction->id, 'delete_transaction_' . $transaction->id); ?>" class="button button-small" onclick="return confirm('Are you sure you want to delete this transaction?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> transactions</span>
                    <span class="pagination-links">
                        <?php
                        $query_args = [];
                        if (!empty($payment_status)) $query_args['payment_status'] = $payment_status;
                        if (!empty($start_date)) $query_args['start_date'] = $start_date;
                        if (!empty($end_date)) $query_args['end_date'] = $end_date;
                        if (!empty($search_email)) $query_args['search_email'] = $search_email;

                        if ($paged > 1) {
                            $prev_args = array_merge($query_args, ['page' => 'jse_flywire_transactions', 'paged' => $paged - 1]);
                            echo '<a class="prev-page button" href="' . add_query_arg($prev_args) . '">‹ Previous</a> ';
                        }

                        echo '<span class="paging-input">';
                        echo "Page <span class='current-page'>" . $paged . "</span> of <span class='total-pages'>" . $total_pages . "</span>";
                        echo '</span>';

                        if ($paged < $total_pages) {
                            $next_args = array_merge($query_args, ['page' => 'jse_flywire_transactions', 'paged' => $paged + 1]);
                            echo ' <a class="next-page button" href="' . add_query_arg($next_args) . '">Next ›</a>';
                        }
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .transaction-status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .transaction-status-complete {
            background-color: #d4edda;
            color: #155724;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .transaction-status-failed {
            background-color: #f8d7da;
            color: #721c24;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .transaction-status-cancelled {
            background-color: #e2e3e5;
            color: #383d41;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .transaction-status-void {
            background-color: #f8d7da;
            color: #721c24;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
    </style>
    <script>
        (function() {
            const selectAll = document.getElementById('jse-select-all-transactions');
            if (!selectAll) return;

            selectAll.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.jse-transaction-checkbox');
                checkboxes.forEach(function(cb) {
                    cb.checked = selectAll.checked;
                });
            });
        })();
    </script>
    <?php
}

// ============================================================================
// TRANSACTION DETAIL VIEW
// ============================================================================

function jse_render_transaction_detail($transaction_id) {
    $transaction = jse_get_flywire_transaction_by_id_admin($transaction_id);

    if (!$transaction) {
        wp_die('Transaction not found');
    }

    $flywire_response = $transaction->flywire_response ? (is_array($transaction->flywire_response) ? $transaction->flywire_response : json_decode($transaction->flywire_response, true)) : [];

    ?>
    <div class="wrap">
        <h1>Transaction Details</h1>
        <a href="?page=jse_flywire_transactions" class="button">← Back to Transactions</a>

        <!-- Main Transaction Info -->
        <div class="card" style="margin-top: 20px; background: #f0f7ff; padding: 20px; border-left: 4px solid #0073aa;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <p style="margin: 0;"><strong>Booking Reference:</strong><br/><span style="font-size: 18px; color: #0073aa; font-weight: bold;"><?php echo esc_html($transaction->booking_reference); ?></span></p>
                </div>
                <div>
                    <p style="margin: 0;"><strong>Total Amount:</strong><br/><span style="font-size: 18px; color: #28a745; font-weight: bold;">¥<?php echo number_format($transaction->payment_amount); ?></span></p>
                </div>
                <div>
                    <p style="margin: 0;"><strong>Total Deposit:</strong><br/><span style="font-size: 18px; color: #0073aa; font-weight: bold;">¥<?php echo number_format($transaction->deposit_amount ?? 0); ?></span></p>
                </div>
                <div>
                    <p style="margin: 0;"><strong>Balance Due:</strong><br/><span style="font-size: 18px; color: #dc3545; font-weight: bold;">¥<?php echo number_format($transaction->balance_due_amount ?? 0); ?></span></p>
                </div>
                <div>
                    <p style="margin: 0;"><strong>Payment Status:</strong><br/><span class="transaction-status-<?php echo esc_attr(strtolower($transaction->payment_status)); ?>" style="font-size: 14px;"><?php echo esc_html(ucfirst($transaction->payment_status)); ?></span></p>
                </div>
                <div>
                    <p style="margin: 0;"><strong>Flywire Status:</strong><br/><span style="font-size: 14px; color: #666;"><?php echo esc_html($transaction->flywire_status ?? '–'); ?></span></p>
                </div>
                <div>
                    <p style="margin: 0;"><strong>Created:</strong><br/><span style="font-size: 14px;"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($transaction->created_at))); ?></span></p>
                </div>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="card" style="margin-top: 20px;">
            <h2>Customer Information</h2>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px; width: 25%;"><strong>Name:</strong></td>
                    <td style="padding: 12px; width: 25%;"><?php echo esc_html($transaction->customer_first_name . ' ' . $transaction->customer_last_name); ?></td>
                    <td style="padding: 12px; width: 25%;"><strong>Email:</strong></td>
                    <td style="padding: 12px; width: 25%;"><?php echo esc_html($transaction->customer_email); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px;"><strong>Phone:</strong></td>
                    <td style="padding: 12px;"><?php echo esc_html($transaction->customer_phone ?? '–'); ?></td>
                    <td style="padding: 12px;"><strong>Country:</strong></td>
                    <td style="padding: 12px;"><?php echo esc_html($transaction->customer_country ?? '–'); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px;"><strong>City:</strong></td>
                    <td style="padding: 12px;"><?php echo esc_html($transaction->customer_city ?? '–'); ?></td>
                    <td style="padding: 12px;"><strong>Zip:</strong></td>
                    <td style="padding: 12px;"><?php echo esc_html($transaction->customer_zip ?? '–'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 12px;"><strong>Address:</strong></td>
                    <td colspan="3" style="padding: 12px;"><?php echo esc_html($transaction->customer_address ?? '–'); ?></td>
                </tr>
            </table>
        </div>

        <!-- Payment Information -->
        <div class="card" style="margin-top: 20px;">
            <h2>Payment Information</h2>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px; width: 25%;"><strong>Payment Type:</strong></td>
                    <td style="padding: 12px; width: 25%;"><?php echo esc_html($transaction->payment_type ?? '–'); ?></td>
                    <td style="padding: 12px; width: 25%;"><strong>Currency:</strong></td>
                    <td style="padding: 12px; width: 25%;"><?php echo esc_html($transaction->currency_code ?? 'JPY'); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px;"><strong>Total Amount:</strong></td>
                    <td style="padding: 12px; font-weight: bold; color: #28a745;">¥<?php echo number_format($transaction->payment_amount); ?></td>
                    <td style="padding: 12px;"><strong>Payment Status:</strong></td>
                    <td style="padding: 12px;"><span class="transaction-status-<?php echo esc_attr(strtolower($transaction->payment_status)); ?>"><?php echo esc_html(ucfirst($transaction->payment_status)); ?></span></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px;"><strong>Deposit Amount:</strong></td>
                    <td style="padding: 12px;">¥<?php echo number_format($transaction->deposit_amount ?? 0); ?></td>
                    <td style="padding: 12px;"><strong>Balance Due:</strong></td>
                    <td style="padding: 12px;">¥<?php echo number_format($transaction->balance_due_amount ?? 0); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px;"><strong>Flywire Transaction ID:</strong></td>
                    <td style="padding: 12px; font-family: monospace; font-size: 12px;"><?php echo esc_html($transaction->flywire_transaction_id ?? '–'); ?></td>
                    <td style="padding: 12px;"><strong>Flywire Status:</strong></td>
                    <td style="padding: 12px;"><?php echo esc_html($transaction->flywire_status ?? '–'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 12px;"><strong>Payment Method:</strong></td>
                    <td style="padding: 12px;"><?php echo esc_html($transaction->flywire_payment_method ?? '–'); ?></td>
                    <td style="padding: 12px;"><strong>Mode:</strong></td>
                    <td style="padding: 12px;"><?php echo esc_html(ucfirst($transaction->transaction_mode)); ?></td>
                </tr>
            </table>
        </div>

        <!-- Booking Items (Multi-Property Support) -->
        <?php if (!empty($transaction->items) && is_array($transaction->items)): ?>
            <div class="card" style="margin-top: 20px;">
                <h2>Booking Details (<?php echo count($transaction->items); ?> Property<?php echo count($transaction->items) !== 1 ? 'ies' : ''; ?>)</h2>
                
                <?php foreach ($transaction->items as $index => $item): ?>
                    <div style="border: 1px solid #e0e0e0; border-radius: 5px; padding: 20px; margin-bottom: 15px; background: #fafafa;">
                        <h3 style="margin-top: 0; color: #0073aa;">Property <?php echo ($index + 1); ?>: <?php echo esc_html($item->accommodation_name); ?></h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <!-- Accommodation Details -->
                            <div>
                                <p style="margin: 0 0 10px 0; font-weight: bold; color: #666;">Accommodation</p>
                                <table style="width: 100%; font-size: 14px;">
                                    <tr><td style="padding: 4px 0;"><strong>Name:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->accommodation_name); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Resort:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->resort_name ?? '–'); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Type:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->accommodation_type ?? '–'); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Permission:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->accommodation_permission ?? 'Reservation'); ?></td></tr>
                                </table>
                            </div>

                            <!-- Room Details -->
                            <div>
                                <p style="margin: 0 0 10px 0; font-weight: bold; color: #666;">Room</p>
                                <table style="width: 100%; font-size: 14px;">
                                    <tr><td style="padding: 4px 0;"><strong>Name:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->room_name); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Rate Plan:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->rate_plan_name ?? '–'); ?></td></tr>
                                </table>
                            </div>

                            <!-- Dates -->
                            <div>
                                <p style="margin: 0 0 10px 0; font-weight: bold; color: #666;">Dates</p>
                                <table style="width: 100%; font-size: 14px;">
                                    <tr><td style="padding: 4px 0;"><strong>Check-in:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->check_in_date); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Check-out:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->check_out_date); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Nights:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->number_of_nights); ?></td></tr>
                                </table>
                            </div>

                            <!-- Guests -->
                            <div>
                                <p style="margin: 0 0 10px 0; font-weight: bold; color: #666;">Guests</p>
                                <table style="width: 100%; font-size: 14px;">
                                    <tr><td style="padding: 4px 0;"><strong>Adults:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->adults); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Children:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->children); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Infants:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->infants); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Total:</strong></td><td style="padding: 4px 8px; font-weight: bold;"><?php echo esc_html($item->guests_count); ?></td></tr>
                                </table>
                            </div>

                            <!-- Payment Details -->
                            <div>
                                <p style="margin: 0 0 10px 0; font-weight: bold; color: #666;">Payment Details</p>
                                <table style="width: 100%; font-size: 14px;">
                                    <tr><td style="padding: 4px 0;"><strong>Deposit:</strong></td><td style="padding: 4px 8px;">¥<?php echo number_format($item->deposit ?? 0); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Balance Due:</strong></td><td style="padding: 4px 8px;">¥<?php echo number_format($item->balance_due ?? 0); ?></td></tr>
                                    <tr><td style="padding: 4px 0;"><strong>Due Date:</strong></td><td style="padding: 4px 8px;"><?php echo esc_html($item->due_date ?? '–'); ?></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Guest Summary -->
        <div class="card" style="margin-top: 20px; background: #f9f9f9;">
            <h2>Booking Summary</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo esc_html($transaction->total_guests ?? 0); ?></p>
                    <p style="margin: 5px 0 0 0; color: #666;">Total Guests</p>
                </div>
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo esc_html($transaction->total_nights ?? 0); ?></p>
                    <p style="margin: 5px 0 0 0; color: #666;">Total Nights</p>
                </div>
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo esc_html($transaction->total_adults ?? 0); ?></p>
                    <p style="margin: 5px 0 0 0; color: #666;">Adults</p>
                </div>
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo esc_html($transaction->total_children ?? 0); ?></p>
                    <p style="margin: 5px 0 0 0; color: #666;">Children</p>
                </div>
            </div>
        </div>

        <!-- Flywire API Response -->
        <?php if (!empty($flywire_response)): ?>
            <div class="card" style="margin-top: 20px;">
                <h2>Flywire API Response</h2>
                <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px;">
                    <?php echo esc_html(json_encode($flywire_response, JSON_PRETTY_PRINT)); ?>
                </pre>
            </div>
        <?php endif; ?>

        <!-- Timestamps -->
        <div class="card" style="margin-top: 20px; background: #f9f9f9;">
            <p style="margin: 0; font-size: 14px;">
                <strong>Created:</strong> <?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($transaction->created_at))); ?><br/>
                <strong>Updated:</strong> <?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($transaction->updated_at))); ?>
            </p>
        </div>

        <a href="?page=jse_flywire_transactions" class="button" style="margin-top: 20px;">← Back to Transactions</a>
    </div>

    <style>
        .card table {
            width: 100%;
            border-collapse: collapse;
        }
        .card table tr {
            border-bottom: 1px solid #e0e0e0;
        }
        .card table td {
            padding: 12px 0;
        }
        .card table td:first-child {
            font-weight: 600;
            width: 30%;
        }
        .transaction-status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .transaction-status-completed {
            background-color: #d4edda;
            color: #155724;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .transaction-status-failed {
            background-color: #f8d7da;
            color: #721c24;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .transaction-status-cancelled {
            background-color: #e2e3e5;
            color: #383d41;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
    </style>
    <?php
}

// Helper function to retrieve transaction for admin display
function jse_get_flywire_transaction_by_id_admin($transaction_id) {
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
        WHERE t.id = %d
        LIMIT 1
    ";
    
    $transaction = $wpdb->get_row(
        $wpdb->prepare($query, $transaction_id)
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
            r.name as room_name,
            r.image_url as room_image_url,
            r.rate_plan_id,
            r.rate_plan_name
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
