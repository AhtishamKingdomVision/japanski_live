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

    $resume_page  = intval( get_option( 'hz_page', 1 ) );
    $resume_total = intval( get_option( 'hz_total_pages', 1 ) );
    $in_progress  = (bool) get_option( 'kv_sync_in_progress', false );
    $failed_pages = get_option( 'kv_sync_failed_pages', [] );
    $has_failed   = is_array( $failed_pages ) && ! empty( $failed_pages );
    $sync_phase   = (string) get_option( 'kv_sync_phase', 'main' );

    /* Resume when main pages remain, or skipped pages still need catchup */
    $is_resumable = $in_progress && $resume_total > 1 && (
        $resume_page <= $resume_total || $has_failed || $sync_phase === 'catchup'
    );

    wp_localize_script( 'kv-sync-admin', 'kvSync', [
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'kv_sync_nonce' ),
        'chunkSize'   => max( 1, min( 5, intval( get_option( 'kv_sync_chunk_size', 3 ) ) ) ),
        'isResumable' => $is_resumable,
        'resumePage'  => $resume_page,
        'resumeTotal' => $resume_total,
        'timeoutMs'   => 300000, // 5 minutes — heavy property mapping can exceed 3 minutes
        'maxRetries'  => 2, // quick retries, then skip page and retry failed pages at the end
        'i18n'        => [
            'syncing'    => __( 'Syncing…', 'kv' ),
            'done'       => __( 'Sync complete!', 'kv' ),
            'donePartial'=> __( 'Sync finished. Some skipped pages could not be synced — check logs.', 'kv' ),
            'error'      => __( 'An error occurred. Please try again.', 'kv' ),
            'stopping'   => __( 'Stopping after current chunk…', 'kv' ),
            'stopped'    => __( 'Sync stopped.', 'kv' ),
            'resume'     => __( 'Resume Sync', 'kv' ),
            'startFresh' => __( 'Start Fresh Sync', 'kv' ),
            'freshConfirm' => __( 'This will discard the interrupted sync progress and start again from page 1. Continue?', 'kv' ),
            'resuming'   => __( 'Resuming sync from page %d of %d…', 'kv' ),
            'retrying'   => __( 'Chunk failed, retrying…', 'kv' ),
            'skipping'   => __( 'Page %d failed — continuing… (will retry at end)', 'kv' ),
            'catchup'      => __( 'Retrying skipped pages… (%d left)', 'kv' ),
            'catchupShort' => __( 'Retrying skipped pages…', 'kv' ),
            'busy'       => __( 'Server still processing previous chunk, waiting…', 'kv' ),
            'timeout'    => __( 'Request timed out. Continuing to next page…', 'kv' ),
            'resumeHint' => __( 'Use Resume Sync to continue.', 'kv' ),
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
        $raw_chunk  = intval( $_POST['kv_sync_chunk_size'] ?? 3 );
        $chunk_size = max( 1, min( 5, $raw_chunk ) );
        update_option( 'kv_sync_chunk_size', $chunk_size, false );

        if ( $raw_chunk > 5 || $raw_chunk < 1 ) {
            echo '<div class="notice notice-warning is-dismissible"><p>' .
                esc_html(
                    sprintf(
                        /* translators: %d: clamped chunk size */
                        __( 'Properties per Chunk must be between 1 and 5. Value was adjusted to %d.', 'kv' ),
                        $chunk_size
                    )
                ) .
                '</p></div>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.' ) . '</p></div>';
        }
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
    $chunk_size = max( 1, min( 5, intval( get_option( 'kv_sync_chunk_size', 3 ) ) ) );
    if ( intval( get_option( 'kv_sync_chunk_size', 3 ) ) !== $chunk_size ) {
        update_option( 'kv_sync_chunk_size', $chunk_size, false );
    }

    $last_sync_display = $last_sync
        ? esc_html( $last_sync )
        : esc_html__( 'Never' );

    $sync_logs = get_option( 'kv_sync_logs', [] );
    if ( ! is_array( $sync_logs ) ) {
        $sync_logs = [];
    }
    /* Hide processing rows (legacy duplicates) — show final outcomes only */
    $sync_logs = array_values( array_filter( $sync_logs, static function ( $log ) {
        $status = strtolower( (string) ( $log['status'] ?? '' ) );
        return in_array( $status, [ 'success', 'failed' ], true );
    } ) );
    $sync_logs = array_reverse( $sync_logs );
    $log_count = count( $sync_logs );
    ?>
    <div class="wrap kv-sync-wrap">
        <header class="kv-sync-hero">
            <div class="kv-sync-hero__text">
                <p class="kv-sync-hero__eyebrow"><?php esc_html_e( 'Accommodation tools' ); ?></p>
                <h1 class="kv-sync-hero__title"><?php esc_html_e( 'Bulk Property Sync' ); ?></h1>
                <p class="kv-sync-hero__desc">
                    <?php esc_html_e( 'Pull properties from the booking system in small chunks. Resume if interrupted, or start fresh from page 1.' ); ?>
                </p>
            </div>
        </header>

        <!-- ── Stat cards ── -->
        <div class="kv-sync-cards">
            <div class="kv-sync-card kv-sync-card--last">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Last Sync' ); ?></span>
                <span class="kv-sync-card__value kv-color-blue" id="kv-stat-last-sync">
                    <?php echo $last_sync_display; ?>
                </span>
                <span class="kv-sync-card__hint"><?php esc_html_e( 'When the last full sync finished' ); ?></span>
            </div>
            <div class="kv-sync-card kv-sync-card--total">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Published on Website' ); ?></span>
                <span class="kv-sync-card__value kv-color-blue" id="kv-stat-total">
                    <?php echo esc_html( $total_properties ); ?>
                </span>
                <span class="kv-sync-card__hint"><?php esc_html_e( 'Current live accommodations in WordPress' ); ?></span>
            </div>
            <div class="kv-sync-card kv-sync-card--added">
                <span class="kv-sync-card__label"><?php esc_html_e( 'New Properties Synced' ); ?></span>
                <span class="kv-sync-card__value kv-color-green" id="kv-stat-added">
                    <?php echo esc_html( $last_added ); ?>
                </span>
                <span class="kv-sync-card__hint"><?php esc_html_e( 'Created during last sync run' ); ?></span>
            </div>
            <div class="kv-sync-card kv-sync-card--updated">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Existing Properties Re-synced' ); ?></span>
                <span class="kv-sync-card__value kv-color-yellow" id="kv-stat-updated">
                    <?php echo esc_html( $last_updated ); ?>
                </span>
                <span class="kv-sync-card__hint"><?php esc_html_e( 'Updated during last sync run (not the website total)' ); ?></span>
            </div>
        </div>

        <div class="kv-sync-layout">
            <!-- ── Control panel ── -->
            <section class="kv-sync-panel kv-sync-panel--controls">
                <div class="kv-sync-panel__head">
                    <h2 class="kv-sync-panel__title"><?php esc_html_e( 'Sync Controls' ); ?></h2>
                    <p class="kv-sync-panel__sub"><?php esc_html_e( 'Run, resume, or restart the bulk sync.' ); ?></p>
                </div>

                <div class="kv-sync-progress-wrap" id="kv-progress-wrap" style="display:none;">
                    <div class="kv-sync-progress-bar">
                        <div class="kv-sync-progress-fill" id="kv-progress-fill"></div>
                    </div>
                    <p class="kv-sync-progress-label" id="kv-progress-label"></p>
                </div>

                <p class="kv-sync-status" id="kv-sync-status"></p>

                <div class="kv-sync-actions">
                    <button type="button" class="button button-primary kv-sync-btn" id="kv-run-sync">
                        <?php esc_html_e( 'Run Sync Now' ); ?>
                    </button>
                    <button type="button" class="button button-primary kv-sync-btn" id="kv-resume-sync" style="display:none;">
                        <?php esc_html_e( 'Resume Sync' ); ?>
                    </button>
                    <button type="button" class="button kv-sync-btn" id="kv-start-fresh" style="display:none;">
                        <?php esc_html_e( 'Start Fresh Sync' ); ?>
                    </button>
                    <button type="button" class="button kv-sync-btn kv-sync-btn--danger" id="kv-stop-sync" style="display:none;">
                        <?php esc_html_e( 'Stop Sync' ); ?>
                    </button>
                </div>
            </section>

            <!-- ── Settings ── -->
            <section class="kv-sync-panel kv-sync-panel--settings">
                <div class="kv-sync-panel__head">
                    <h2 class="kv-sync-panel__title"><?php esc_html_e( 'Sync Settings' ); ?></h2>
                    <p class="kv-sync-panel__sub"><?php esc_html_e( 'Lower chunk size if you see timeouts.' ); ?></p>
                </div>
                <form method="post" id="kv-sync-settings-form" class="kv-sync-settings-form">
                    <?php wp_nonce_field( 'kv_sync_settings' ); ?>
                    <label class="kv-sync-field" for="kv_sync_chunk_size">
                        <span class="kv-sync-field__label"><?php esc_html_e( 'Properties per Chunk' ); ?></span>
                        <input
                            type="number"
                            id="kv_sync_chunk_size"
                            name="kv_sync_chunk_size"
                            value="<?php echo esc_attr( $chunk_size ); ?>"
                            min="1"
                            max="5"
                            step="1"
                            required
                            class="kv-sync-field__input"
                        >
                        <span class="kv-sync-field__help">
                            <?php esc_html_e( 'Allowed: 1–5. Recommended: 3. Use 1 if timeouts occur.', 'kv' ); ?>
                        </span>
                        <span class="kv-sync-chunk-error" id="kv-sync-chunk-error" style="display:none;">
                            <?php esc_html_e( 'Please enter a number between 1 and 5.', 'kv' ); ?>
                        </span>
                    </label>
                    <p class="kv-sync-settings-actions">
                        <input
                            type="submit"
                            name="kv_sync_save_settings"
                            class="button button-secondary"
                            value="<?php esc_attr_e( 'Save Settings' ); ?>"
                        >
                    </p>
                </form>
            </section>
        </div>

        <!-- ── Sync Logs ── -->
        <section class="kv-sync-panel kv-sync-panel--logs">
            <div class="kv-sync-panel__head kv-sync-panel__head--row">
                <div>
                    <h2 class="kv-sync-panel__title"><?php esc_html_e( 'Sync Logs' ); ?></h2>
                    <p class="kv-sync-panel__sub">
                        <?php
                        printf(
                            /* translators: %d: log entry count */
                            esc_html__( '%d recent entries', 'kv' ),
                            (int) $log_count
                        );
                        ?>
                    </p>
                </div>
                <?php if ( ! empty( $sync_logs ) ) : ?>
                    <form method="post" class="kv-sync-clear-logs">
                        <?php wp_nonce_field( 'kv_sync_clear_logs' ); ?>
                        <input
                            type="submit"
                            name="kv_sync_clear_logs"
                            class="button button-secondary"
                            value="<?php esc_attr_e( 'Clear Logs' ); ?>"
                            onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all sync logs?' ) ); ?>');"
                        >
                    </form>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $sync_logs ) ) : ?>
                <div class="kv-sync-logs-scroll">
                    <table class="kv-sync-logs-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Timestamp' ); ?></th>
                                <th><?php esc_html_e( 'Property ID' ); ?></th>
                                <th><?php esc_html_e( 'Property Name' ); ?></th>
                                <th><?php esc_html_e( 'Status' ); ?></th>
                                <th><?php esc_html_e( 'Error / Notes' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $sync_logs as $log ) :
                                $status = $log['status'] ?? 'unknown';
                                $badge  = 'kv-sync-badge--muted';
                                if ( $status === 'success' ) {
                                    $badge = 'kv-sync-badge--success';
                                } elseif ( $status === 'failed' ) {
                                    $badge = 'kv-sync-badge--failed';
                                } elseif ( $status === 'processing' ) {
                                    $badge = 'kv-sync-badge--processing';
                                }
                            ?>
                                <tr>
                                    <td class="kv-sync-logs-table__mono"><?php echo esc_html( $log['timestamp'] ?? '–' ); ?></td>
                                    <td class="kv-sync-logs-table__mono"><?php echo esc_html( $log['property_id'] ?? '–' ); ?></td>
                                    <td><?php echo esc_html( $log['property_name'] ?? '–' ); ?></td>
                                    <td>
                                        <span class="kv-sync-badge <?php echo esc_attr( $badge ); ?>">
                                            <?php echo esc_html( ucfirst( $status ) ); ?>
                                        </span>
                                    </td>
                                    <td class="kv-sync-logs-table__notes"><?php echo esc_html( $log['error'] ?: '–' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p class="kv-sync-empty"><?php esc_html_e( 'No sync logs yet. Run a sync to generate activity here.' ); ?></p>
            <?php endif; ?>
        </section>
    </div>
    <?php
}


/* ──────────────────────────────────────────────
 * Sync run ID — invalidate in-flight chunks on stop / fresh start
 * ────────────────────────────────────────────── */

function kv_sync_new_run_id() {
    $run_id = wp_generate_password( 20, false, false );
    update_option( 'kv_sync_run_id', $run_id, false );
    return $run_id;
}

function kv_sync_get_run_id() {
    return (string) get_option( 'kv_sync_run_id', '' );
}

function kv_sync_is_active_run( $run_id ) {
    $run_id = (string) $run_id;
    $current = kv_sync_get_run_id();
    return $run_id !== '' && $current !== '' && hash_equals( $current, $run_id );
}

function kv_sync_stale_payload() {
    return [
        'stale'        => true,
        'done'         => false,
        'message'      => 'Stale sync request ignored.',
        'phase'        => (string) get_option( 'kv_sync_phase', 'main' ),
        'page'         => intval( get_option( 'hz_page', 1 ) ),
        'total_pages'  => max( 1, intval( get_option( 'hz_total_pages', 1 ) ) ),
        'failed_count' => count( kv_sync_get_failed_pages() ),
        'added'        => intval( get_option( 'kv_sync_session_added', 0 ) ),
        'updated'      => intval( get_option( 'kv_sync_session_updated', 0 ) ),
        'total'        => intval( get_option( 'kv_sync_total_properties', 0 ) ),
        'last_sync'    => get_option( 'kv_sync_last_run', '' ),
    ];
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
        update_option( 'kv_sync_phase', 'main', false );
        update_option( 'kv_sync_failed_pages', [], false );
        delete_transient( 'kv_sync_chunk_lock' );

        /* Initialize seen-ID trackers for soft-delete detection at finalize */
        delete_option( 'kv_sync_seen_property_ids' );
        delete_option( 'kv_sync_seen_room_ids' );
        update_option( 'kv_sync_seen_property_ids', [], false );
        update_option( 'kv_sync_seen_room_ids', [], false );

        $run_id = kv_sync_new_run_id();

        wp_send_json_success( [
            'message'     => 'Sync session started',
            'run_id'      => $run_id,
            'page'        => 1,
            'total_pages' => 1,
            'phase'       => 'main',
        ] );
    } catch ( Throwable $e ) {
        error_log( 'kv_ajax_sync_start error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
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
        $failed      = kv_sync_get_failed_pages();
        $phase       = (string) get_option( 'kv_sync_phase', 'main' );

        /* Main finished but skipped pages remain → resume into catchup */
        if ( $page_num > $total_pages && $total_pages > 1 && ! empty( $failed ) ) {
            update_option( 'kv_sync_phase', 'catchup', false );
            $phase = 'catchup';
        }

        /* If already finished with nothing left to catch up, clear stale state */
        if ( $page_num > $total_pages && $total_pages > 1 && empty( $failed ) ) {
            $seen = get_option( 'kv_sync_seen_property_ids', [] );
            if ( ! empty( $seen ) ) {
                kv_sync_finalize( true );
            } else {
                update_option( 'kv_sync_in_progress', false, false );
                update_option( 'hz_page', 1, false );
                update_option( 'hz_total_pages', 1, false );
                update_option( 'kv_sync_phase', 'main', false );
            }
            wp_send_json_success( [
                'message'      => 'Sync already complete.',
                'page'         => $page_num,
                'total_pages'  => $total_pages,
                'done'         => true,
                'phase'        => 'done',
                'failed_count' => 0,
                'added'        => intval( get_option( 'kv_sync_last_added', 0 ) ),
                'updated'      => intval( get_option( 'kv_sync_last_updated', 0 ) ),
                'total'        => intval( get_option( 'kv_sync_total_properties', 0 ) ),
                'last_sync'    => get_option( 'kv_sync_last_run', '' ),
            ] );
        }

        update_option( 'kv_sync_in_progress', true, false );
        delete_transient( 'kv_sync_chunk_lock' );
        $run_id = kv_sync_new_run_id();

        wp_send_json_success( [
            'message'      => 'Sync resumed',
            'run_id'       => $run_id,
            'page'         => $page_num,
            'total_pages'  => $total_pages,
            'done'         => false,
            'phase'        => $phase,
            'failed_count' => count( $failed ),
        ] );
    } catch ( Throwable $e ) {
        error_log( 'kv_ajax_sync_resume error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
    }
}

/* ──────────────────────────────────────────────
 * 4c. AJAX: stop — invalidate in-flight chunks (keep page for resume)
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_stop', 'kv_ajax_sync_stop' );
function kv_ajax_sync_stop() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        check_ajax_referer( 'kv_sync_nonce', 'nonce' );

        /* New run id makes any still-running chunk refuse to advance hz_page */
        $run_id = kv_sync_new_run_id();
        delete_transient( 'kv_sync_chunk_lock' );
        update_option( 'kv_sync_in_progress', true, false );

        wp_send_json_success( [
            'message'      => 'Sync stop acknowledged',
            'run_id'       => $run_id,
            'page'         => intval( get_option( 'hz_page', 1 ) ),
            'total_pages'  => max( 1, intval( get_option( 'hz_total_pages', 1 ) ) ),
            'phase'        => (string) get_option( 'kv_sync_phase', 'main' ),
            'failed_count' => count( kv_sync_get_failed_pages() ),
        ] );
    } catch ( Throwable $e ) {
        error_log( 'kv_ajax_sync_stop error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
    }
}

/* ──────────────────────────────────────────────
 * 5.  AJAX: process one chunk of properties
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_chunk', 'kv_ajax_sync_chunk' );
function kv_ajax_sync_chunk() {
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 0 );
    }
    @ini_set( 'max_execution_time', '0' );
    @ini_set( 'memory_limit', '512M' );
    ignore_user_abort( true );

    $lock_key = 'kv_sync_chunk_lock';
    $got_lock = false;

    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        check_ajax_referer( 'kv_sync_nonce', 'nonce' );

        $run_id = sanitize_text_field( wp_unslash( $_POST['run_id'] ?? '' ) );
        if ( ! kv_sync_is_active_run( $run_id ) ) {
            wp_send_json_success( kv_sync_stale_payload() );
        }

        $lock = get_transient( $lock_key );
        if ( $lock ) {
            /* Same run still working — wait. Different run — steal stale lock. */
            if ( (string) $lock === $run_id ) {
                wp_send_json_success( [
                    'busy'           => true,
                    'retry_after_ms' => 5000,
                    'message'        => 'Previous chunk still running.',
                    'phase'          => get_option( 'kv_sync_phase', 'main' ),
                    'page'           => intval( get_option( 'hz_page', 1 ) ),
                    'total_pages'    => intval( get_option( 'hz_total_pages', 1 ) ),
                    'failed_count'   => count( kv_sync_get_failed_pages() ),
                    'added'          => intval( get_option( 'kv_sync_session_added', 0 ) ),
                    'updated'        => intval( get_option( 'kv_sync_session_updated', 0 ) ),
                ] );
            }
            delete_transient( $lock_key );
        }

        set_transient( $lock_key, $run_id, 10 * MINUTE_IN_SECONDS );
        $got_lock = true;

        if ( ! kv_sync_is_active_run( $run_id ) ) {
            delete_transient( $lock_key );
            wp_send_json_success( kv_sync_stale_payload() );
        }

        $chunk_size  = max( 1, min( 5, intval( get_option( 'kv_sync_chunk_size', 3 ) ) ) );
        $page_num    = intval( get_option( 'hz_page', 1 ) );
        $total_pages = max( 1, intval( get_option( 'hz_total_pages', 1 ) ) );
        $failed      = kv_sync_get_failed_pages();
        $phase       = (string) get_option( 'kv_sync_phase', 'main' );

        /* Main pass finished → retry skipped pages (catchup), then finalize */
        if ( $page_num > $total_pages && $total_pages > 1 ) {
            if ( ! empty( $failed ) ) {
                update_option( 'kv_sync_phase', 'catchup', false );
                $phase = 'catchup';
            } else {
                kv_sync_finalize( true );
                delete_transient( $lock_key );
                wp_send_json_success( kv_sync_done_payload( $page_num, $total_pages, false ) );
            }
        }

        if ( $phase === 'catchup' ) {
            $failed = kv_sync_get_failed_pages();
            if ( empty( $failed ) ) {
                kv_sync_finalize( true );
                delete_transient( $lock_key );
                wp_send_json_success( kv_sync_done_payload( $total_pages, $total_pages, false ) );
            }

            $catchup_page = intval( array_shift( $failed ) );
            update_option( 'kv_sync_failed_pages', array_values( $failed ), false );

            if ( ! kv_sync_is_active_run( $run_id ) ) {
                /* Put page back — this catchup belongs to an old run */
                array_unshift( $failed, $catchup_page );
                update_option( 'kv_sync_failed_pages', array_values( array_unique( array_map( 'intval', $failed ) ) ), false );
                delete_transient( $lock_key );
                wp_send_json_success( kv_sync_stale_payload() );
            }

            $processed = kv_sync_process_page( $catchup_page, $chunk_size, $total_pages );
            if ( is_wp_error( $processed ) ) {
                /* Keep page at front so JS retries the same skipped page */
                array_unshift( $failed, $catchup_page );
                $failed = array_values( array_unique( array_map( 'intval', $failed ) ) );
                update_option( 'kv_sync_failed_pages', $failed, false );
                delete_transient( $lock_key );
                wp_send_json_error( [
                    'message'      => $processed->get_error_message(),
                    'phase'        => 'catchup',
                    'page'         => $catchup_page,
                    'total_pages'  => $total_pages,
                    'failed_count' => count( $failed ),
                ] );
            }

            if ( ! kv_sync_is_active_run( $run_id ) ) {
                delete_transient( $lock_key );
                wp_send_json_success( kv_sync_stale_payload() );
            }

            $failed_left = kv_sync_get_failed_pages();
            $done        = empty( $failed_left );
            if ( $done ) {
                kv_sync_finalize( true );
            }

            delete_transient( $lock_key );
            wp_send_json_success( array_merge( $processed, [
                'done'         => $done,
                'phase'        => 'catchup',
                'page'         => $total_pages,
                'total_pages'  => $total_pages,
                'catchup_page' => $catchup_page,
                'failed_count' => count( $failed_left ),
                'skipped'      => false,
                'partial'      => false,
            ] ) );
        }

        /* ── Main phase ── */
        if ( ! kv_sync_is_active_run( $run_id ) ) {
            delete_transient( $lock_key );
            wp_send_json_success( kv_sync_stale_payload() );
        }

        $processed = kv_sync_process_page( $page_num, $chunk_size, $total_pages );
        if ( is_wp_error( $processed ) ) {
            delete_transient( $lock_key );
            wp_send_json_error( [
                'message'      => $processed->get_error_message(),
                'phase'        => 'main',
                'page'         => $page_num,
                'total_pages'  => intval( get_option( 'hz_total_pages', $total_pages ) ),
                'failed_count' => count( kv_sync_get_failed_pages() ),
            ] );
        }

        $total_pages = intval( $processed['total_pages'] ?? $total_pages );

        /* Stop / fresh start happened while this chunk was working — do not move the cursor */
        if ( ! kv_sync_is_active_run( $run_id ) ) {
            delete_transient( $lock_key );
            wp_send_json_success( kv_sync_stale_payload() );
        }

        $next_page = $page_num + 1;
        update_option( 'hz_page', $next_page, false );

        $failed      = kv_sync_get_failed_pages();
        $main_done   = ( $next_page > $total_pages );
        $done        = false;
        $phase_out   = 'main';

        if ( $main_done ) {
            if ( ! empty( $failed ) ) {
                update_option( 'kv_sync_phase', 'catchup', false );
                $phase_out = 'catchup';
            } else {
                kv_sync_finalize( true );
                $done = true;
            }
        }

        delete_transient( $lock_key );

        wp_send_json_success( array_merge( $processed, [
            'done'         => $done,
            'phase'        => $phase_out,
            'page'         => $page_num,
            'total_pages'  => $total_pages,
            'failed_count' => count( $failed ),
            'skipped'      => false,
            'partial'      => false,
        ] ) );
    } catch ( Throwable $e ) {
        if ( $got_lock ) {
            delete_transient( $lock_key );
        }
        error_log( 'kv_ajax_sync_chunk error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
    }
}

/* ──────────────────────────────────────────────
 * 5b. AJAX: skip a failed page and continue
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_skip', 'kv_ajax_sync_skip' );
function kv_ajax_sync_skip() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        check_ajax_referer( 'kv_sync_nonce', 'nonce' );

        $run_id = sanitize_text_field( wp_unslash( $_POST['run_id'] ?? '' ) );
        if ( ! kv_sync_is_active_run( $run_id ) ) {
            wp_send_json_success( kv_sync_stale_payload() );
        }

        $page_num    = intval( get_option( 'hz_page', 1 ) );
        $total_pages = max( 1, intval( get_option( 'hz_total_pages', 1 ) ) );
        $phase       = (string) get_option( 'kv_sync_phase', 'main' );

        if ( $phase === 'catchup' ) {
            /* During catchup, drop current failed head and continue */
            $failed = kv_sync_get_failed_pages();
            if ( ! empty( $failed ) ) {
                $dropped = intval( array_shift( $failed ) );
                update_option( 'kv_sync_failed_pages', array_values( $failed ), false );
                if ( function_exists( 'kv_sync_log_entry' ) ) {
                    kv_sync_log_entry( [
                        'property_id'   => 'page-' . $dropped,
                        'property_name' => 'Skipped page ' . $dropped,
                        'status'        => 'failed',
                        'error'         => 'Catchup retry failed — page left unsynced',
                        'timestamp'     => current_time( 'Y-m-d H:i:s' ),
                    ] );
                }
            }

            $failed_left = kv_sync_get_failed_pages();
            $done        = empty( $failed_left );
            $partial     = false;
            if ( $done ) {
                /* Soft-delete skipped if any pages were permanently dropped this session */
                kv_sync_finalize( false );
                $partial = true;
            }

            wp_send_json_success( [
                'done'         => $done,
                'phase'        => $done ? 'done' : 'catchup',
                'page'         => $total_pages,
                'total_pages'  => $total_pages,
                'failed_count' => count( $failed_left ),
                'skipped'      => true,
                'partial'      => $partial,
                'added'        => intval( get_option( $done ? 'kv_sync_last_added' : 'kv_sync_session_added', 0 ) ),
                'updated'      => intval( get_option( $done ? 'kv_sync_last_updated' : 'kv_sync_session_updated', 0 ) ),
                'total'        => intval( get_option( 'kv_sync_total_properties', 0 ) ),
                'last_sync'    => get_option( 'kv_sync_last_run', '' ),
            ] );
        }

        /* Main phase: queue page for end-of-run retry, then advance */
        if ( $page_num <= $total_pages ) {
            kv_sync_queue_failed_page( $page_num );
            if ( function_exists( 'kv_sync_log_entry' ) ) {
                kv_sync_log_entry( [
                    'property_id'   => 'page-' . $page_num,
                    'property_name' => 'Page ' . $page_num,
                    'status'        => 'failed',
                    'error'         => 'Chunk failed — queued for retry at end',
                    'timestamp'     => current_time( 'Y-m-d H:i:s' ),
                ] );
            }
        }

        $next_page = $page_num + 1;
        update_option( 'hz_page', $next_page, false );

        $failed    = kv_sync_get_failed_pages();
        $main_done = ( $next_page > $total_pages && $total_pages > 1 );
        $done      = false;
        $phase_out = 'main';

        if ( $main_done ) {
            if ( ! empty( $failed ) ) {
                update_option( 'kv_sync_phase', 'catchup', false );
                $phase_out = 'catchup';
            } else {
                kv_sync_finalize( true );
                $done = true;
            }
        }

        wp_send_json_success( [
            'done'         => $done,
            'phase'        => $phase_out,
            'page'         => $page_num,
            'total_pages'  => $total_pages,
            'failed_count' => count( $failed ),
            'skipped'      => true,
            'partial'      => false,
            'added'        => intval( get_option( 'kv_sync_session_added', 0 ) ),
            'updated'      => intval( get_option( 'kv_sync_session_updated', 0 ) ),
            'total'        => intval( get_option( 'kv_sync_total_properties', 0 ) ),
            'last_sync'    => get_option( 'kv_sync_last_run', '' ),
        ] );
    } catch ( Throwable $e ) {
        error_log( 'kv_ajax_sync_skip error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
    }
}

/* ──────────────────────────────────────────────
 * 6.  Helpers
 * ────────────────────────────────────────────── */

function kv_sync_get_failed_pages() {
    $failed = get_option( 'kv_sync_failed_pages', [] );
    if ( ! is_array( $failed ) ) {
        return [];
    }
    $failed = array_values( array_unique( array_filter( array_map( 'intval', $failed ) ) ) );
    sort( $failed );
    return $failed;
}

function kv_sync_queue_failed_page( $page_num ) {
    $page_num = intval( $page_num );
    if ( $page_num < 1 ) {
        return;
    }
    $failed   = kv_sync_get_failed_pages();
    $failed[] = $page_num;
    $failed   = array_values( array_unique( $failed ) );
    sort( $failed );
    update_option( 'kv_sync_failed_pages', $failed, false );
}

function kv_sync_done_payload( $page, $total_pages, $partial ) {
    return [
        'done'         => true,
        'phase'        => 'done',
        'page'         => $page,
        'total_pages'  => $total_pages,
        'failed_count' => 0,
        'skipped'      => false,
        'partial'      => (bool) $partial,
        'added'        => intval( get_option( 'kv_sync_last_added', 0 ) ),
        'updated'      => intval( get_option( 'kv_sync_last_updated', 0 ) ),
        'total'        => intval( get_option( 'kv_sync_total_properties', 0 ) ),
        'last_sync'    => get_option( 'kv_sync_last_run', '' ),
    ];
}

/**
 * Fetch + map a single API page. Returns stats array or WP_Error.
 */
function kv_sync_process_page( $page_num, $chunk_size, $total_pages = 1 ) {
    $page_num   = intval( $page_num );
    $chunk_size = max( 1, min( 5, intval( $chunk_size ) ) );

    if ( ! function_exists( 'hz_get_limited_properties' ) ) {
        return new WP_Error( 'missing_helper', 'Missing function hz_get_limited_properties(). Sync API helper is not loaded.' );
    }

    $result = hz_get_limited_properties( $page_num, $chunk_size );
    if ( $result === false || ! is_array( $result ) ) {
        return new WP_Error( 'api_fetch', 'Failed to fetch properties from API for page ' . $page_num . '. Check API credentials / rate limits.' );
    }

    $properties = $result['properties'] ?? [];
    $api_total  = intval( $result['total_pages'] ?? 0 );
    if ( $api_total > 0 ) {
        update_option( 'hz_total_pages', $api_total, false );
        $total_pages = $api_total;
    }

    $existing_ids = kv_sync_get_existing_property_ids( $properties );

    $seen_property_ids = get_option( 'kv_sync_seen_property_ids', [] );
    $seen_room_ids     = get_option( 'kv_sync_seen_room_ids', [] );
    if ( ! is_array( $seen_property_ids ) ) {
        $seen_property_ids = [];
    }
    if ( ! is_array( $seen_room_ids ) ) {
        $seen_room_ids = [];
    }

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

    if ( ! empty( $properties ) ) {
        if ( ! function_exists( 'sq_mapping_properties' ) ) {
            return new WP_Error( 'missing_mapper', 'Missing function sq_mapping_properties(). Mapper is not loaded.' );
        }

        foreach ( $properties as $property ) {
            try {
                sq_mapping_properties( [ $property ] );
            } catch ( Throwable $map_error ) {
                $pid   = trim( (string) ( $property['id'] ?? 'unknown' ) );
                $pname = trim( (string) ( $property['client_property_name'] ?? $property['name'] ?? 'unknown' ) );
                error_log( 'kv_sync_process_page mapping error on page ' . $page_num . ' property ' . $pid . ': ' . $map_error->getMessage() );
                if ( function_exists( 'kv_sync_log_entry' ) ) {
                    kv_sync_log_entry( [
                        'property_id'   => $pid,
                        'property_name' => $pname,
                        'status'        => 'failed',
                        'error'         => $map_error->getMessage(),
                        'timestamp'     => current_time( 'Y-m-d H:i:s' ),
                    ] );
                }
            }
        }
    }

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

    $session_added   = intval( get_option( 'kv_sync_session_added', 0 ) ) + $added;
    $session_updated = intval( get_option( 'kv_sync_session_updated', 0 ) ) + $updated;
    update_option( 'kv_sync_session_added', $session_added, false );
    update_option( 'kv_sync_session_updated', $session_updated, false );

    return [
        'added'       => $session_added,
        'updated'     => $session_updated,
        'total'       => intval( get_option( 'kv_sync_total_properties', 0 ) ),
        'last_sync'   => get_option( 'kv_sync_last_run', '' ),
        'total_pages' => $total_pages,
    ];
}

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

function kv_sync_finalize( $do_soft_delete = true ) {
    try {
        global $wpdb;

        $seen_property_ids = get_option( 'kv_sync_seen_property_ids', [] );
        $seen_room_ids     = get_option( 'kv_sync_seen_room_ids', [] );
        if ( ! is_array( $seen_property_ids ) ) {
            $seen_property_ids = [];
        }
        if ( ! is_array( $seen_room_ids ) ) {
            $seen_room_ids = [];
        }

        if ( $do_soft_delete ) {
            /* ── Soft-delete accommodations no longer in API ── */
            $all_acco_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                    'accommodation',
                    'publish'
                )
            );

            $seen_property_ids_str = array_map( 'strval', $seen_property_ids );

            foreach ( $all_acco_ids as $post_id ) {
                $property_id = get_post_meta( $post_id, 'property_id', true );
                if ( ! $property_id ) {
                    $property_id = get_post_meta( $post_id, 'acc_hotel_id', true );
                }
                if ( ! $property_id ) {
                    continue;
                }

                if ( ! in_array( (string) $property_id, $seen_property_ids_str, true ) ) {
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

            $seen_room_ids_str = array_map( 'strval', $seen_room_ids );

            foreach ( $all_room_ids as $post_id ) {
                $room_id = get_post_meta( $post_id, 'actual_room_id', true );
                if ( ! $room_id ) {
                    $room_id = get_post_meta( $post_id, 'room_type_id', true );
                }
                if ( ! $room_id ) {
                    continue;
                }

                if ( ! in_array( (string) $room_id, $seen_room_ids_str, true ) ) {
                    wp_update_post( [
                        'ID'          => $post_id,
                        'post_status' => 'pending',
                    ] );
                }
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
        update_option( 'kv_sync_phase', 'main', false );
        update_option( 'kv_sync_failed_pages', [], false );

        /* Reset total pages marker so Resume does not reappear after completion */
        update_option( 'hz_total_pages', 1, false );

        /* Clean up seen-ID trackers */
        delete_option( 'kv_sync_seen_property_ids' );
        delete_option( 'kv_sync_seen_room_ids' );
    } catch ( Throwable $e ) {
        error_log( 'kv_sync_finalize error: ' . $e->getMessage() );
        update_option( 'kv_sync_in_progress', false, false );
    }
}
