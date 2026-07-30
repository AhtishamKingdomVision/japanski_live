<?php
/**
 * Flywire Admin Settings Page
 */

// ============================================================================
// ADMIN MENU REGISTRATION
// ============================================================================

function jse_register_flywire_admin_menus() {
    // Main menu
    add_menu_page(
        'Flywire Payments',
        'Flywire Payments',
        'manage_options',
        'jse_flywire_main',
        'jse_render_flywire_main_page',
        'dashicons-money-alt',
        76
    );

    // Settings submenu
    add_submenu_page(
        'jse_flywire_main',
        'Flywire Settings',
        'Settings',
        'manage_options',
        'jse_flywire_settings',
        'jse_render_flywire_settings_page'
    );

    // Transactions submenu
    add_submenu_page(
        'jse_flywire_main',
        'Flywire Transactions',
        'Transactions',
        'manage_options',
        'jse_flywire_transactions',
        'jse_render_flywire_transactions_page'
    );
}
add_action('admin_menu', 'jse_register_flywire_admin_menus');

// ============================================================================
// SETTINGS PAGE
// ============================================================================

function jse_render_flywire_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    $settings = jse_get_flywire_settings();
    $message = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jse_flywire_nonce'])) {
        if (!wp_verify_nonce($_POST['jse_flywire_nonce'], 'jse_flywire_settings')) {
            wp_die('Nonce verification failed');
        }

        $new_settings = [
            'recipient_code' => sanitize_text_field($_POST['recipient_code'] ?? ''),
            'sandbox_api_key' => sanitize_text_field($_POST['sandbox_api_key'] ?? ''),
            'live_api_key' => sanitize_text_field($_POST['live_api_key'] ?? ''),
            'mode' => sanitize_text_field($_POST['mode'] ?? 'sandbox'),
            'currency' => sanitize_text_field($_POST['currency'] ?? 'JPY'),
            'enabled' => isset($_POST['enabled']) ? 1 : 0
        ];

        jse_save_flywire_settings($new_settings);
        $settings = $new_settings;
        $message = '<div class="notice notice-success"><p>Flywire settings saved successfully!</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Flywire Payment Gateway Settings</h1>

        <?php echo $message; ?>

        <form method="POST" action="" class="flywire-settings-form">
            <?php wp_nonce_field('jse_flywire_settings', 'jse_flywire_nonce'); ?>

            <table class="form-table">
                <tbody>
                    <!-- Enable/Disable -->
                    <tr>
                        <th scope="row"><label for="enabled">Enable Flywire</label></th>
                        <td>
                            <input type="checkbox" name="enabled" id="enabled" value="1" <?php checked($settings['enabled']); ?> />
                            <p class="description">Enable or disable Flywire payment processing</p>
                        </td>
                    </tr>

                    <!-- Recipient Code -->
                    <tr>
                        <th scope="row"><label for="recipient_code">Flywire Recipient Code</label></th>
                        <td>
                            <input 
                                type="text" 
                                name="recipient_code" 
                                id="recipient_code" 
                                value="<?php echo esc_attr($settings['recipient_code']); ?>" 
                                class="regular-text"
                                required
                            />
                            <p class="description">Your Flywire recipient code (e.g., 'FWU')</p>
                        </td>
                    </tr>

                    <!-- Mode Selection -->
                    <tr>
                        <th scope="row"><label for="mode">Transaction Mode</label></th>
                        <td>
                            <select name="mode" id="mode" class="regular-text">
                                <option value="sandbox" <?php selected($settings['mode'], 'sandbox'); ?>>Sandbox (Testing)</option>
                                <option value="live" <?php selected($settings['mode'], 'live'); ?>>Live (Production)</option>
                            </select>
                            <p class="description">Select sandbox for testing or live for production transactions</p>
                        </td>
                    </tr>

                    <!-- Currency -->
                    <tr>
                        <th scope="row"><label for="currency">Currency</label></th>
                        <td>
                            <input 
                                type="text" 
                                name="currency" 
                                id="currency" 
                                value="<?php echo esc_attr($settings['currency']); ?>" 
                                class="regular-text"
                                placeholder="JPY"
                            />
                            <p class="description">Currency code (e.g., 'JPY', 'USD')</p>
                        </td>
                    </tr>

                    <!-- Sandbox API Key -->
                    <tr>
                        <th scope="row"><label for="sandbox_api_key">Sandbox API Key</label></th>
                        <td>
                            <input 
                                type="password" 
                                name="sandbox_api_key" 
                                id="sandbox_api_key" 
                                value="<?php echo esc_attr($settings['sandbox_api_key']); ?>" 
                                class="large-text"
                                placeholder="Enter your sandbox API key"
                            />
                            <p class="description">API key for sandbox/testing environment</p>
                        </td>
                    </tr>

                    <!-- Live API Key -->
                    <tr>
                        <th scope="row"><label for="live_api_key">Live API Key</label></th>
                        <td>
                            <input 
                                type="password" 
                                name="live_api_key" 
                                id="live_api_key" 
                                value="<?php echo esc_attr($settings['live_api_key']); ?>" 
                                class="large-text"
                                placeholder="Enter your live API key"
                            />
                            <p class="description" style="color: #d63638;"><strong>⚠️ Warning:</strong> Only use live API keys in production. Use with caution.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Save Settings</button>
            </p>
        </form>

        <!-- Test Connection Section -->
        <div class="card" style="margin-top: 30px;">
            <h2>Connection Test</h2>
            <p>Current Configuration:</p>
            <ul>
                <li><strong>Mode:</strong> <?php echo esc_html(ucfirst($settings['mode'])); ?></li>
                <li><strong>Recipient Code:</strong> <?php echo esc_html($settings['recipient_code']); ?></li>
                <li><strong>Currency:</strong> <?php echo esc_html($settings['currency']); ?></li>
                <li><strong>API Key Configured:</strong> <?php echo (!empty($settings[$settings['mode'] === 'live' ? 'live_api_key' : 'sandbox_api_key'])) ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>'; ?></li>
                <li><strong>Enabled:</strong> <?php echo $settings['enabled'] ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>'; ?></li>
            </ul>
        </div>

        <!-- Documentation -->
        <div class="card" style="margin-top: 30px;">
            <h2>Documentation</h2>
            <p><strong>Flywire Integration Details:</strong></p>
            <ul>
                <li>Payment Authorization: Based on accommodation permission type</li>
                <li>Request permission: Authorization only (no capture)</li>
                <li>Reservation permission: Authorization + Capture</li>
                <li>All transactions are logged in the Transactions table</li>
                <li>Flywire API responses are stored for audit trail</li>
            </ul>
        </div>
    </div>

    <style>
        .flywire-settings-form table {
            margin-top: 20px;
        }
        .flywire-settings-form th {
            text-align: left;
            width: 200px;
            font-weight: 600;
        }
        .card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
        }
        .card h2 {
            margin-top: 0;
        }
        .card ul {
            margin: 10px 0 10px 20px;
        }
        .card li {
            margin: 8px 0;
        }
    </style>
    <?php
}

// ============================================================================
// MAIN DASHBOARD PAGE
// ============================================================================

function jse_render_flywire_main_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    $settings = jse_get_flywire_settings();
    $total_transactions = jse_get_flywire_transactions_count();
    $pending_transactions = jse_get_flywire_transactions_count(['payment_status' => 'Pending']);
    $completed_transactions = jse_get_flywire_transactions_count(['payment_status' => 'Completed']);
    $failed_transactions = jse_get_flywire_transactions_count(['payment_status' => 'Failed']);

    ?>
    <div class="wrap">
        <h1>Flywire Payment Gateway Dashboard</h1>

        <!-- Status Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            <div class="card" style="background: #f8f9fa; border-left: 4px solid #0073aa;">
                <h3 style="margin: 0 0 10px;">Status</h3>
                <p style="font-size: 18px; font-weight: bold; color: <?php echo $settings['enabled'] ? '#28a745' : '#dc3545'; ?>;">
                    <?php echo $settings['enabled'] ? '✓ ENABLED' : '✗ DISABLED'; ?>
                </p>
                <p style="font-size: 12px; margin: 0; color: #666;">Mode: <?php echo ucfirst($settings['mode']); ?></p>
            </div>

            <div class="card" style="background: #f8f9fa; border-left: 4px solid #0073aa;">
                <h3 style="margin: 0 0 10px;">Total Transactions</h3>
                <p style="font-size: 24px; font-weight: bold; color: #0073aa;">
                    <?php echo $total_transactions; ?>
                </p>
            </div>

            <div class="card" style="background: #f8f9fa; border-left: 4px solid #28a745;">
                <h3 style="margin: 0 0 10px;">Completed</h3>
                <p style="font-size: 24px; font-weight: bold; color: #28a745;">
                    <?php echo $completed_transactions; ?>
                </p>
            </div>

            <div class="card" style="background: #f8f9fa; border-left: 4px solid #ffc107;">
                <h3 style="margin: 0 0 10px;">Pending</h3>
                <p style="font-size: 24px; font-weight: bold; color: #ffc107;">
                    <?php echo $pending_transactions; ?>
                </p>
            </div>

            <div class="card" style="background: #f8f9fa; border-left: 4px solid #dc3545;">
                <h3 style="margin: 0 0 10px;">Failed</h3>
                <p style="font-size: 24px; font-weight: bold; color: #dc3545;">
                    <?php echo $failed_transactions; ?>
                </p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="margin-top: 20px;">
            <h2>Quick Actions</h2>
            <p>
                <a href="?page=jse_flywire_settings" class="button button-primary">Configure Settings</a>
                <a href="?page=jse_flywire_transactions" class="button">View Transactions</a>
            </p>
        </div>

        <!-- Configuration Overview -->
        <div class="card" style="margin-top: 20px;">
            <h2>Configuration Overview</h2>
            <table class="wp-list-table" style="width: 100%;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; width: 30%;"><strong>Setting</strong></td>
                    <td style="padding: 10px;"><strong>Value</strong></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px;">Flywire Enabled</td>
                    <td style="padding: 10px;">
                        <span style="color: <?php echo $settings['enabled'] ? '#28a745' : '#dc3545'; ?>;">
                            <?php echo $settings['enabled'] ? 'Yes' : 'No'; ?>
                        </span>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px;">Transaction Mode</td>
                    <td style="padding: 10px;"><?php echo ucfirst($settings['mode']); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px;">Recipient Code</td>
                    <td style="padding: 10px;"><?php echo esc_html($settings['recipient_code']); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px;">Currency</td>
                    <td style="padding: 10px;"><?php echo esc_html($settings['currency']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px;">API Key Status</td>
                    <td style="padding: 10px;">
                        <?php 
                        $current_key = $settings[$settings['mode'] === 'live' ? 'live_api_key' : 'sandbox_api_key'];
                        $status = !empty($current_key) ? 'Configured' : 'Not Configured';
                        $color = !empty($current_key) ? '#28a745' : '#dc3545';
                        ?>
                        <span style="color: <?php echo $color; ?>;">
                            <?php echo $status; ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}
