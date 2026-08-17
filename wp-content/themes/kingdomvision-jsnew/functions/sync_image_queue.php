<?php
/**
 * Background image queue for property/room sync.
 *
 * Problem: sync used to call download_url() synchronously for each
 * property's + room's featured image. A large property (e.g. 22 rooms) could
 * need up to 23 such downloads in one request, easily exceeding Cloudflare's
 * ~100s edge timeout no matter what PHP/JS-side timeouts are set to.
 *
 * Fix: hz_add_img_from_booking_sys() (sync_functions.php) now only does a
 * fast local dedupe check and, if the image isn't already on this site,
 * enqueues it here instead of downloading it inline. A real server cron hits
 * the REST endpoint below every minute, which drains a small batch of the
 * queue — completely decoupled from the sync request/response cycle.
 */

define( 'KV_IMAGE_QUEUE_DB_VERSION', '1.0' );

function kv_image_queue_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'kv_image_queue';
}

/**
 * Self-healing table creation — runs on admin_init behind a cheap option
 * version check rather than relying on a theme-activation hook (the theme
 * may already be active on existing sites when this file first ships).
 */
add_action( 'init', 'kv_image_queue_maybe_install' );
function kv_image_queue_maybe_install() {
    if ( get_option( 'kv_image_queue_db_version' ) === KV_IMAGE_QUEUE_DB_VERSION ) {
        return;
    }

    global $wpdb;
    $table           = kv_image_queue_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        post_type VARCHAR(20) NOT NULL,
        context VARCHAR(20) NOT NULL DEFAULT 'featured',
        image_url TEXT NOT NULL,
        booking_image_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY post_context (post_id, context),
        KEY status_id (status, id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    if ( ! get_option( 'kv_image_queue_cron_secret' ) ) {
        update_option( 'kv_image_queue_cron_secret', wp_generate_password( 32, false ) );
    }

    update_option( 'kv_image_queue_db_version', KV_IMAGE_QUEUE_DB_VERSION );
}

/**
 * Queue an image for background download. Upserts by (post_id, context) so
 * re-syncing the same property just refreshes the URL and resets it to
 * pending rather than piling up duplicate rows.
 *
 * @param int    $post_id
 * @param string $post_type         'accommodation' or 'room'
 * @param string $context           'featured' (only context used today)
 * @param string $url               Remote image URL
 * @param int    $booking_image_id  API images[].id, for dedupe/filename
 */
function kv_image_queue_enqueue( $post_id, $post_type, $context, $url, $booking_image_id = 0 ) {
    global $wpdb;

    $post_id = absint( $post_id );
    $url     = esc_url_raw( trim( (string) $url ) );

    if ( $post_id < 1 || $url === '' ) {
        return;
    }

    $table = kv_image_queue_table_name();
    $now   = current_time( 'mysql' );

    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$table} (post_id, post_type, context, image_url, booking_image_id, status, attempts, created_at, updated_at)
         VALUES (%d, %s, %s, %s, %d, 'pending', 0, %s, %s)
         ON DUPLICATE KEY UPDATE
            image_url = VALUES(image_url),
            booking_image_id = VALUES(booking_image_id),
            status = 'pending',
            attempts = 0,
            last_error = NULL,
            updated_at = VALUES(updated_at)",
        $post_id, (string) $post_type, (string) $context, $url, absint( $booking_image_id ), $now, $now
    ) );

    if ( function_exists( 'cf_log' ) ) {
        cf_log(
            'Image Queue Enqueued post_id=' . $post_id . ' type=' . $post_type . ' context=' . $context . ' url=' . $url,
            'kv_image_queue', 'txt', false, true
        );
    }
}

/**
 * Process a batch of queued images. Called by the REST cron endpoint and by
 * the admin "Process now" button. Self-limits to $time_budget_sec so a run
 * can never itself become a long-hanging request.
 */
function kv_image_queue_process_batch( $limit = 10, $time_budget_sec = 45 ) {
    global $wpdb;
    $table = kv_image_queue_table_name();
    $start = microtime( true );

    $result = [ 'processed' => 0, 'succeeded' => 0, 'failed' => 0, 'requeued' => 0 ];

    $max_attempts = 5;
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$table} WHERE status IN ('pending','processing') OR (status = 'failed' AND attempts < %d) ORDER BY id ASC LIMIT %d",
        $max_attempts, $limit
    ) );

    if ( empty( $ids ) ) {
        return $result;
    }

    $id_list = implode( ',', array_map( 'intval', $ids ) );
    $wpdb->query( "UPDATE {$table} SET status = 'processing', updated_at = '" . esc_sql( current_time( 'mysql' ) ) . "' WHERE id IN ({$id_list})" );

    $rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE id IN ({$id_list})" );

    foreach ( $rows as $row ) {
        if ( ( microtime( true ) - $start ) > $time_budget_sec ) {
            $wpdb->update( $table, [ 'status' => 'pending' ], [ 'id' => $row->id ] );
            $result['requeued']++;
            continue;
        }

        $result['processed']++;

        $attachment_id = kv_sideload_or_find_image( $row->image_url, $row->post_id, $row->booking_image_id );

        if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
            if ( $row->context === 'featured' ) {
                set_post_thumbnail( $row->post_id, $attachment_id );
                delete_post_meta( $row->post_id, '_kv_pending_featured_image' );
            }

            $wpdb->update( $table, [
                'status'     => 'done',
                'last_error' => null,
                'updated_at' => current_time( 'mysql' ),
            ], [ 'id' => $row->id ] );

            $result['succeeded']++;

            if ( function_exists( 'cf_log' ) ) {
                cf_log(
                    'Image Queue Success post_id=' . $row->post_id . ' attachment_id=' . (int) $attachment_id . ' url=' . $row->image_url,
                    'kv_image_queue', 'txt', false, true
                );
            }
        } else {
            $err      = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'sideload returned empty';
            $attempts = (int) $row->attempts + 1;

            $wpdb->update( $table, [
                'status'     => $attempts >= $max_attempts ? 'failed' : 'pending',
                'attempts'   => $attempts,
                'last_error' => $err,
                'updated_at' => current_time( 'mysql' ),
            ], [ 'id' => $row->id ] );

            $result['failed']++;

            if ( function_exists( 'cf_log' ) ) {
                cf_log(
                    'Image Queue Error post_id=' . $row->post_id . ' attempts=' . $attempts . ' url=' . $row->image_url . ' | ' . $err,
                    'kv_image_queue', 'txt', false, true
                );
            }
        }
    }

    return $result;
}

/**
 * REST endpoint for the real server cron job to hit, e.g.:
 *   curl -s "https://sitename.com/wp-json/kv/v1/process-image-queue?secret=XXXX"
 * every minute. Guarded by a random secret stored in wp_options (shown on the
 * sync admin page) instead of cookie/nonce auth, since this is called by an
 * external cron process with no WP session.
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'kv/v1', '/process-image-queue', [
        'methods'             => 'GET',
        'callback'            => 'kv_rest_process_image_queue',
        'permission_callback' => '__return_true',
    ] );
} );

function kv_rest_process_image_queue( WP_REST_Request $request ) {
    $secret    = (string) get_option( 'kv_image_queue_cron_secret' );
    $given     = (string) $request->get_param( 'secret' );

    if ( empty( $secret ) || ! hash_equals( $secret, $given ) ) {
        return new WP_REST_Response( [ 'error' => 'forbidden' ], 403 );
    }

    $result           = kv_image_queue_process_batch( 10, 45 );
    $result['counts'] = kv_image_queue_get_status_counts();

    return new WP_REST_Response( $result, 200 );
}

/**
 * Status counts for the admin panel.
 */
function kv_image_queue_get_status_counts() {
    global $wpdb;
    $table = kv_image_queue_table_name();

    $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status", ARRAY_A );

    $counts = [ 'pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0 ];
    foreach ( (array) $rows as $row ) {
        $status = $row['status'];
        if ( isset( $counts[ $status ] ) ) {
            $counts[ $status ] = (int) $row['cnt'];
        }
    }

    return $counts;
}

add_action( 'wp_ajax_kv_image_queue_status', 'kv_ajax_image_queue_status' );
function kv_ajax_image_queue_status() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
    }

    wp_send_json_success( [
        'counts'      => kv_image_queue_get_status_counts(),
        'cron_url'    => rest_url( 'kv/v1/process-image-queue' ) . '?secret=' . rawurlencode( (string) get_option( 'kv_image_queue_cron_secret' ) ),
    ] );
}

add_action( 'wp_ajax_kv_image_queue_process_now', 'kv_ajax_image_queue_process_now' );
function kv_ajax_image_queue_process_now() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
    }

    check_ajax_referer( 'kv_sync_nonce', 'nonce' );

    $result = kv_image_queue_process_batch( 10, 45 );

    wp_send_json_success( [
        'result' => $result,
        'counts' => kv_image_queue_get_status_counts(),
    ] );
}
