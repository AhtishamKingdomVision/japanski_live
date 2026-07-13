<?php
/**
 * Bulk Sync Admin Page
 *
 * Registers a WordPress admin page that lets administrators sync all
 * accommodation properties in configurable chunks so that server load
 * is kept low.  Progress is reported back to the browser via AJAX so
 * the page never times out.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ──────────────────────────────────────────────
 * 1.  Register admin menu page
 * ────────────────────────────────────────────── */

add_action( 'admin_menu', 'kv_sync_register_admin_page' );
function kv_sync_register_admin_page() {
    add_submenu_page(
        'edit.php?post_type=accommodation',                        // Parent: Tools menu
        'Bulk Property Sync',               // Page title
        'Bulk Property Sync',               // Menu label
        'manage_options',                   // Capability required
        'kv-bulk-sync',                     // Menu slug
        'kv_sync_render_admin_page'         // Render callback
    );
}

/* ──────────────────────────────────────────────
 * 2.  Enqueue admin scripts / styles
 * ────────────────────────────────────────────── */

add_action( 'admin_enqueue_scripts', 'kv_sync_enqueue_assets' );
function kv_sync_enqueue_assets( $hook ) {
    if ( $hook !== 'accommodation_page_kv-bulk-sync' ) {
        return;
    }

    wp_enqueue_style(
        'kv-sync-admin',
        get_template_directory_uri() . '/css/sync-admin.css',
        [],
        filemtime( get_theme_file_path( '/css/sync-admin.css' ) )
    );

    wp_enqueue_script(
        'kv-sync-admin',
        get_template_directory_uri() . '/js/sync-admin.js',
        [ 'jquery' ],
        filemtime( get_theme_file_path( '/js/sync-admin.js' ) ),
        true
    );

    wp_localize_script( 'kv-sync-admin', 'kvSync', [
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'kv_sync_nonce' ),
        'chunkSize'   => intval( get_option( 'kv_sync_chunk_size', 3 ) ),
        'isResumable' => (bool) get_option( 'kv_sync_in_progress', false ),
        'resumePage'  => intval( get_option( 'hz_page', 1 ) ),
        'resumeTotal' => intval( get_option( 'hz_total_pages', 1 ) ),
        'i18n'        => [
            'syncing'    => __( 'Syncing…', 'kv' ),
            'done'       => __( 'Sync complete!', 'kv' ),
            'error'      => __( 'An error occurred. Please try again.', 'kv' ),
            'stopping'   => __( 'Stopping after current chunk…', 'kv' ),
            'stopped'    => __( 'Sync stopped.', 'kv' ),
            'resume'     => __( 'Resume Sync', 'kv' ),
            'resuming'   => __( 'Resuming sync from page %d of %d…', 'kv' ),
        ],
    ] );
}

/* ──────────────────────────────────────────────
 * 3.  Render the admin page HTML
 * ────────────────────────────────────────────── */

function kv_sync_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.' ) );
    }

    /* Save chunk size setting */
    if (
        isset( $_POST['kv_sync_save_settings'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'kv_sync_settings' )
    ) {
        $chunk_size = max( 1, min( 20, intval( $_POST['kv_sync_chunk_size'] ?? 3 ) ) );
        update_option( 'kv_sync_chunk_size', $chunk_size, false );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.' ) . '</p></div>';
    }

    /* Clear sync logs */
    if (
        isset( $_POST['kv_sync_clear_logs'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'kv_sync_clear_logs' )
    ) {
        delete_option( 'kv_sync_logs' );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sync logs cleared.' ) . '</p></div>';
    }

    /* Fetch stored stats */
    $last_sync        = get_option( 'kv_sync_last_run', '' );
    $total_properties = intval( get_option( 'kv_sync_total_properties', 0 ) );
    $last_added       = intval( get_option( 'kv_sync_last_added', 0 ) );
    $last_updated     = intval( get_option( 'kv_sync_last_updated', 0 ) );
    $chunk_size       = intval( get_option( 'kv_sync_chunk_size', 3 ) );

    $last_sync_display = $last_sync
        ? esc_html( $last_sync )
        : esc_html__( 'Never' );
    ?>
    <div class="wrap kv-sync-wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Bulk Property Sync' ); ?></h1>
        <hr class="wp-header-end">

        <!-- ── Stat cards ── -->
        <div class="kv-sync-cards">
            <div class="kv-sync-card">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Last Sync' ); ?></span>
                <span class="kv-sync-card__value kv-color-blue" id="kv-stat-last-sync">
                    <?php echo $last_sync_display; ?>
                </span>
            </div>
            <div class="kv-sync-card">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Total Properties' ); ?></span>
                <span class="kv-sync-card__value kv-color-blue" id="kv-stat-total">
                    <?php echo esc_html( $total_properties ); ?>
                </span>
            </div>
            <div class="kv-sync-card">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Added (Last Sync)' ); ?></span>
                <span class="kv-sync-card__value kv-color-green" id="kv-stat-added">
                    <?php echo esc_html( $last_added ); ?>
                </span>
            </div>
            <div class="kv-sync-card">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Updated (Last Sync)' ); ?></span>
                <span class="kv-sync-card__value kv-color-yellow" id="kv-stat-updated">
                    <?php echo esc_html( $last_updated ); ?>
                </span>
            </div>
        </div>

        <!-- ── Progress bar ── -->
        <div class="kv-sync-progress-wrap" id="kv-progress-wrap" style="display:none;">
            <div class="kv-sync-progress-bar">
                <div class="kv-sync-progress-fill" id="kv-progress-fill"></div>
            </div>
            <p class="kv-sync-progress-label" id="kv-progress-label"></p>
        </div>

        <!-- ── Status message ── -->
        <p class="kv-sync-status" id="kv-sync-status"></p>

        <!-- ── Action buttons ── -->
        <div class="kv-sync-actions">
            <button type="button" class="button button-primary kv-sync-btn" id="kv-run-sync">
                <?php esc_html_e( 'Run Sync Now' ); ?>
            </button>
            <button type="button" class="button kv-sync-btn" id="kv-resume-sync" style="display:none;">
                <?php esc_html_e( 'Resume Sync' ); ?>
            </button>
            <button type="button" class="button kv-sync-btn" id="kv-stop-sync" style="display:none;">
                <?php esc_html_e( 'Stop Sync' ); ?>
            </button>
        </div>

        <!-- ── Sync Logs ── -->
        <?php
        $sync_logs = get_option( 'kv_sync_logs', [] );
        $sync_logs = array_reverse( $sync_logs ); // Show newest first
        $log_count = count( $sync_logs );
        ?>
        <div class="kv-sync-logs-wrap" style="margin-top: 30px;">
            <h2><?php esc_html_e( 'Sync Logs' ); ?> <span style="font-size: 14px; color: #666;">(<?php echo esc_html( $log_count ); ?> entries)</span></h2>

            <?php if ( ! empty( $sync_logs ) ) : ?>
                <div style="max-height: 500px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 4px;">
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 160px;"><?php esc_html_e( 'Timestamp' ); ?></th>
                                <th style="width: 100px;"><?php esc_html_e( 'Property ID' ); ?></th>
                                <th><?php esc_html_e( 'Property Name' ); ?></th>
                                <th style="width: 100px;"><?php esc_html_e( 'Status' ); ?></th>
                                <th><?php esc_html_e( 'Error / Notes' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $sync_logs as $log ) :
                                $status = $log['status'] ?? 'unknown';
                                $status_class = '';
                                if ( $status === 'success' ) {
                                    $status_class = 'background: #d4edda; color: #155724; font-weight: 600;';
                                } elseif ( $status === 'failed' ) {
                                    $status_class = 'background: #f8d7da; color: #721c24; font-weight: 600;';
                                } elseif ( $status === 'processing' ) {
                                    $status_class = 'background: #fff3cd; color: #856404; font-weight: 600;';
                                }
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $log['timestamp'] ?? '–' ); ?></td>
                                    <td><?php echo esc_html( $log['property_id'] ?? '–' ); ?></td>
                                    <td><?php echo esc_html( $log['property_name'] ?? '–' ); ?></td>
                                    <td><span style="padding: 2px 8px; border-radius: 3px; font-size: 12px; <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
                                    <td><?php echo esc_html( $log['error'] ?: '–' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="post" style="margin-top: 10px;">
                    <?php wp_nonce_field( 'kv_sync_clear_logs' ); ?>
                    <input type="submit" name="kv_sync_clear_logs" class="button button-secondary" value="<?php esc_attr_e( 'Clear Logs' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all sync logs?' ); ?>');">
                </form>
            <?php else : ?>
                <p style="color: #666;"><?php esc_html_e( 'No sync logs available yet. Run a sync to generate logs.' ); ?></p>
            <?php endif; ?>
        </div>

        <!-- ── Settings ── -->
        <div class="kv-sync-settings-wrap">
            <h2><?php esc_html_e( 'Sync Settings' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'kv_sync_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="kv_sync_chunk_size">
                                <?php esc_html_e( 'Properties per Chunk' ); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="kv_sync_chunk_size"
                                name="kv_sync_chunk_size"
                                value="<?php echo esc_attr( $chunk_size ); ?>"
                                min="1"
                                max="20"
                                class="small-text"
                            >
                            <p class="description">
                                <?php esc_html_e( 'Number of properties to sync per request. Lower values reduce server load. Recommended: 3–5.' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input
                        type="submit"
                        name="kv_sync_save_settings"
                        class="button button-secondary"
                        value="<?php esc_attr_e( 'Save Settings' ); ?>"
                    >
                </p>
            </form>
        </div>
    </div>
    <?php
}

/* ──────────────────────────────────────────────
 * 4.  AJAX: start a fresh sync (resets counters)
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_start', 'kv_ajax_sync_start' );
function kv_ajax_sync_start() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        check_ajax_referer( 'kv_sync_nonce', 'nonce' );

        /* Reset all pagination and session counters */
        update_option( 'hz_page', 1, false );
        update_option( 'hz_total_pages', 1, false );
        update_option( 'kv_sync_session_added', 0, false );
        update_option( 'kv_sync_session_updated', 0, false );
        update_option( 'kv_sync_in_progress', true, false );

        /* Initialize seen-ID trackers for soft-delete detection at finalize */
        delete_option( 'kv_sync_seen_property_ids' );
        delete_option( 'kv_sync_seen_room_ids' );
        update_option( 'kv_sync_seen_property_ids', [], false );
        update_option( 'kv_sync_seen_room_ids', [], false );

        wp_send_json_success( [ 'message' => 'Sync session started' ] );
    } catch ( Exception $e ) {
        error_log( 'kv_ajax_sync_start error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ], 500 );
    }
}

/* ──────────────────────────────────────────────
 * 4b. AJAX: resume an interrupted sync
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_resume', 'kv_ajax_sync_resume' );
function kv_ajax_sync_resume() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        check_ajax_referer( 'kv_sync_nonce', 'nonce' );

        $page_num    = intval( get_option( 'hz_page', 1 ) );
        $total_pages = intval( get_option( 'hz_total_pages', 1 ) );

        /* If already finished, nothing to resume */
        if ( $page_num > $total_pages && $total_pages > 1 ) {
            wp_send_json_success( [
                'message'     => 'Sync already complete.',
                'page'        => $page_num,
                'total_pages' => $total_pages,
                'done'        => true,
            ] );
        }

        update_option( 'kv_sync_in_progress', true, false );

        wp_send_json_success( [
            'message'     => 'Sync resumed',
            'page'        => $page_num,
            'total_pages' => $total_pages,
            'done'        => false,
        ] );
    } catch ( Exception $e ) {
        error_log( 'kv_ajax_sync_resume error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ], 500 );
    }
}

/* ──────────────────────────────────────────────
 * 5.  AJAX: process one chunk of properties
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_chunk', 'kv_ajax_sync_chunk' );
function kv_ajax_sync_chunk() {
    // set_time_limit(60*8); // Sets the limit to 60 seconds * min (X minutes)
    // ini_set('max_execution_time', 60*8); // Set to 60 seconds * min
    set_time_limit(0);   // Sets an infinite execution time (no limit)
    ini_set('max_execution_time', '0');   // Set to infinite

    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        check_ajax_referer( 'kv_sync_nonce', 'nonce' );

        $chunk_size  = max( 1, min( 20, intval( get_option( 'kv_sync_chunk_size', 3 ) ) ) );
        $page_num    = intval( get_option( 'hz_page', 1 ) );
        $total_pages = intval( get_option( 'hz_total_pages', 1 ) );

        /* All pages processed */
        if ( $page_num > $total_pages && $total_pages > 1 ) {
            kv_sync_finalize();
            wp_send_json_success( [
                'done'        => true,
                'page'        => $page_num,
                'total_pages' => $total_pages,
                'added'       => intval( get_option( 'kv_sync_last_added', 0 ) ),
                'updated'     => intval( get_option( 'kv_sync_last_updated', 0 ) ),
                'total'       => intval( get_option( 'kv_sync_total_properties', 0 ) ),
                'last_sync'   => get_option( 'kv_sync_last_run', '' ),
            ] );
        }

        /* Fetch properties for this page */
        $result = hz_get_limited_properties( $page_num, $chunk_size );

        if ( $result === false || ! is_array( $result ) ) {
            wp_send_json_error( [ 'message' => 'Failed to fetch properties from API for page ' . $page_num ] );
        }

        $properties  = $result['properties'] ?? [];
        $api_total   = intval( $result['total_pages'] ?? 1 );

        /* Update total pages from API on first page */
        if ( $api_total > 0 ) {
            update_option( 'hz_total_pages', $api_total, false );
            $total_pages = $api_total;
        }

        /* Track adds vs updates by comparing existing post IDs */
        $existing_ids = kv_sync_get_existing_property_ids( $properties );

        /* ── Collect seen property / room IDs for soft-delete at finalize ── */
        $seen_property_ids = get_option( 'kv_sync_seen_property_ids', [] );
        $seen_room_ids     = get_option( 'kv_sync_seen_room_ids', [] );

        foreach ( $properties as $property ) {
            $pid = trim( (string) ( $property['id'] ?? '' ) );
            if ( $pid !== '' ) {
                $seen_property_ids[] = $pid;
            }

            if ( ! empty( $property['rooms'] ) && is_array( $property['rooms'] ) ) {
                foreach ( $property['rooms'] as $room ) {
                    $rid = trim( (string) ( $room['id'] ?? '' ) );
                    if ( $rid !== '' ) {
                        $seen_room_ids[] = $rid;
                    }
                }
            }
        }

        update_option( 'kv_sync_seen_property_ids', array_values( array_unique( $seen_property_ids ) ), false );
        update_option( 'kv_sync_seen_room_ids', array_values( array_unique( $seen_room_ids ) ), false );

        /* Run the existing mapping logic */
        if ( ! empty( $properties ) ) {
            sq_mapping_properties( $properties );
        }

        /* Count adds vs updates */
        $added   = 0;
        $updated = 0;
        foreach ( $properties as $property ) {
            $pid = trim( (string) ( $property['id'] ?? '' ) );
            if ( $pid === '' ) {
                continue;
            }
            if ( isset( $existing_ids[ $pid ] ) ) {
                $updated++;
            } else {
                $added++;
            }
        }

        /* Accumulate session totals */
        $session_added   = intval( get_option( 'kv_sync_session_added', 0 ) ) + $added;
        $session_updated = intval( get_option( 'kv_sync_session_updated', 0 ) ) + $updated;
        update_option( 'kv_sync_session_added', $session_added, false );
        update_option( 'kv_sync_session_updated', $session_updated, false );

        /* Advance page */
        $next_page = $page_num + 1;
        update_option( 'hz_page', $next_page, false );

        /* If this was the last page, finalize now */
        $done = ( $next_page > $total_pages );
        if ( $done ) {
            kv_sync_finalize();
        }

        wp_send_json_success( [
            'done'        => $done,
            'page'        => $page_num,
            'total_pages' => $total_pages,
            'added'       => $session_added,
            'updated'     => $session_updated,
            'total'       => intval( get_option( 'kv_sync_total_properties', 0 ) ),
            'last_sync'   => get_option( 'kv_sync_last_run', '' ),
        ] );
    } catch ( Exception $e ) {
        error_log( 'kv_ajax_sync_chunk error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ], 500 );
    }
}

/* ──────────────────────────────────────────────
 * 6.  Helper: collect property_id → post_id map
 *     for properties about to be processed so we
 *     can distinguish adds from updates.
 * ────────────────────────────────────────────── */

function kv_sync_get_existing_property_ids( array $properties ) {
    $ids = [];
    foreach ( $properties as $property ) {
        $pid = trim( (string) ( $property['id'] ?? '' ) );
        if ( $pid === '' ) {
            continue;
        }
        $existing = get_post_id_by_typeId( $pid, 'accommodation' );
        if ( $existing ) {
            $ids[ $pid ] = intval( $existing );
        }
    }
    return $ids;
}

/* ──────────────────────────────────────────────
 * 7.  Helper: finalize sync – soft-delete missing items, persist stats
 * ────────────────────────────────────────────── */

function kv_sync_finalize() {
    try {
        global $wpdb;

        $seen_property_ids = get_option( 'kv_sync_seen_property_ids', [] );
        $seen_room_ids     = get_option( 'kv_sync_seen_room_ids', [] );

        /* ── Soft-delete accommodations no longer in API ── */
        $all_acco_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'accommodation',
                'publish'
            )
        );

        foreach ( $all_acco_ids as $post_id ) {
            $property_id = get_post_meta( $post_id, 'property_id', true );
            if ( ! $property_id ) {
                $property_id = get_post_meta( $post_id, 'acc_hotel_id', true );
            }
            if ( ! $property_id ) {
                continue;
            }

            if ( ! in_array( (string) $property_id, array_map( 'strval', $seen_property_ids ), true ) ) {
                wp_update_post( [
                    'ID'          => $post_id,
                    'post_status' => 'pending',
                ] );
            }
        }

        /* ── Soft-delete rooms no longer in API ── */
        $all_room_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'japan_rooms',
                'publish'
            )
        );

        foreach ( $all_room_ids as $post_id ) {
            $room_id = get_post_meta( $post_id, 'actual_room_id', true );
            if ( ! $room_id ) {
                $room_id = get_post_meta( $post_id, 'room_type_id', true );
            }
            if ( ! $room_id ) {
                continue;
            }

            if ( ! in_array( (string) $room_id, array_map( 'strval', $seen_room_ids ), true ) ) {
                wp_update_post( [
                    'ID'          => $post_id,
                    'post_status' => 'pending',
                ] );
            }
        }

        /* ── Persist stats ── */
        $total_posts = wp_count_posts( 'accommodation' );
        $total       = isset( $total_posts->publish ) ? intval( $total_posts->publish ) : 0;

        $last_sync = current_time( 'Y-m-d H:i:s' );

        update_option( 'kv_sync_last_run', $last_sync, false );
        update_option( 'kv_sync_total_properties', $total, false );
        update_option( 'kv_sync_last_added', intval( get_option( 'kv_sync_session_added', 0 ) ), false );
        update_option( 'kv_sync_last_updated', intval( get_option( 'kv_sync_session_updated', 0 ) ), false );

        /* Reset session accumulators */
        update_option( 'kv_sync_session_added', 0, false );
        update_option( 'kv_sync_session_updated', 0, false );

        /* Reset pagination for next cron cycle */
        update_option( 'hz_page', 1, false );

        /* Mark sync as no longer in progress */
        update_option( 'kv_sync_in_progress', false, false );

        /* Clean up seen-ID trackers */
        delete_option( 'kv_sync_seen_property_ids' );
        delete_option( 'kv_sync_seen_room_ids' );
    } catch ( Exception $e ) {
        error_log( 'kv_sync_finalize error: ' . $e->getMessage() );
    }
}
