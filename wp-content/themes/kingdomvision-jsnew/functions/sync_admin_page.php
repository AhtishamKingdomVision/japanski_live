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
        'timeoutMs'   => 900000, // 15 minutes — must comfortably exceed the worst-case single
                                  // chunk (large properties with many rooms/images can take
                                  // several minutes even with per-image download timeouts).
                                  // A client timeout shorter than actual processing time causes
                                  // the browser to retry a chunk that's still legitimately
                                  // working, racing a duplicate attempt against the original.
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

/**
 * Split a log error message into its first line + total line count in one pass,
 * so the logs table can show a short preview with a "+N more" link to the full text.
 */
function kv_sync_split_log_error( $msg ) {
    $msg   = (string) $msg;
    $lines = preg_split( '/\r\n|\r|\n/', $msg );
    return [
        'first' => isset( $lines[0] ) ? trim( $lines[0] ) : '',
        'count' => count( $lines ),
    ];
}

/**
 * Write full exception detail (message, class, file/line, stack trace) to the
 * dedicated bulk-sync log, so a crash tells us exactly where it happened
 * instead of just a bare message.
 */
function kv_sync_log_exception( $label, Throwable $e ) {
    $detail = sprintf(
        "%s: %s\nException: %s\nFile: %s:%d\nTrace:\n%s",
        $label,
        $e->getMessage(),
        get_class( $e ),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );

    if ( function_exists( 'kv_sync_write_log' ) ) {
        kv_sync_write_log( $detail );
    } else {
        error_log( $detail );
    }
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

        <!-- ── Background Image Queue ── -->
        <section class="kv-sync-panel kv-sync-panel--image-queue">
            <div class="kv-sync-panel__head">
                <h2 class="kv-sync-panel__title"><?php esc_html_e( 'Background Image Queue', 'kv' ); ?></h2>
                <p class="kv-sync-panel__sub">
                    <?php esc_html_e( 'Featured images are downloaded here in the background — not during sync — so large properties can never time out the sync request itself.', 'kv' ); ?>
                </p>
            </div>

            <div class="kv-sync-cards kv-sync-cards--queue">
                <div class="kv-sync-card">
                    <span class="kv-sync-card__label"><?php esc_html_e( 'Pending' ); ?></span>
                    <span class="kv-sync-card__value kv-color-blue" id="kv-queue-pending">–</span>
                </div>
                <div class="kv-sync-card">
                    <span class="kv-sync-card__label"><?php esc_html_e( 'Processing' ); ?></span>
                    <span class="kv-sync-card__value kv-color-yellow" id="kv-queue-processing">–</span>
                </div>
                <div class="kv-sync-card">
                    <span class="kv-sync-card__label"><?php esc_html_e( 'Done' ); ?></span>
                    <span class="kv-sync-card__value kv-color-green" id="kv-queue-done">–</span>
                </div>
                <div class="kv-sync-card">
                    <span class="kv-sync-card__label"><?php esc_html_e( 'Failed' ); ?></span>
                    <span class="kv-sync-card__value kv-color-red" id="kv-queue-failed">–</span>
                </div>
            </div>

            <p class="kv-sync-panel__sub">
                <?php esc_html_e( 'To keep this draining automatically, add a real server cron job (not WordPress\'s own pseudo-cron) that hits this URL every minute:', 'kv' ); ?>
            </p>
            <p>
                <code id="kv-queue-cron-url" style="user-select:all;word-break:break-all;">
                    <?php echo esc_html( rest_url( 'kv/v1/process-image-queue' ) . '?secret=' . get_option( 'kv_image_queue_cron_secret' ) ); ?>
                </code>
            </p>
            <p class="kv-sync-panel__sub">
                <?php esc_html_e( 'Example cron command (every minute):', 'kv' ); ?>
                <code>curl -s "<?php echo esc_html( rest_url( 'kv/v1/process-image-queue' ) . '?secret=' . get_option( 'kv_image_queue_cron_secret' ) ); ?>" &gt;/dev/null 2&gt;&amp;1</code>
            </p>

            <div class="kv-sync-actions">
                <button type="button" class="button button-secondary" id="kv-queue-process-now">
                    <?php esc_html_e( 'Process Now', 'kv' ); ?>
                </button>
            </div>
            <p class="kv-sync-status" id="kv-queue-status"></p>
        </section>

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
                    <tbody id="kv-sync-logs-tbody">
                        <?php if ( empty( $sync_logs ) ) : ?>
                            <tr class="kv-sync-logs-table__empty-row">
                                <td colspan="5" class="kv-sync-logs-table__empty">
                                    <?php esc_html_e( 'No sync logs yet. Run a sync to generate activity here.' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
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
                                $error_text = (string) ( $log['error'] ?? '' );
                                $split      = kv_sync_split_log_error( $error_text );
                                $first_line = $split['first'];
                                $more_lines = $split['count'] - 1;
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
                                    <td class="kv-sync-logs-table__notes<?php echo ( $first_line !== '' ) ? ' kv-sync-logs-table__notes--link' : ''; ?>"<?php echo ( $first_line !== '' ) ? ' data-error="' . esc_attr( $error_text ) . '"' : ''; ?>>
                                        <?php if ( $first_line !== '' ) : ?>
                                            <?php echo esc_html( $first_line ); ?>
                                            <?php if ( $more_lines > 0 ) : ?>
                                                <span class="kv-sync-logs-table__more">+<?php echo esc_html( $more_lines ); ?> more</span>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <?php esc_html_e( '–' ); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ── Full-log message popup ── -->
        <div class="kv-sync-modal" id="kv-sync-modal" hidden>
            <div class="kv-sync-modal__overlay" data-kv-modal-close></div>
            <div class="kv-sync-modal__box" role="dialog" aria-modal="true" aria-labelledby="kv-sync-modal-title">
                <button type="button" class="kv-sync-modal__close" data-kv-modal-close aria-label="<?php esc_attr_e( 'Close' ); ?>">&times;</button>
                <h2 class="kv-sync-modal__title" id="kv-sync-modal-title"></h2>
                <p class="kv-sync-modal__meta" id="kv-sync-modal-meta"></p>
                <pre class="kv-sync-modal__body" id="kv-sync-modal-body"></pre>
            </div>
        </div>
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
        kv_sync_log_exception( 'kv_ajax_sync_start error', $e );
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
        kv_sync_log_exception( 'kv_ajax_sync_resume error', $e );
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
        kv_sync_log_exception( 'kv_ajax_sync_stop error', $e );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
    }
}

/* ──────────────────────────────────────────────
 * 4d. AJAX: poll the live sync log (rendered every few seconds)
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_log_poll', 'kv_ajax_sync_log_poll' );
function kv_ajax_sync_log_poll() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        check_ajax_referer( 'kv_sync_nonce', 'nonce' );

        $logs = get_option( 'kv_sync_logs', [] );
        if ( ! is_array( $logs ) ) {
            $logs = [];
        }

        $logs = array_values( array_filter( $logs, static function ( $log ) {
            $status = strtolower( (string) ( $log['status'] ?? '' ) );
            return in_array( $status, [ 'success', 'failed', 'processing' ], true );
        } ) );
        $logs = array_reverse( $logs );

        /* Keep the payload small — send only what the table needs. */
        $entries = array_map( static function ( $log ) {
            return [
                'timestamp'     => (string) ( $log['timestamp'] ?? '–' ),
                'property_id'   => (string) ( $log['property_id'] ?? '–' ),
                'property_name' => (string) ( $log['property_name'] ?? '–' ),
                'status'        => strtolower( (string) ( $log['status'] ?? 'unknown' ) ),
                'error'         => (string) ( $log['error'] ?? '' ),
            ];
        }, array_slice( $logs, 0, 250 ) );

        wp_send_json_success( [
            'entries'      => $entries,
            'in_progress'  => (bool) get_option( 'kv_sync_in_progress', false ),
            'failed_count' => count( kv_sync_get_failed_pages() ),
            'page'         => intval( get_option( 'hz_page', 1 ) ),
            'total_pages'  => intval( get_option( 'hz_total_pages', 1 ) ),
        ] );
    } catch ( Throwable $e ) {
        kv_sync_log_exception( 'kv_ajax_sync_log_poll error', $e );
        wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
    }
}

/* ──────────────────────────────────────────────
 * 4e. AJAX: log a client-observed failure (504 / timeout / network) that the
 * server-side request never lived long enough to log itself — e.g. Cloudflare
 * returning its own 504 page before kv_ajax_sync_chunk ever finishes running.
 * Best-effort: if the browser is genuinely offline this call will also fail
 * to reach the server, which is an inherent limit, not a bug.
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_log_client_error', 'kv_ajax_sync_log_client_error' );
function kv_ajax_sync_log_client_error() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        check_ajax_referer( 'kv_sync_nonce', 'nonce' );

        $error_type = sanitize_text_field( wp_unslash( $_POST['error_type'] ?? 'other' ) );
        $message    = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
        $phase      = sanitize_text_field( wp_unslash( $_POST['phase'] ?? '' ) );
        $page       = intval( $_POST['page'] ?? 0 );

        if ( function_exists( 'kv_sync_log_entry' ) ) {
            kv_sync_log_entry( [
                'property_id'   => 'page-' . $page,
                'property_name' => 'Client-side ' . strtoupper( $error_type ) . ( $phase ? ' (' . $phase . ' phase)' : '' ),
                'status'        => 'failed',
                'error'         => sprintf(
                    'Browser received a %s error on this request and is retrying in 5 minutes. %s',
                    $error_type,
                    $message ? '(' . $message . ')' : ''
                ),
                'timestamp'     => current_time( 'Y-m-d H:i:s' ),
            ] );
        }

        wp_send_json_success();
    } catch ( Throwable $e ) {
        kv_sync_log_exception( 'kv_ajax_sync_log_client_error error', $e );
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

        // Must exceed kvSync.timeoutMs (client AJAX timeout) with real margin — otherwise
        // a client retry after its own timeout can arrive just as this lock expires and
        // slip through as a duplicate attempt instead of being told "still busy".
        set_transient( $lock_key, $run_id, 20 * MINUTE_IN_SECONDS );
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
                    'error_type'   => $processed->get_error_data()['error_type'] ?? 'other',
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
            $catchup_avg = kv_sync_get_avg_property_seconds();
            wp_send_json_success( array_merge( $processed, [
                'done'         => $done,
                'phase'        => 'catchup',
                'page'         => $total_pages,
                'total_pages'  => $total_pages,
                'catchup_page' => $catchup_page,
                'failed_count' => count( $failed_left ),
                'skipped'      => false,
                'partial'      => false,
                'eta_seconds'  => ( $done || $catchup_avg === null ) ? 0 : ( $catchup_avg * count( $failed_left ) * $chunk_size ),
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
                'error_type'   => $processed->get_error_data()['error_type'] ?? 'other',
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
            'eta_seconds'  => $done ? 0 : kv_sync_estimate_remaining_seconds( $next_page, $total_pages, $chunk_size ),
        ] ) );
    } catch ( Throwable $e ) {
        if ( $got_lock ) {
            delete_transient( $lock_key );
        }
        kv_sync_log_exception( 'kv_ajax_sync_chunk error', $e );
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
        $chunk_size  = max( 1, min( 5, intval( get_option( 'kv_sync_chunk_size', 3 ) ) ) );

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

            $skip_catchup_avg = kv_sync_get_avg_property_seconds();
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
                'eta_seconds'  => ( $done || $skip_catchup_avg === null ) ? 0 : ( $skip_catchup_avg * count( $failed_left ) * $chunk_size ),
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
            'eta_seconds'  => $done ? 0 : kv_sync_estimate_remaining_seconds( $next_page, $total_pages, $chunk_size ),
        ] );
    } catch ( Throwable $e ) {
        kv_sync_log_exception( 'kv_ajax_sync_skip error', $e );
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

/* ──────────────────────────────────────────────
 * Timing model — how long a property takes to sync scales with its room
 * and image count. We record (rooms, images, seconds) samples as each
 * property finishes, fit a simple linear model, and use it to flag
 * unusually slow properties and estimate how long the next one will take.
 * ────────────────────────────────────────────── */

function kv_sync_record_timing_sample( $rooms, $images, $seconds ) {
    $rooms   = max( 0, intval( $rooms ) );
    $images  = max( 0, intval( $images ) );
    $seconds = max( 0.0, (float) $seconds );

    $samples = get_option( 'kv_sync_timing_samples', [] );
    if ( ! is_array( $samples ) ) {
        $samples = [];
    }
    $samples[] = [ 'rooms' => $rooms, 'images' => $images, 'seconds' => $seconds ];

    /* Bounded ring buffer — keep the model responsive to current server conditions */
    if ( count( $samples ) > 200 ) {
        $samples = array_slice( $samples, -200 );
    }

    update_option( 'kv_sync_timing_samples', $samples, false );
}

/**
 * Fit `seconds = a*images + b*rooms + c` via least squares over recent samples.
 * Falls back to a flat per-unit estimate when there isn't enough data yet,
 * or the sample set is too degenerate (e.g. every property has 0 rooms) to solve.
 *
 * @return array{a: float, b: float, c: float, samples: int}
 */
function kv_sync_get_timing_model() {
    $samples = get_option( 'kv_sync_timing_samples', [] );
    if ( ! is_array( $samples ) ) {
        $samples = [];
    }

    $n = count( $samples );
    $fallback = [ 'a' => 2.0, 'b' => 6.0, 'c' => 0.0, 'samples' => $n ];

    if ( $n < 5 ) {
        return $fallback;
    }

    $sum_xx = 0.0; $sum_xy = 0.0; $sum_xz = 0.0;
    $sum_yy = 0.0; $sum_yz = 0.0;
    $sum_x  = 0.0; $sum_y  = 0.0; $sum_z  = 0.0;

    foreach ( $samples as $s ) {
        $x = (float) ( $s['images'] ?? 0 );
        $y = (float) ( $s['rooms'] ?? 0 );
        $z = (float) ( $s['seconds'] ?? 0 );

        $sum_xx += $x * $x;
        $sum_xy += $x * $y;
        $sum_xz += $x * $z;
        $sum_yy += $y * $y;
        $sum_yz += $y * $z;
        $sum_x  += $x;
        $sum_y  += $y;
        $sum_z  += $z;
    }

    /* Normal equations for least-squares fit of seconds = a*images + b*rooms + c:
     * [ sum_xx  sum_xy  sum_x ] [a]   [sum_xz]
     * [ sum_xy  sum_yy  sum_y ] [b] = [sum_yz]
     * [ sum_x   sum_y   n     ] [c]   [sum_z ]
     */
    $solved = kv_sync_solve_3x3( [
        [ $sum_xx, $sum_xy, $sum_x, $sum_xz ],
        [ $sum_xy, $sum_yy, $sum_y, $sum_yz ],
        [ $sum_x,  $sum_y,  $n,     $sum_z  ],
    ] );

    if ( $solved === null ) {
        return $fallback;
    }

    return [
        'a'       => max( 0.0, $solved[0] ),
        'b'       => max( 0.0, $solved[1] ),
        'c'       => max( 0.0, $solved[2] ),
        'samples' => $n,
    ];
}

/**
 * Gaussian elimination with partial pivoting for a 3x4 augmented matrix.
 * Returns [x, y, z] or null if the system is singular/degenerate.
 */
function kv_sync_solve_3x3( $m ) {
    for ( $col = 0; $col < 3; $col++ ) {
        $pivot_row = $col;
        for ( $row = $col + 1; $row < 3; $row++ ) {
            if ( abs( $m[ $row ][ $col ] ) > abs( $m[ $pivot_row ][ $col ] ) ) {
                $pivot_row = $row;
            }
        }
        if ( abs( $m[ $pivot_row ][ $col ] ) < 1e-9 ) {
            return null;
        }
        if ( $pivot_row !== $col ) {
            $tmp             = $m[ $col ];
            $m[ $col ]       = $m[ $pivot_row ];
            $m[ $pivot_row ] = $tmp;
        }

        for ( $row = 0; $row < 3; $row++ ) {
            if ( $row === $col ) {
                continue;
            }
            $factor = $m[ $row ][ $col ] / $m[ $col ][ $col ];
            for ( $c2 = $col; $c2 < 4; $c2++ ) {
                $m[ $row ][ $c2 ] -= $factor * $m[ $col ][ $c2 ];
            }
        }
    }

    return [
        $m[0][3] / $m[0][0],
        $m[1][3] / $m[1][1],
        $m[2][3] / $m[2][2],
    ];
}

function kv_sync_predict_property_seconds( $rooms, $images ) {
    $model = kv_sync_get_timing_model();
    return ( $model['a'] * max( 0, intval( $images ) ) ) + ( $model['b'] * max( 0, intval( $rooms ) ) ) + $model['c'];
}

/**
 * Rough "time remaining" for the rest of the sync. We don't know the room/image
 * count of properties we haven't fetched yet, so this uses the plain average
 * seconds-per-property from recent samples rather than the regression model.
 */
function kv_sync_get_avg_property_seconds() {
    $samples = get_option( 'kv_sync_timing_samples', [] );
    if ( ! is_array( $samples ) || empty( $samples ) ) {
        return null;
    }

    $recent = array_slice( $samples, -50 );
    $sum    = 0.0;
    foreach ( $recent as $s ) {
        $sum += (float) ( $s['seconds'] ?? 0 );
    }

    return $sum / count( $recent );
}

function kv_sync_estimate_remaining_seconds( $page_num, $total_pages, $chunk_size ) {
    $avg_seconds = kv_sync_get_avg_property_seconds();
    if ( $avg_seconds === null ) {
        return null;
    }

    $pages_left = max( 0, intval( $total_pages ) - intval( $page_num ) + 1 );
    $properties_left = $pages_left * max( 1, intval( $chunk_size ) );

    return $avg_seconds * $properties_left;
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
        $fetch_error = function_exists( 'kv_sync_get_last_fetch_error' )
            ? kv_sync_get_last_fetch_error()
            : [ 'type' => 'other', 'message' => 'Check API credentials / rate limits.' ];

        $message = 'Failed to fetch properties from API for page ' . $page_num . ': ' . $fetch_error['message'];

        if ( function_exists( 'kv_sync_log_entry' ) ) {
            kv_sync_log_entry( [
                'property_id'   => 'page-' . $page_num,
                'property_name' => 'Page ' . $page_num,
                'status'        => 'failed',
                'error'         => '[' . strtoupper( $fetch_error['type'] ) . '] ' . $message,
                'timestamp'     => current_time( 'Y-m-d H:i:s' ),
            ] );
        }

        return new WP_Error( 'api_fetch', $message, [ 'error_type' => $fetch_error['type'] ] );
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
            $pid   = trim( (string) ( $property['id'] ?? 'unknown' ) );
            $pname = trim( (string) ( $property['client_property_name'] ?? $property['name'] ?? 'unknown' ) );

            $room_count  = is_array( $property['rooms'] ?? null ) ? count( $property['rooms'] ) : 0;
            $image_count = is_array( $property['images'] ?? null ) ? count( $property['images'] ) : 0;
            foreach ( ( is_array( $property['rooms'] ?? null ) ? $property['rooms'] : [] ) as $room ) {
                $image_count += is_array( $room['images'] ?? null ) ? count( $room['images'] ) : 0;
            }

            if ( function_exists( 'kv_sync_write_log' ) ) {
                $predicted_start = function_exists( 'kv_sync_predict_property_seconds' )
                    ? kv_sync_predict_property_seconds( $room_count, $image_count )
                    : null;
                kv_sync_write_log( sprintf(
                    'STARTED property %s (%s) on page %d — %d rooms, %d images%s',
                    $pid, $pname, $page_num, $room_count, $image_count,
                    $predicted_start !== null ? sprintf( ' — expected ~%.1fs', $predicted_start ) : ''
                ) );
            }

            $started_at = microtime( true );

            try {
                sq_mapping_properties( [ $property ] );
            } catch ( Throwable $map_error ) {
                kv_sync_log_exception(
                    sprintf( 'kv_sync_process_page mapping error on page %d property %s (%s)', $page_num, $pid, $pname ),
                    $map_error
                );
                if ( function_exists( 'kv_sync_log_entry' ) ) {
                    kv_sync_log_entry( [
                        'property_id'   => $pid,
                        'property_name' => $pname,
                        'status'        => 'failed',
                        'error'         => $map_error->getMessage() . ' (full trace in kv-sync-debug.log)',
                        'timestamp'     => current_time( 'Y-m-d H:i:s' ),
                    ] );
                }
                continue;
            }

            $elapsed = microtime( true ) - $started_at;

            if ( function_exists( 'kv_sync_record_timing_sample' ) ) {
                kv_sync_record_timing_sample( $room_count, $image_count, $elapsed );
            }

            if ( function_exists( 'kv_sync_predict_property_seconds' ) && function_exists( 'kv_sync_log_entry' ) ) {
                $predicted = kv_sync_predict_property_seconds( $room_count, $image_count );
                $threshold = max( 20.0, $predicted * 1.75 );
                $is_slow   = $elapsed > $threshold;

                kv_sync_log_entry( [
                    'property_id'   => $pid,
                    'property_name' => $pname,
                    'status'        => 'success',
                    'error'         => sprintf(
                        '%stook %.1fs to sync (%d rooms, %d images) — expected ~%.1fs.%s',
                        $is_slow ? 'SLOW: ' : '',
                        $elapsed,
                        $room_count,
                        $image_count,
                        $predicted,
                        $is_slow ? ' Investigate if this recurs.' : ''
                    ),
                    'timestamp'     => current_time( 'Y-m-d H:i:s' ),
                ] );
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
        kv_sync_log_exception( 'kv_sync_finalize error', $e );
        update_option( 'kv_sync_in_progress', false, false );
    }
}
