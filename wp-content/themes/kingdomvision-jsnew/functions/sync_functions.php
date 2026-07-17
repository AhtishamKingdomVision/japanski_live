<?php

/**

 * Booking System Sync Functions

 * 

 * All cron jobs, API helpers, image import, REST sync endpoints,

 * and debug triggers for syncing data from the Booking System / RoomBoss APIs.

 */



/**

 * Build HTTP request arguments for Booking System API calls

 * Constructs standard headers, timeout, and authorization configuration

 * 

 * @return array HTTP request arguments with method, headers, timeout, and cookies

 */

function booking_sys_api_args()

{

    try {

        // ✅ STEP 1: Validate authorization token is defined

        if (!defined('KV_BS_authToken') || empty(KV_BS_authToken)) {

            error_log('KV_BS_authToken is not defined or empty');

            return [];

        }



        $token = trim(KV_BS_authToken);

        if (empty($token)) {

            error_log('KV_BS_authToken is empty after trimming');

            return [];

        }



        // ✅ STEP 2: Build request configuration

        $args = [

            'method'      => 'POST',

            'timeout'     => 120,

            'redirection' => 5,

            'httpversion' => '1.1',

            'headers'     => [

                'Authorization' => 'Bearer ' . $token,

            ],

            'body'        => [],

            'cookies'     => [],

        ];



        // ✅ STEP 3: Return configured arguments

        return $args;



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error in booking_sys_api_args: ' . $e->getMessage());

        return [];

    }

}



/**

 * Find a post by its exact title

 * @param string $title     Exact post title to search for

 * @param string $post_type Post type to search within

 * @return WP_Post|null First matching post or null if not found

 */

function get_post_by_title($title, $post_type = 'post')

{

    if (empty($title)) {

        return null;

    }



    $posts = get_posts([

        'post_type'      => sanitize_text_field($post_type),

        'title'          => sanitize_text_field($title),

        'post_status'    => 'any',

        'posts_per_page' => 1,

    ]);



    return !empty($posts) ? $posts[0] : null;

}



/**

 * Extract and sort bedroom counts from room data

 *

 * @param array $rooms Array of room data with 'no_of_bedrooms' key

 * @return array Sorted array of bedroom counts

 */

function get_hotel_num_bedrooms($rooms)

{

    if (empty($rooms) || !is_array($rooms)) {

        return [];

    }



    $bedrooms = wp_list_pluck($rooms, 'room_per_unit');

    $bedrooms = array_map('intval', array_filter($bedrooms));

    sort($bedrooms, SORT_NUMERIC);

    return $bedrooms;

}



/**

 * Look up accommodation category term ID by booking system resort ID

 *

 * @param string|int $resort_id Booking system resort ID

 * @return int|null Term ID if found, null otherwise

 */

function hz_get_term_id_by_resort_id($resort_id)

{

    if (empty($resort_id)) {

        return null;

    }



    $terms = get_terms([

        'taxonomy'   => 'accommodation-cat',

        'hide_empty' => false,

        'number'     => 1,

        'meta_query' => [

            [

                'key'     => 'bs_resort_id',

                'value'   => sanitize_text_field($resort_id),

                'compare' => '=',

            ],

        ],

    ]);



    return (!is_wp_error($terms) && !empty($terms)) ? intval($terms[0]->term_id) : null;

}



/**

 * Insert or update a term in a custom taxonomy and return term ID.

 *

 * If $parent_id is provided, the function creates/updates the term

 * as a child of the given parent.

 *

 * @param string $taxonomy  Taxonomy slug.

 * @param string $term_name Human-readable term name.

 * @param int    $parent_id Optional parent term ID.

 * @param string $slug      Optional explicit slug.

 *

 * @return int|null Term ID on success, null on failure.

 */

function kv_upsert_taxonomy_term($taxonomy, $term_name, $parent_id = 0, $slug = '')

{

    $taxonomy = trim((string) $taxonomy);

    $term_name = trim(wp_strip_all_tags((string) $term_name));

    $parent_id = absint($parent_id);

    $slug = trim((string) $slug);



    if ($taxonomy === '' || $term_name === '' || !taxonomy_exists($taxonomy)) {

        return null;

    }



    if ($parent_id > 0) {

        $parent_term = term_exists($parent_id, $taxonomy);

        if (!$parent_term) {

            return null;

        }

    }



    $slug = ($slug !== '') ? sanitize_title($slug) : sanitize_title($term_name);

    $parent_lookup = $parent_id > 0 ? $parent_id : null;



    $existing = term_exists($slug, $taxonomy, $parent_lookup);

    if (!$existing) {

        $existing = term_exists($term_name, $taxonomy, $parent_lookup);

    }



    if ($existing) {

        $term_id = is_array($existing) ? intval($existing['term_id']) : intval($existing);

        if ($term_id < 1) {

            return null;

        }



        // Keep hierarchy consistent when parent is provided.

        if ($parent_id > 0) {

            $term_obj = get_term($term_id, $taxonomy);

            if (!is_wp_error($term_obj) && $term_obj && intval($term_obj->parent) !== $parent_id) {

                $updated = wp_update_term($term_id, $taxonomy, ['parent' => $parent_id]);

                if (!is_wp_error($updated) && !empty($updated['term_id'])) {

                    $term_id = intval($updated['term_id']);

                }

            }

        }



        return $term_id;

    }



    $insert_args = [

        'slug' => $slug,

    ];



    if ($parent_id > 0) {

        $insert_args['parent'] = $parent_id;

    }



    $inserted = wp_insert_term($term_name, $taxonomy, $insert_args);

    if (is_wp_error($inserted) || empty($inserted['term_id'])) {

        return null;

    }



    return intval($inserted['term_id']);

}



/**

 * Log a sync entry for a property with status, error details, and timestamp.

 *

 * @param array $entry Associative array with keys: property_id, property_name, status, error, timestamp

 * @return void

 */

function kv_sync_log_entry( array $entry ) {

    $defaults = [

        'property_id'   => 'unknown',

        'property_name' => 'unknown',

        'status'        => 'unknown',

        'error'         => '',

        'timestamp'     => current_time( 'Y-m-d H:i:s' ),

    ];



    $log = array_merge( $defaults, $entry );

    // Skip "processing" rows — they duplicate every Success/Failed entry in the admin table
    if ( strtolower( (string) ( $log['status'] ?? '' ) ) === 'processing' ) {
        return;
    }

    // Store in a transient-based ring buffer (last 500 entries)

    $logs   = get_option( 'kv_sync_logs', [] );

    $logs[] = $log;



    if ( count( $logs ) > 500 ) {

        $logs = array_slice( $logs, -500 );

    }



    update_option( 'kv_sync_logs', $logs, false );



    // Also write to error_log for server-side traceability

    $line = sprintf(

        '[SYNC] %s | Property %s (%s) | Status: %s | Error: %s',

        $log['timestamp'],

        $log['property_id'],

        $log['property_name'],

        strtoupper( $log['status'] ),

        $log['error'] ?: 'none'

    );

    error_log( $line );

}

function __media_sideload_image( $file, $post_id = 0, $desc = null, $return_type = 'html' ) {

    if ( ! empty( $file ) ) {



        $segments = explode('/', $file);

        $numSegments = count($segments);

        $matches = $segments[$numSegments - 1];



        $file_array         = array();

        // $file_array['name'] = wp_basename( $matches[0] );

        $file_array['name'] = wp_basename( $matches . '.jpg' );



        // Download file to temp location.

        $file_array['tmp_name'] = download_url( $file );



        // If error storing temporarily, return the error.

        if ( is_wp_error( $file_array['tmp_name'] ) ) {

            return $file_array['tmp_name'];

        }



        // Do the validation and storage stuff.

        $id = media_handle_sideload( $file_array, $post_id, $desc );



        // If error storing permanently, unlink.

        if ( is_wp_error( $id ) ) {

            @unlink( $file_array['tmp_name'] );

            return $id;

        }



        // Store the original attachment source in meta.

        add_post_meta( $id, '_source_url', $file );



        // If attachment ID was requested, return it.

        if ( 'id' === $return_type ) {

            return $id;

        }



    }

}



/**

 * Find an existing media attachment by filename (name + extension).

 * Matches against the _wp_attached_file meta to avoid re-uploading duplicates.

 *

 * @param string $filename Filename including extension, e.g. "photo.jpg"

 * @return int|null Attachment post ID if found, null otherwise

 */

function kv_find_attachment_by_filename($filename) {

    if (empty($filename)) {

        return null;

    }



    $results = get_posts([

        'post_type'      => 'attachment',

        'post_status'    => 'inherit',

        'posts_per_page' => 1,

        'fields'         => 'ids',

        'meta_query'     => [

            [

                'key'     => '_wp_attached_file',

                'value'   => $filename,

                'compare' => 'LIKE',

            ],

        ],

    ]);



    return !empty($results) ? intval($results[0]) : null;

}



/**

 * Return existing attachment ID matched by filename, or sideload and return new ID.

 *

 * @param string $url     Image URL to sideload

 * @param int    $post_id Parent post ID for the attachment

 * @return int|null Attachment ID on success, null on failure

 */

function kv_sideload_or_find_image($url, $post_id) {

    $path     = parse_url($url, PHP_URL_PATH);

    $filename = $path ? basename($path) : '';



    if (empty($filename)) {

        return null;

    }



    $existing_id = kv_find_attachment_by_filename($filename);

    if ($existing_id) {

        return $existing_id;

    }



    $ext = strtolower( pathinfo($filename, PATHINFO_EXTENSION) );

    if( empty($ext) ) {

        $attachment_id = __media_sideload_image($url, $post_id, null, 'id');

    }

    else {

        $attachment_id = media_sideload_image($url, $post_id, null, 'id');

    }



    return is_wp_error($attachment_id) ? null : intval($attachment_id);

}



/**

 * Register admin meta boxes to display pending (non-sideloaded) image URLs as a gallery.

 */

add_action('add_meta_boxes', 'kv_register_pending_images_meta_boxes');

function kv_register_pending_images_meta_boxes() {

    add_meta_box(

        'kv_acco_pending_images',

        'Additional Images (from Booking System)',

        'kv_render_acco_pending_images_meta_box',

        'accommodation',

        'normal',

        'low'

    );

    add_meta_box(

        'kv_room_pending_images',

        'Additional Images (from Booking System)',

        'kv_render_room_pending_images_meta_box',

        'japan_rooms',

        'normal',

        'low'

    );

}



// Helper function to decode JSON gallery and return as array (if needed elsewhere)

function kv_get_meta_images_gallery($post_id, $meta_key) {

    $json = get_post_meta($post_id, $meta_key, true);

    return json_decode((string) $json, true);

}



function kv_render_acco_pending_images_meta_box($post) {

    $json = kv_get_meta_images_gallery($post->ID, 'acco_pending_images');

    kv_render_pending_images_gallery($json);

}



function kv_render_room_pending_images_meta_box($post) {

    $json = kv_get_meta_images_gallery($post->ID, 'room_pending_images');

    kv_render_pending_images_gallery($json);

}



/**

 * Render a simple image gallery from a JSON-encoded array of image URLs.

 *

 * @param string $json JSON-encoded array of image URLs

 */

function kv_render_pending_images_gallery($urls) {

    // $urls = json_decode((string) $json, true);



    if (empty($urls) || !is_array($urls)) {

        echo '<p>' . esc_html__('No additional images saved.') . '</p>';

        return;

    }



    echo '<div style="display:flex;flex-wrap:wrap;gap:8px;padding:8px 0;">';

    foreach ($urls as $url) {

        $url = esc_url((string) $url);

        if (empty($url)) {

            continue;

        }

        echo '<a href="' . $url . '" target="_blank" rel="noopener">'

            . '<img src="' . $url . '" style="width:120px;height:90px;object-fit:cover;border:1px solid #ddd;border-radius:3px;" loading="lazy" />'

            . '</a>';

    }

    echo '</div>';

}



/**

 * Import images from booking system API for accommodations or rooms.

 *

 * - Checks if an image already exists in the media library by filename before sideloading.

 * - Uploads/reuses ONLY the first valid image and sets it as the featured image.

 * - Remaining image URLs are NOT sideloaded; they are stored as a JSON array in post meta

 *   (acco_pending_images / room_pending_images) and rendered in a custom admin meta box.

 *

 * @param array    $images  Array of image data with 'url' key

 * @param int      $post_id WordPress post ID to attach images to

 * @param string   $type    'accommodation' or 'room'

 * @return string|void Success message or void on invalid input

 */

function hz_add_img_from_booking_sys($images, $post_id, $type) {

    // ✅ STEP 1: Validate inputs and load dependencies

    if (empty($images) || !is_array($images)) {

        return;

    }



    $post_id = intval($post_id);

    if ($post_id < 1) {

        return;

    }



    if (!in_array($type, ['accommodation', 'room'], true)) {

        return;

    }



    // ✅ STEP 2: Collect and validate all image URLs upfront

    $valid_urls = [];

    foreach ($images as $image) {

        $url = isset($image['url']) ? esc_url_raw(trim($image['url'])) : '';

        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {

            $valid_urls[] = $url;

        }

    }



    if (empty($valid_urls)) {

        return;

    }



    // ✅ STEP 4: Sideload or find first image → set as featured

    $first_url     = array_shift($valid_urls);

    $attachment_id = kv_sideload_or_find_image($first_url, $post_id);



    if ($type === 'accommodation') {



        if ($attachment_id) {

            set_post_thumbnail($post_id, $attachment_id);

        }



        // ✅ STEP 5: Save remaining URLs as JSON in post meta (no sideloading)

        update_post_meta(

            $post_id,

            'acco_pending_images',

            !empty($valid_urls) ? wp_json_encode(array_values($valid_urls)) : ''

        );



    } else {



        if ($attachment_id) {

            set_post_thumbnail($post_id, $attachment_id);

        }



        // ✅ STEP 5: Save remaining URLs as JSON in post meta (no sideloading)

        update_post_meta(

            $post_id,

            'room_pending_images',

            !empty($valid_urls) ? wp_json_encode(array_values($valid_urls)) : ''

        );

    }



    return 'Images successfully refreshed for accommodations and rooms';

}



/**

 * Fetch paginated list of properties from Booking System API

 * Retrieves accommodation properties with pagination metadata

 * 

 * @param int $page Page number to fetch (default: 1)

 * @param int $perPage Items per page (default: 1)

 * @return array|false Array with 'properties', 'total_pages', and 'pagination' keys, or false on error

 */

function hz_get_limited_properties($page = 1, $perPage = 1)

{

    try {

        // ✅ STEP 1: Validate and sanitize pagination parameters

        $page = intval($page);

        $perPage = intval($perPage);



        if ($page < 1) {

            $page = 1;

        }

        if ($perPage < 1) {

            $perPage = 1;

        }

        $apiUrl = add_query_arg([

            'page' => $page,

            'per_page' => $perPage,

        ], KV_BOOKING_SYSTEM_BASE . '/api/get-all-properties');



        if (isset( $_GET['is_test_cron'] ) && !empty( $_GET['is_test_cron'] ) ) {

            $property_id = $_GET['is_test_cron'];



            $apiUrl = add_query_arg([

                'propertyIds' => $property_id,

            ], KV_BOOKING_SYSTEM_BASE . '/api/get-properties-by-ids');

        }



        // ✅ STEP 3: Get API request arguments

        $args = booking_sys_api_args();



        if (empty($args)) {

            error_log('Failed to get booking system API args in hz_get_limited_properties');

            return false;

        }

        // Bulk sync pages can be heavy — allow a longer API wait than the default
        $args['timeout'] = max( intval( $args['timeout'] ?? 120 ), 180 );



        // ✅ STEP 4: Make API request

        $response = wp_remote_post($apiUrl, $args);



        // Validate response

        if (is_wp_error($response)) {

            cf_log( 'API Error: ' . $response->get_error_message(), 'err_api', 'txt', false, true );

            return false;

        }



        // ✅ STEP 5: Validate HTTP status code

        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200) {

            cf_log( 'API returned HTTP ' . $http_code, 'err_api', 'txt', false, true );

            return false;

        }



        // ✅ STEP 6: Parse JSON response

        $body = wp_remote_retrieve_body($response);

        // pre( $body, 1);

        if (empty($body)) {

            cf_log('Empty response body from API', 'err_api', 'txt', false, true);

            return false;

        }



        $result = json_decode($body, true);



        if (!is_array($result)) {

            cf_log('Invalid JSON in API response', 'err_api', 'txt', false, true);

            return false;

        }



        // ✅ STEP 7: Validate properties in response

        if (empty($result['properties']) || !is_array($result['properties'])) {

            return [

                'properties' => [],

                'total_pages' => 0,

                'pagination' => [],

            ];

        }



        // ✅ STEP 8: Extract pagination data

        $total_pages = isset($result['pagination']['last_page']) ? intval($result['pagination']['last_page']) : 1;

        $pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];



        // ✅ STEP 9: Return structured response

        return [

            'properties' => $result['properties'],

            'total_pages' => $total_pages,

            'pagination' => $pagination,

            'faq'           => $result['default_faqs'],

        ];



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error in hz_get_limited_properties: ' . $e->getMessage());

        return false;

    }

}





/**

 * Fetch available hotels from RoomBoss API with pagination support

 *

 * @param string $hotelId    Query string of hotelId params (e.g. "hotelId=123&hotelId=456")

 * @param string $checkIn    Check-in date

 * @param string $checkOut   Check-out date

 * @param int    $guests     Number of guests

 * @param int    $offset     Pagination offset

 * @param int    $limit      Number of results to return

 * @return array{status: string, response: array, number_posts: int}

 */

function hz_get_limited_available_hotels($hotelId, $checkIn, $checkOut, $guests, $offset = 0, $limit = 5)

{

    try {

        // ✅ STEP 1: Validate required parameters

        if (empty($hotelId) || empty($checkIn) || empty($checkOut)) {

            return ['status' => 'fail', 'response' => 'Missing required parameters', 'number_posts' => 0];

        }



        $guests = intval($guests);

        $offset = intval($offset);

        $limit  = max(1, intval($limit));



        // ✅ STEP 2: Build API URL safely

        $apiUrl = add_query_arg([

            'checkIn'                => sanitize_text_field($checkIn),

            'checkOut'               => sanitize_text_field($checkOut),

            'numberGuests'           => $guests,

            'excludeConditionsNotMet' => 'true',

            'rate'                   => 'ota',

        ], 'https://api.roomboss.com/extws/hotel/v1/listAvailable?' . $hotelId);



        // ✅ STEP 3: Get API args and make request

        $args = booking_sys_api_args();

        $response = wp_remote_get($apiUrl, $args);



        // ✅ STEP 4: Validate response

        if (is_wp_error($response)) {

            cf_log($response->get_error_message(), 'err_api', 'txt', false, true);

            return ['status' => 'fail', 'response' => $response->get_error_message(), 'number_posts' => 0];

        }



        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {

            cf_log('RoomBoss API returned HTTP ' . $status_code, 'err_api', 'txt', false, true);

            return ['status' => 'fail', 'response' => 'API returned HTTP ' . $status_code, 'number_posts' => 0];

        }



        // ✅ STEP 5: Parse and validate JSON response

        $responseBody = wp_remote_retrieve_body($response);

        if (empty($responseBody)) {

            return ['status' => 'fail', 'response' => 'Empty API response', 'number_posts' => 0];

        }



        $result = json_decode($responseBody, true);

        if (!is_array($result) || !isset($result['availableHotels'])) {

            cf_log('Invalid JSON structure from RoomBoss API', 'err_api', 'txt', false, true);

            return ['status' => 'fail', 'response' => 'Invalid API response structure', 'number_posts' => 0];

        }



        // ✅ STEP 6: Extract and paginate results

        $availableHotels = $result['availableHotels'];

        $number_posts = count($availableHotels);

        $hotels = array_slice($availableHotels, $offset, $limit);



        return ['status' => 'success', 'response' => $hotels, 'number_posts' => $number_posts];



    } catch (Exception $e) {

        error_log('Error in hz_get_limited_available_hotels: ' . $e->getMessage());

        return ['status' => 'fail', 'response' => $e->getMessage(), 'number_posts' => 0];

    }

}



// Cron Scheduling

add_filter('cron_schedules', 'hz_cron_schedule');

function hz_cron_schedule($schedules)

{

    $schedules['every_two_mins'] = array(

        'interval' => (60 * 2), // Every 2 mins

        'display'  => 'Every 2 Mins',

    );

    $schedules['every_three_mins'] = array(

        'interval' => (60 * 3), // Every 3 mins

        'display'  => 'Every 3 Mins',

    );

    $schedules['every_five_mins'] = array(

        'interval' => (60 * 5), // Every 5 mins

        'display'  => 'Every 5 Mins',

    );

    $schedules['every_15_mins'] = array(

        'interval' => (60 * 15), // Every 15 mins

        'display'  => 'Every 15 Mins',

    );

    $schedules['every_3_hours'] = array(

        'interval' => (60 * 60 * 3), // Every 3 hours

        'display'  => 'Every 3 Hours',

    );

    $schedules['every_12_hours'] = array(

        'interval' => (60 * 60 * 12), // Every 12 hours

        'display'  => 'Every 12 Hours',

    );

    return $schedules;

}



// cronjob registration

add_action('init', 'hooks_for_crons');

function hooks_for_crons()

{

    // ⚠️ DEPRECATED: `hz_get_data_from_booking_sys` (old cron) — replaced by

    // the three dedicated single-purpose crons below. Unschedule any leftover

    // events so it can never run again on existing installs.

    $old_hook = 'hz_get_data_from_booking_sys';

    $old_ts   = wp_next_scheduled( $old_hook );

    while ( $old_ts ) {

        wp_unschedule_event( $old_ts, $old_hook );

        $old_ts = wp_next_scheduled( $old_hook );

    }



    // Also unschedule the previous unified sync cron — superseded by the 3 new crons.

    $unified_hook = 'kv_cron_sync_accommodations';

    $u_ts         = wp_next_scheduled( $unified_hook );

    while ( $u_ts ) {

        wp_unschedule_event( $u_ts, $unified_hook );

        $u_ts = wp_next_scheduled( $unified_hook );

    }



    // ✅ 1) ADD cron — runs every 2 minutes. Detects NEW property IDs returned

    // by the API that don't yet exist locally, and inserts them.

    if ( ! wp_next_scheduled( 'kv_cron_add_accommodations' ) ) {

        wp_schedule_event( time() + 30, 'every_two_mins', 'kv_cron_add_accommodations' );

    }



    // ✅ 2) UPDATE cron — runs every 15 minutes. Updates EXISTING properties

    // (no insert/delete logic here) so existing content stays current.

    if ( ! wp_next_scheduled( 'kv_cron_update_accommodations' ) ) {

        wp_schedule_event( time() + 60, 'every_15_mins', 'kv_cron_update_accommodations' );

    }



    // ✅ 3) STATUS / DELETE cron — runs twice a day. Reconciles local posts

    // against the API list, demoting posts that are no longer returned or

    // are flagged as inactive to draft.

    if ( ! wp_next_scheduled( 'kv_cron_status_accommodations' ) ) {

        wp_schedule_event( time() + 90, 'every_3_hours', 'kv_cron_status_accommodations' );

    }



    // Reviews crons (unchanged).

    if ( ! wp_next_scheduled( 'kv_cron_fetch_reviews' ) ) {

        wp_schedule_event( time(), 'daily', 'kv_cron_fetch_reviews' );

    }



    if ( ! wp_next_scheduled( 'kv_cron_fetch_product_reviews' ) ) {

        wp_schedule_event( time(), 'daily', 'kv_cron_fetch_product_reviews' );

    }

}



function kv_map_icon_slug($label) {

    /*get data from theme options*/

    $icons = get_field('amm_icons', 'option');



    foreach ( $icons as $key => $icon ) {

         return strpos(strtolower( $label ), strtolower( $icon) ) ? $icons[$key] : null;

    }



}



/**

 * Sync accommodation and room data from Booking System API

 * Runs as WordPress cron job to fetch and update properties, rooms, and images

 * Iterates through paginated API results and creates/updates posts with metadata

 * 

 * @return void Updates WordPress posts and metadata via options-based pagination

 */



add_action('init', 'mycf_init_func');

function mycf_init_func() {

    if( @$_GET['run'] == 'dev' ){

        kv_cron_run_update_batch();

    }

}



// ⚠️ DEPRECATED: This cron is intentionally disabled. It has been replaced

// by `kv_cron_sync_accommodations` (see below). The handler is kept as a

// no-op + manual debug entry point so any orphaned scheduled event does

// nothing harmful, and developers can still trigger it via ?run=dev.

function hz_get_data_from_booking_sys_func()

{

    cf_log( '[DEPRECATED] hz_get_data_from_booking_sys_func called — use kv_cron_sync_accommodations instead.', 'deprecated_cron', 'txt', false, true );

    return;

}



// NOTE: The original add_action('hz_get_data_from_booking_sys', ...) was

// removed because the new cron `kv_cron_sync_accommodations` supersedes it.

// The original body of the deprecated cron has also been removed; it was

// replaced by `kv_cron_run_sync_batch()` (the new, batched sync engine).



function sq_mapping_properties($properties) {

    if (empty($properties) || !is_array($properties)) {

        cf_log( 'No properties to process', 'no_properties', 'txt', false, true );

        return;

    }

    // pre($properties, 1);

    // $allowed_areas = hz_get_global_accommodation_whitelist();

    // ✅ STEP 7: Process each property

    foreach ($properties as $property) {

        // ✅ STEP 7a: Validate property structure

        if (!is_array($property) || empty($property['id'])) {

            kv_sync_log_entry([

                'property_id'   => 'unknown',

                'property_name' => 'unknown',

                'status'        => 'failed',

                'error'         => 'Invalid property structure or missing ID',

                'timestamp'     => current_time('Y-m-d H:i:s'),

            ]);

            continue;

        }

        // ✅ STEP 7b: Extract and validate basic property data

        $property_id = trim((string) $property['id']);

        if ($property_id === '') {

            kv_sync_log_entry([

                'property_id'   => 'unknown',

                'property_name' => 'unknown',

                'status'        => 'failed',

                'error'         => 'Property ID is empty after trimming',

                'timestamp'     => current_time('Y-m-d H:i:s'),

            ]);

            continue;

        }

        $hotelid = get_post_id_by_typeId($property_id, 'accommodation');

        $property_type =  strtolower(trim((string) ($property['property_type'] ?? '')));

        // Use property_type as source of truth. Do not force RoomBoss just because
        // a leftover room_boss_hotel_id is still present after BedBank conversion.
        $is_roomboss = ($property_type === 'roomboss' || $property_type === 'hybrid') ? 1 : 0;

        // $is_enabled = $property['is_enabled'] == 1;

        $_status = $property['status'] == 1;

        $hotel_name = trim( wp_strip_all_tags( empty( $property['client_property_name'] ) ? $property['name'] : $property['client_property_name'] ));

        if ($hotel_name === '') {

            kv_sync_log_entry([

                'property_id'   => $property_id,

                'property_name' => 'unknown',

                'status'        => 'failed',

                'error'         => 'Property name is empty after trimming',

                'timestamp'     => current_time('Y-m-d H:i:s'),

            ]);

            continue;

        }



        // Log start of property sync (with explicit action tag so it's easy

        // to filter sync logs by CREATE vs UPDATE in the admin or via grep).

        kv_sync_log_entry([

            'property_id'   => $property_id,

            'property_name' => $hotel_name,

            'status'        => 'processing',

            // 'status'        => $is_new_post ? 'creating' : 'updating',

            'error'         => '',

            'timestamp'     => current_time('Y-m-d H:i:s'),

        ]);



        $hotel_slug = sanitize_title($hotel_name);

        /* also clean the title if it contians url encoded strings eg: annupuri-residences-setsurin-%e9%9b%aa%e6%9e%97/ */

        $hotel_slug = preg_replace('/%[0-9a-f]{2}/i', '', $hotel_slug);

        $hotel_slug = preg_replace('/-+/', '-', $hotel_slug);

        $hotel_slug = trim($hotel_slug, '-');



        // if ($hotel_slug === '') {

        //     continue;

        // }

        $hotel_slug = html_entity_decode($hotel_slug, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Initialize metadata array

        $hotel_tid = '';

        $meta_input = [];

        $property_types = [];

        // ✅ STEP 7c: Set up property-specific metadata

        if ($is_roomboss) {

            $hotel_tid = trim((string) ($property['room_boss_hotel_id'] ?? ''));

            if ($hotel_tid !== '') {

                $meta_input['acc_hotel_id'] = $hotel_tid;

            }

            $meta_input['is_roomboss'] = '1';

        } else {

            // BedBank property — clear RoomBoss hotel id so filters treat it as BedBank.

            $meta_input['is_roomboss'] = '0';

            $meta_input['acc_hotel_id'] = '';

        }

        // ✅ STEP 7d: Extract accommodation details with safe defaults

        $detail = isset($property['detail']) && is_array($property['detail']) ? $property['detail'] : [];



        $hotel_desc          = trim((string) strip_tags($detail['long_description'] ?? ''));

        $client_hotel_desc   = trim((string) strip_tags($detail['client_long_description'] ?? ''));

        $list_desc           = trim((string) strip_tags($detail['list_description'] ?? ''));

        $unit_count          = intval($detail['unit_count'] ?? 0);

        $property_code       = trim((string) ($detail['property_code'] ?? ''));

        $phone_number        = trim((string) ($detail['phone_number'] ?? ''));

        $fax_number          = trim((string) ($detail['fax_number'] ?? ''));

        $media_code          = ($detail['media_code'] ?? 0);

        $email               = trim((string) ($detail['email'] ?? ''));

        $property_type       = trim((string) ($detail['property_type'] ?? ''));

        $no_of_bedrooms      = intval($detail['no_of_bedrooms'] ?? 0);

        $max_child_age       = intval($detail['max_child_age'] ?? 0);

        $trip_advisor_url    = trim((string) ($detail['trip_advisor_url'] ?? ''));

        $deposit_amount      = ($detail['deposit_amount'] ?? 0);

        $supplier_deposit    = ($detail['supplier_deposit'] ?? 0);

        $supplier_commission = ($detail['supplier_commission'] ?? 0);

        $supplier_markup     = ($detail['supplier_markup'] ?? 0);

        $sku_code            = ($detail['sku_code'] ?? 0);



        // ✅ STEP 7e: Extract rate plans and room types

        $extra_properties = (!empty($property['extra_properties']) && is_array($property['extra_properties'])) ? $property['extra_properties'] : [];

        $rateplans = is_array($property['ratePlanDescriptions'] ?? null) ? $property['ratePlanDescriptions'] : [];

        $roomTypes = is_array($property['rooms'] ?? null) ? $property['rooms'] : [];



        $resort_id = trim((string) ($property['resort_id'] ?? ''));

        // ✅ FIX #1 (create-vs-update): For NEW posts we always default to

        // 'publish' so a brand-new property doesn't get silently hidden as a

        // draft when the API returns status=0 / is_enabled=0 (which the API

        // sometimes does for newly added entries that haven't been reviewed

        // yet). For existing posts: API inactive → draft; otherwise keep

        // draft/pending/private as-is (do not auto-publish).

        $post_order = wp_count_posts( 'accommodation' );

        $post_order = $post_order ? $post_order->publish: 0;

        $address = trim((string) ($property['address_one'] ?? '') . (string) ($property['address_two'] ?? ''));

        $country = trim((string) ($property['country'] ?? ''));

        $latitude = trim((string) ($property['latitude'] ?? ''));

        $longitude = trim((string) ($property['longitude'] ?? ''));

        $location_info = trim((string) ($property['location_info'] ?? ''));

        $resort = (isset($property['resort']) && is_array($property['resort'])) ? $property['resort'] : [];

        // ✅ STEP 7f: Check if accommodation post already exists

        $hotel = get_post_id_by_typeId($property_id, 'accommodation');

        // ⚠️ IMPORTANT: this flag is what allows the create vs update path

        // to be distinguished. When $hotelid === 0 the post does not exist

        // and the rest of this function must CREATE one. When $hotelid > 0

        // we UPDATE the existing post.

        $is_new_post = ( $hotelid === 0 );



        // New posts default to publish. Existing draft/pending/private stay
        // unpublished even if the API reports status=1 (bulk sync must not
        // auto-publish intentionally drafted properties). API status=0 still
        // demotes to draft.

        if ( $is_new_post ) {

            $status = 'publish';

        } else {

            $current_status = get_post_status( $hotelid ) ?: 'draft';

            $preserve_statuses = [ 'draft', 'pending', 'private' ];

            if ( ! $_status ) {

                $status = 'draft';

            } elseif ( in_array( $current_status, $preserve_statuses, true ) ) {

                $status = $current_status;

            } else {

                $status = 'publish';

            }

        }



        $menu_order = $hotelid > 0 ? get_post_field('menu_order', $hotelid) : $post_order;



        // ✅ STEP 7g: Build accommodation metadata



        $num_bedrooms = get_hotel_num_bedrooms($roomTypes);



        $bedrooms = array_values(array_unique($num_bedrooms));



        $meta_input['property_id'] = $property_id;

        $meta_input['post_order'] = $post_order;

        $meta_input['accomodation_details_address'] = $address;

        $meta_input['_header_option'] = 'field_695261be5649d ';

        $meta_input['header_option'] = 'transparent ';



        if ($unit_count > 0) {

            $meta_input['unit_count'] = $unit_count;

        }



        if ($list_desc !== '') {

            $meta_input['bs_short_description'] = $list_desc;

        }



        if ($hotel_desc !== '') {

            $meta_input['quote_desc'] = $hotel_desc;

        }



        if ($client_hotel_desc !== '') {

            $meta_input['client_quote_desc'] = $client_hotel_desc;

        }



        if ($country !== '') {

            $meta_input['accomodation_details_acc_country'] = $country;

        }



        if ($latitude !== '') {

            $meta_input['accomodation_details_acc_latitude'] = $latitude;

        }



        if ($longitude !== '') {

            $meta_input['accomodation_details_acc_longitude'] = $longitude;

        }



        if ($location_info !== '') {

            $meta_input['acc_location_info'] = $location_info;

        }



        if ($property_code !== '') {

            $meta_input['acc_property_code'] = $property_code;

        }



        if ($phone_number !== '') {

            $meta_input['acc_phone_number'] = $phone_number;

        }



        if ($fax_number !== '') {

            $meta_input['acc_fax_number'] = $fax_number;

        }



        if ( !empty($media_code) ) {

            $meta_input['acc_media_code'] = $media_code;

        }



        if ($email !== '') {

            $meta_input['acc_email'] = $email;

        }



        if ($property_type !== '') {

            $property_types[] =$property_type;

        }



        if ( !empty( $bedrooms ) ) {

            $meta_input['acc_no_of_bedrooms'] = $bedrooms;

        }



        if ($max_child_age > 0) {

            $meta_input['acc_max_child_age'] = $max_child_age;

        }



        // if ($deposit_amount > 0) {

            $meta_input['acc_deposit_amount'] = $deposit_amount;

        // }



        if ($trip_advisor_url !== '') {

            $meta_input['acc_trip_advisor_url'] = $trip_advisor_url;

        }



        // if ($supplier_deposit > 0) {

            $meta_input['acc_supplier_deposit'] = $supplier_deposit;

        // }



        if ($supplier_commission > 0) {

            $meta_input['acc_supplier_commission'] = $supplier_commission;

        }



        if ($supplier_markup > 0) {

            $meta_input['acc_supplier_markup'] = $supplier_markup;

        }



        if ( !empty($sku_code) ) {

            $meta_input['acc_sku_code'] = $sku_code;

        }



        $supplier_fields = 

        [

            'supplier_id'                         => 'id',

            'supplier_type'                       => 'type',

            'room_boss_vendor_id'                 => 'room_boss_vendor_id',

            'arrival_departure'                   => 'arrival_departure',

            'vendor_type_id'                      => 'vendor_type_id',

            'booking_permission_id'               => 'booking_permission_id',

            'supplier_code'                       => 'code',

            'supplier_name'                       => 'name',

            'supplier_notes'                      => 'notes',

            'supplier_email'                      => 'email',

            'supplier_address'                    => 'address',

            'supplier_telephone'                  => 'telephone_number',

            'backoffice_ref'                      => 'backoffice_ref',

            'auto_confirm'                        => 'auto_confirm',

            'account_notes'                       => 'account_notes',

            'travel_e_doc_notes'                  => 'travel_e_document_notes',

            'booking_coaches_strategy'            => 'booking_coaches_strategy',

            'product_type'                        => 'product_type',

            'check_in_time'                       => 'check_in_time',

            'check_out_time'                      => 'check_out_time',

            'terms_conditions'                    => 'terms_conditions',

            'supplier_description'                => 'description',

            'image_url'                           => 'image_url',

            'supplier_url'                        => 'url',

            'book_and_pay'                        => 'book_and_pay_enabled',

            'hide_request_if_book_and_pay_enabled'=> 'hide_request_if_book_and_pay_enabled',

            'supplier_latitude'                   => 'latitude',

            'supplier_longitude'                  => 'longitude',

            'supplier_currency_code'              => 'currency_code',

            'supplier_country_code'               => 'country_code',

            'supplier_location_code'              => 'location_code',

            'price_mode'                          => 'price_mode',

            'supplier_price'                      => 'price',

            'sup_is_bb'                           => 'is_bed_bank',

            'supp_created_at'                     => 'created_at',

            'supp_updated_at'                     => 'updated_at',

        ];



        $supplier = isset($detail['supplier']) && is_array($detail['supplier']) ? $detail['supplier'] : [];

        if (!empty($supplier)) {

            foreach ($supplier_fields as $acf_field => $api_key) {

                if (isset($supplier[$api_key]) && !empty( $supplier[$api_key] )) {

                    $meta_input[$acf_field] = $supplier[$api_key];

                }

            }

        }

        // ✅ STEP 7h: Prepare accommodation post data for insert/update

        $hotel_data = [

            'ID' => $hotelid,

            'post_title' => $hotel_name,

            'post_name' => $hotel_slug,

            'post_content' => $hotel_desc,

            'post_type' => 'accommodation',

            'post_status' => $status,

            'menu_order' => $menu_order,

        ];

        // pre( 'hotel_data' );
        // pre( $hotel_data, 1 );

        // ✅ STEP 7i: Insert or update accommodation post

        $upd_hotel_id = wp_insert_post($hotel_data, true);

        if (is_wp_error($upd_hotel_id)) {

            $error_msg = $upd_hotel_id->get_error_message();

            cf_log( 'Error creating/updating accommodation: ' . $error_msg, 'roomboss_hotels', 'txt', false, true );

            kv_sync_log_entry([

                'property_id'   => $property_id,

                'property_name' => $hotel_name,

                'status'        => 'failed',

                'error'         => 'wp_insert_post error: ' . $error_msg,

                'timestamp'     => current_time('Y-m-d H:i:s'),

            ]);

            continue;

        }

       /* if not empty property_type add it in term "property_types" */

       if( !empty($property_types) ){

            $property_type_ids = [];

            foreach( $property_types as $ptype ){

                $term_id = kv_upsert_taxonomy_term('property_types', ucwords($ptype).'s');

                if( !empty($term_id) ){

                    $property_type_ids[] = intval($term_id);

                }

            }

            if( !empty($property_type_ids) ){

                wp_set_object_terms($upd_hotel_id, $property_type_ids, 'property_types');

            }

        }



        if (!empty($resort) && !empty($resort_id) && !empty($resort['name'])) {



            $taxonomy = 'accommodation-cat'; // change if using custom taxonomy



            $resort_name = ucwords( $resort['name'].' Accommodation' );

            // $resort_slug = strtolower( $resort['name'].'-accommodation' );

            $resort_cat_ids = [];



            // 1. Create/Get Parent Term (Resort)

            $parent_id = kv_upsert_taxonomy_term($taxonomy, $resort_name, 0);

            if (empty($parent_id)) {

                cf_log('Failed to resolve parent term for resort: ' . $resort_name, 'roomboss_hotels', 'txt', false, true);

                continue;

            }

            $resort_cat_ids[] = intval($parent_id);

            

            // 2. Loop Locations → Create Child Terms

            $locations = isset($resort['locations']) && is_array($resort['locations']) ? $resort['locations'] : [];



            if (!empty($locations)) {

                $selected_locations = @$property['resort_location_id'] ?? [];



                foreach ($locations as $location) {

                    if( ! in_array($location['id'], $selected_locations) ) {

                        continue; // Skip locations not associated with this property

                    }



                    if (!is_array($location) || empty($location['location']) ) {

                        continue;

                    }

                    // if( $allowed_areas && isset($allowed_areas[$resort['name']]) && !in_array($location['location'], $allowed_areas[$resort['name']]) ) {

                    //     continue; // Skip locations not in allowed areas list

                    // }

                    $child_cat_id = kv_upsert_taxonomy_term($taxonomy, $location['location'], $parent_id);

                    if (empty($child_cat_id)) {

                        continue;

                    }

                    $resort_cat_ids[] = intval($child_cat_id);

                }

            }

            // 3. Assign accommodation to parent/child resort category

            wp_set_object_terms($upd_hotel_id, $resort_cat_ids, $taxonomy);

            // pre($upd_hotel_id, 0);

            // pre($resort_cat_ids, 0);

            // pre($property, 1);

        }



        foreach ($meta_input as $meta_key => $meta_value) {

            update_post_meta($upd_hotel_id, $meta_key, $meta_value);

        }

        // Keep ACF true/false field in sync after BedBank ↔ RoomBoss conversion.
        if (function_exists('update_field') && isset($meta_input['is_roomboss'])) {
            update_field('is_roomboss', $meta_input['is_roomboss'] === '1' || $meta_input['is_roomboss'] === 1 ? 1 : 0, $upd_hotel_id);
        }

        // Fully clear stale RoomBoss hotel id after BedBank conversion.
        if (!$is_roomboss) {
            delete_post_meta($upd_hotel_id, 'acc_hotel_id');
        }

        $unit_ids = [];

        if( !empty( $extra_properties ) ){

            foreach ( $extra_properties as $extra_property ) {

                if (!is_array($extra_property) || !isset($extra_property['data']) || !is_array($extra_property['data'])) {

                    continue;

                }



                $data = $extra_property['data'];

                

                if (!empty($data['options']) && is_array($data['options'])) {



                    $options = $data['options'];



                    if (!empty($data) && !empty($data['name'])) {



                        if ($data['name'] === 'Property Facilities' || $data['name'] === 'Unit Facilities') {

                            $aminity_ids = [];

                            $faq_ids = [];



                            foreach ($options as $op_key => $option) {

                                if (!is_array($option) || empty($option['name'])) {

                                    continue;

                                }



                                if (

                                    (!empty($option['value'])) ||

                                    (!empty($option['checked']) && $option['checked'] === true)

                                ) {

                                    $icon = isset($option['icon']) ? $option['icon'] : '';

                                    $label = $option['name'];



                                    // Convert label to icon slug (match your dropdown values)

                                    

                                    if( $data['name'] == 'Property Facilities' ){



                                        $term_id = kv_upsert_taxonomy_term('property_ammenites', $label);

                                        $aminity_ids[] = intval($term_id);

                                        update_field('field_69fb87ef2b007', $icon, 'property_ammenites_' . $term_id);



                                        if( isset( $option['faq'] ) ){

                                            $faq_data = $option['faq'];

                                            if ( !empty($faq_data) && isset($faq_data['title']) && isset($faq_data['description']) ) {

                                                $faq_title = trim(wp_strip_all_tags($faq_data['title']));

                                                $faq_description = trim($faq_data['description']);



                                                if ( !empty($faq_title) ) {

                                                    // Use the $term_id from property_ammenites as the parent_id for the FAQ term

                                                    $faq_term_id = kv_upsert_taxonomy_term('ammenites_faq', $faq_title);

                                                    $faq_ids[] = intval($faq_term_id);

                                                    if ( !empty($faq_term_id) ) {

                                                        wp_update_term($faq_term_id, 'ammenites_faq', ['description' => $faq_description]);

                                                    }

                                                }

                                            }

                                        }

                                    }



                                    if( $data['name'] == 'Unit Facilities' ){



                                        $term_id = kv_upsert_taxonomy_term('unit_ammenites', $label);

                                        $unit_ids[] = intval($term_id);

                                        update_field('field_69fb8993792ae', $icon, 'unit_ammenites_' . $term_id);

                                    }

                                }

                            }

                            // 3. Assign accommodation to unit and aminity category

                            if( $aminity_ids ) {

                                wp_set_object_terms($upd_hotel_id, $aminity_ids, 'property_ammenites');

                            }

                        }

                    }

                }

            }

        }



        // ✅ STEP 7l: Process rate plans if available

        if (!empty($rateplans) && is_array($rateplans)) {

            $rate_plan_rows = [];



            foreach ($rateplans as $rateplan) {

                /*check if client_rateplan_name contains keyword "discount" mark the accomdation as discount*/



                if (!empty($rateplan['client_rateplan_name']) && stripos($rateplan['client_rateplan_name'], 'discount') !== false) {

                    update_post_meta($upd_hotel_id, 'is_discount', 1);

                }

                else{

                    update_post_meta($upd_hotel_id, 'is_discount', 0);

                }



                $rate_plan_rows[] = [

                    'rate_plan_id' => $rateplan['rate_plan_id'] ?? '',

                    'rate_plan_name' => !empty(trim($rateplan['client_rateplan_name'] ?? '')) ?

                        trim(html_entity_decode($rateplan['client_rateplan_name'])) : 'Standard Rate',

                    'rate_plan_description' => !empty(trim($rateplan['client_description'] ?? '')) ?

                        trim(html_entity_decode($rateplan['client_description'])) : '',

                    'rate_plan_long_descriptions' => !empty(trim($rateplan['client_long_description'] ?? '')) ?

                        trim(html_entity_decode($rateplan['client_long_description'])) : '',

                ];

            }



            update_field('rate_plan', $rate_plan_rows, $upd_hotel_id);

        }

        // ✅ STEP 7m: Process room types for this accommodation

        $room_ids = [];

        $room_links = '';

        if (!empty($roomTypes) && is_array($roomTypes)) {

            foreach ($roomTypes as $key => $roomType) {

                // Validate room data structure

                if (!is_array($roomType) || empty($roomType['id'])) {

                    continue;

                }

                // Extract room basic info

                $room_name = !empty($roomType['client_unit_name']) ? trim( htmlentities($roomType['client_unit_name']) ) : trim( htmlentities($roomType['name']) );

                if (empty($room_name)) {

                    continue;

                }

                $room_id = trim($roomType['id']);


                // Extract room configuration

                $roomboss_room_id = isset( $roomType['room_boss_room_id'] ) && !empty( $roomType['room_boss_room_id'] ) ? intval($roomType['room_boss_room_id'] ) : 0;

                $pricing_model = isset( $roomType['pricing_model'] ) && !empty( $roomType['pricing_model'] ) ? intval($roomType['pricing_model'] ) : 0;

                $maxNumberGuests = isset( $roomType['no_of_guest'] ) && !empty( $roomType['no_of_guest'] ) ? intval($roomType['no_of_guest'] ) : 0;

                $numberBedrooms = isset( $roomType['room_per_unit'] ) && !empty( $roomType['room_per_unit'] ) ? intval($roomType['room_per_unit'] ) : 0;

                $bedding_options = isset( $roomType['bedding_options'] ) && !empty( $roomType['bedding_options'] ) ? json_decode($roomType['bedding_options'] ) : 0;

                $guest_types = isset( $roomType['guest_types'] ) && !empty( $roomType['guest_types'] ) ? json_decode($roomType['guest_types'] ) : 0;

                $numberBathrooms = isset( $roomType['no_of_bathrooms'] ) && !empty( $roomType['no_of_bathrooms'] ) ? intval($roomType['no_of_bathrooms'] ) : 0;

                $maxNumberAdults = isset( $roomType['maximum_adults'] ) && !empty( $roomType['maximum_adults'] ) ? intval($roomType['maximum_adults'] ) : 0;

                $minNumberAdults = isset( $roomType['minimum_adults'] ) && !empty( $roomType['minimum_adults'] ) ? intval( $roomType['minimum_adults'] ) : 0;

                $maxNumberChildren = isset( $roomType['additional_children'] ) && !empty( $roomType['additional_children'] ) ? intval($roomType['additional_children'] ) : 0;

                $maxNumberInfants = isset( $roomType['additional_infants'] ) && !empty( $roomType['additional_infants'] ) ? intval($roomType['additional_infants'] ) : 0;

                $room_sqm = isset( $roomType['square_meters'] ) && !empty( $roomType['square_meters'] ) ? intval($roomType['square_meters'] ) : 0;

                $standard_adults = isset( $roomType['standard_adults'] ) && !empty( $roomType['standard_adults'] ) ? trim($roomType['standard_adults']) : '';

                $no_of_units = isset( $roomType['no_of_units'] ) && !empty( $roomType['no_of_units'] ) ? intval($roomType['no_of_units']) : 0;

                $additional_children = isset( $roomType['additional_children'] ) && !empty( $roomType['additional_children'] ) ? intval($roomType['additional_children']) : 0;

                $include_children = isset( $roomType['include_children'] ) && !empty( $roomType['include_children'] ) ? intval($roomType['include_children']) : 0;

                $additional_infants = isset( $roomType['additional_infants'] ) && !empty( $roomType['additional_infants'] ) ? intval($roomType['additional_infants']) : 0;

                $include_infants = isset( $roomType['include_infants'] ) && !empty( $roomType['include_infants'] ) ? intval($roomType['include_infants']) : 0;

                $desc = isset( $roomType['description'] ) && !empty( $roomType['description'] ) ? trim($roomType['description']) : '';

                $client_desc = isset( $roomType['client_description'] ) && !empty( $roomType['client_description'] ) ? trim($roomType['client_description']) : '';

                $room_facilities = isset( $roomType['facilities'] ) && !empty( $roomType['facilities'] ) ? json_decode( $roomType['facilities'], true ) : [];

                $status = isset( $roomType['global_status'] ) && $roomType['global_status'] == 1 ? 'publish' :'draft';

                // Get or create room post

                $rooms = get_hotel_rooms($property_id, [$room_id]);

                

                if (!is_array($rooms) || !isset($rooms['rooms']) || !is_array($rooms['rooms'])) {

                    continue;

                }



                $roomid = 0; // Default: new room

                if (!empty($rooms['rooms']) && isset($rooms['rooms'][0]) && is_object($rooms['rooms'][0])) {

                    $roomid = $rooms['rooms'][0]->ID;

                }



                // Build room post data

                $room_data = [

                    'ID' => $roomid,

                    'post_parent' => $upd_hotel_id,

                    'post_title' => $room_name,

                    'post_type' => 'japan_rooms',

                    'post_status' => $status,

                    // 'post_status' => 'publish'

                ];



                // Initialize room metadata

                $room_meta_input = [

                    'property_id'       => $property_id,

                    'pricing_model'     => $pricing_model,

                    'roomboss_room_id'  => $roomboss_room_id,

                    'actual_room_id'    => $room_id,

                    'room_guests'       => $maxNumberGuests,

                    'room_sqm'          => $room_sqm,

                    'room_bedroom'      => $numberBedrooms,

                    'room_bathroom'     => $numberBathrooms,

                    'room_adults'       => $maxNumberAdults,

                    'room_children'     => $maxNumberChildren,

                    'room_infants'      => $maxNumberInfants,

                    'minimum_adults'    => $minNumberAdults,

                    'maximum_adults'    => $maxNumberAdults,

                    'standard_adults'   => $standard_adults,

                    'no_of_units'       => $no_of_units,

                    'additional_children' => $additional_children,

                    'include_children' => $include_children,

                    'additional_infants' => $additional_infants,

                    'include_infants' => $include_infants,

                    'room_desc' => $desc,

                    'client_description' => $client_desc,

                    'jp_hotel_link' => [

                        'title' => $hotel_name,

                        'url' => get_permalink($upd_hotel_id),

                        'target' => '_blank',

                    ],

                    'jp_hotel' => [$upd_hotel_id],

                ];



                // Add RoomBoss-specific metadata only for RoomBoss-backed
                // rooms. Hybrid properties can also contain BedBank rooms,
                // and those must not be shown in the RoomBoss website flow.

                $room_tid = trim((string) ($roomType['room_boss_room_id'] ?? ''));

                $room_is_roomboss = $is_roomboss && $room_tid !== '';

                if ($room_is_roomboss) {

                    $room_desc = isset($roomType['description']) ? trim($roomType['description']) : '';



                    $room_data['post_content'] = $room_desc;

                    $room_meta_input['room_hotel_id'] = $hotel_tid;

                    $room_meta_input['room_type_id'] = $room_tid;

                    $room_meta_input['is_roomboss'] = '1';

                } else {

                    // BedBank / manual room — clear leftover RoomBoss room links.

                    $room_meta_input['is_roomboss'] = '0';

                    $room_meta_input['roomboss_room_id'] = '0';

                    $room_meta_input['room_hotel_id'] = '';

                    $room_meta_input['room_type_id'] = '';

                }



                // Insert or update room post

                $upd_room_id = wp_insert_post($room_data);

                if (is_wp_error($upd_room_id)) {

                    cf_log( 'Error creating/updating room: ' . $upd_room_id->get_error_message(), 'roomboss_rooms', 'txt', false, true );

                    continue;

                }

                // cf_log( $room_facilities, 'roomfacilities' );

                if (!empty($room_facilities)) {

                    /* first remove all facility options then add the new ones */

                    wp_remove_object_terms($upd_room_id, wp_get_object_terms($upd_room_id, 'room_facilities', array('fields' => 'ids')), 'room_facilities');

                    $facility_options = $room_facilities['options'];

                    $room_fac_ids = [];

                    // pre( var_dump( $facility_options ));

                    foreach ($facility_options as $bed_key => $option) {

                        // pre( var_dump( $option ) );

                        if ( empty($option['name']) || !isset($option['checked']) || $option['checked'] === false ) {

                            continue;

                        }

                        

                        $icon = isset($option['icon']) ? $option['icon'] : '';

                        $label = $option['name'];



                        $term_id = kv_upsert_taxonomy_term('room_facilities', $label);

                        $room_fac_ids[] = intval($term_id);

                        update_field('field_6a0c86ac337bc', $icon, 'room_facilities_' . $term_id);

                    }
                    
                    if( $room_fac_ids ) {

                        wp_set_object_terms($upd_room_id, $room_fac_ids, 'room_facilities');

                    }

                }

                if (!empty($bedding_options)) {

                    /* first remove all bedidng options then add the new ones*/

                    wp_remove_object_terms($upd_room_id, wp_get_object_terms($upd_room_id, 'bedding_options', array('fields' => 'ids')), 'bedding_options');

                    $bed_term_ids = [];

                    foreach ($bedding_options as $bed_key => $bedding_option) {

                        

                        if( !is_array( $bedding_option ) ){

                            $bedding_option[] = $bedding_option;

                        }



                        foreach ($bedding_option as $key => $option) {

                            /*add terms even if th*/

                            if ( is_int( $option ) ) {

                                continue;

                            }

                            $bed_term_id = kv_upsert_taxonomy_term('bedding_options', $option);

                            $bed_term_ids[] = intval($bed_term_id);

                        }

                    }



                    if($bed_term_ids){

                        wp_set_object_terms($upd_room_id, $bed_term_ids, 'bedding_options');

                    }

                }
                // pre( 'guest_types' );
                // pre( $guest_types, 1 );
                if (!empty($guest_types) && isset($guest_types[0])) {

                    /* first remove all guest types then add the new ones */

                    wp_remove_object_terms($upd_room_id, wp_get_object_terms($upd_room_id, 'guest_types', array('fields' => 'ids')), 'guest_types');



                    $new_guest_types = $guest_types[0];

                    if( !empty( $new_guest_types ) ){

                        $guest_term_ids = [];



                        foreach ($new_guest_types as $guest_type) {

                        

                            if( !is_array( $guest_type ) ){

                                $guest_type[] = $guest_type;

                            }



                            foreach ($guest_type as $type) {



                                if (is_numeric($type)) continue;



                                $guest_term_id = kv_upsert_taxonomy_term('guest_types', $type);

                                $guest_term_ids[] = intval($guest_term_id);

                            }

                        }



                        if($guest_term_ids){

                            wp_set_object_terms($upd_room_id, $guest_term_ids, 'guest_types');

                        }

                    }

                }

                if( $unit_ids ) {

                    wp_set_object_terms($upd_hotel_id, $unit_ids, 'unit_ammenites');

                }

                $room_ids[] = $upd_room_id;

                $room_links .= '<a href="' . admin_url('post.php?post=' . $upd_room_id . '&action=edit') . '">' . esc_html($room_name) . '</a>' . "\n";

                // Add room images

                $room_imgs = $roomType['images'] ?? [];

                hz_add_img_from_booking_sys($room_imgs, $upd_room_id, 'room');



                // Update room metadata

                foreach ($room_meta_input as $meta_key => $meta_value) {

                    update_post_meta($upd_room_id, $meta_key, $meta_value);

                }

                if (!$room_is_roomboss) {
                    delete_post_meta($upd_room_id, 'room_hotel_id');
                    delete_post_meta($upd_room_id, 'room_type_id');
                }

            }

        }

        // ✅ STEP 7n: Update accommodation with room relationships

        update_field('jp_rooms_link', $room_links, $upd_hotel_id);

        update_field('jp_rooms', $room_ids, $upd_hotel_id);

        update_option('hz_post_order', $post_order + 1, false);



        // ✅ STEP 7j: Add accommodation images and update metadata
        // pre( $upd_room_id, 1 );
        hz_add_img_from_booking_sys(@$property['images'], $upd_hotel_id, 'accommodation');

        // Log successful sync (the wrapper above logs 'created' vs 'updated'

        // with the action tag, this entry keeps the legacy 'success' status

        // for backward compatibility with existing log consumers).

        kv_sync_log_entry([

            'property_id'   => $property_id,

            'property_name' => $hotel_name,

            'status'        => 'success',

            // 'error'         => $is_new_post ? 'NEW POST CREATED (ID ' . $upd_hotel_id . ')' : '',

            'error'         => '',

            'timestamp'     => current_time('Y-m-d H:i:s'),

        ]);

    }
    // kv_update_accommodation_menu_order();

}



add_action( 'kv_cron_fetch_reviews', 'kv_cron_fetch_reviews_fn' );

function kv_cron_fetch_reviews_fn() {

    

    $store_id = 'japanskiexperience-com1';

    $api_key  = 'ae0427dc29a62e2320110a6c157bc45b';



    $per_page = 100;

    $page     = 1;

    $sort     = 'date_created';



    global $wpdb;

    while ( true ) {

        $url = add_query_arg([

            'store'    => $store_id,

            'per_page' => $per_page,

            'page'     => $page,

            'sort'     => $sort,

        ], 'https://api.reviews.io/merchant/reviews');



        $response = wp_remote_get( $url, [

            'headers' => [

                'Content-Type' => 'application/json',

                'store'        => $store_id,

                'apikey'       => $api_key,

            ],

            'timeout' => 20,

        ]);



        if ( is_wp_error( $response ) ) {

            break;

        }



        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['reviews'] ) ) {

            cf_log( 'No more reviews', 'review_cron', 'txt', true, true );

            break;

        }



        /*

         * Store company-level stats ONCE (page 1)

         */

        if ( $page === 1 && ! empty( $body['stats'] ) ) {



            update_option( 'kv_company_total_reviews', (int) $body['stats']['total_reviews'], false );

            update_option( 'kv_company_average_rating', (float) $body['stats']['average_rating'], false );

        }



        /*

         * Process reviews

         */

        foreach ( $body['reviews'] as $review ) {



            if ( empty( $review['store_review_id'] ) ) {

                continue;

            }

            // Prevent duplicates

            $exists = $wpdb->get_var(

                $wpdb->prepare(

                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",

                    'store_review_id',

                    $review['store_review_id']

                )

            );



            if ( $exists ) {

                continue;

            }



            $author = trim(

                html_entity_decode(

                    ( $review['reviewer']['first_name'] ?? '' ) . ' ' .

                    ( $review['reviewer']['last_name'] ?? '' )

                )

            );



            $post_id = wp_insert_post([

                'post_title'   => !empty( $author ) ? sanitize_text_field( str_replace('&quot;', '', $author) ) : 'Anonymous Review',

                'post_type'    => 'reviews',

                'post_status'  => 'publish',

                'post_content' => $review['comments'] ?? '',

            ]);



            if ( ! $post_id || is_wp_error( $post_id ) ) {



                cf_log( 'review failed to create msg: '.$post_id->get_error_message() , 'err_review_cron', 'txt', false, true );

                continue;

            }

            /*

             * Save meta

             */

            update_post_meta( $post_id, 'review_rating', (int) $review['rating'] );

            update_post_meta( $post_id, 'review_date', $review['date_created'] );

            update_post_meta( $post_id, 'store_review_id', $review['store_review_id'] );



            $posted_date = 'Posted ' . date( 'F Y', strtotime( $review['date_created'] ) );



            if ( function_exists( 'update_field' ) ) {

                update_field( 'posted_date', $posted_date, $post_id );

            } else {

                update_post_meta( $post_id, 'posted_date', $posted_date );

            }

            /*

             * Assign review tags → CPT taxonomy (slug: review_tags)

             */

            if ( ! empty( $review['tags'] ) && is_array( $review['tags'] ) ) {



                $tag_slugs = [];



                foreach ( $review['tags'] as $tag ) {

                    if ( ! empty( $tag['tag'] ) ) {

                        $tag_slugs[] = sanitize_title( $tag['tag'] );

                    }

                }



                if ( ! empty( $tag_slugs ) ) {

                    wp_set_object_terms( $post_id, $tag_slugs, 'review_tags' );

                }

            }

        }



        $page++;

    }

}



add_action( 'kv_cron_fetch_product_reviews', 'kv_cron_fetch_product_reviews_fn' );

function kv_cron_fetch_product_reviews_fn() {

    $store = 'japanskiexperience-com1';

    $per_page = 100;

    $sort     = 'date_created';



    global $wpdb;



    // Get all unique SKUs from accommodations to fetch reviews per product

    $skus = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'prd_sku' AND meta_value != ''");



    if ( empty( $skus ) ) {

        return;

    }



    foreach ( $skus as $sku ) {

        $page = 1;



        while ( true ) {

            $url = 'https://api.reviews.io/product/review?store=' . $store . '&sku=' . $sku . '&per_page=' . $per_page . '&page=' . $page . '&verified_only=true&comments_only=true';



            $response = wp_remote_get( $url, [

                'headers' => [ 'Content-Type' => 'application/json' ],

                'timeout' => 20,

            ]);



            if ( is_wp_error( $response ) ) {

                break;

            }



            $body = json_decode( wp_remote_retrieve_body( $response ), true );



            if ( empty( $body['reviews']['data'] ) ) {

                break;

            }



            $data = $body['reviews']['data'];



            foreach ( $data as $review ) {

                if ( empty( $review['product_review_id'] ) ) {

                    continue;

                }



                // Prevent duplicates

                $exists = $wpdb->get_var(

                    $wpdb->prepare(

                        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",

                        'product_review_id',

                        $review['product_review_id']

                    )

                );

                if ( $exists ) {

                    continue;

                }



                $author = trim(

                    html_entity_decode(

                        ( $review['reviewer']['first_name'] ?? '' ) . ' ' .

                        ( $review['reviewer']['last_name'] ?? '' )

                    )

                );



                $post_id = wp_insert_post([

                    'post_title'   => $author ? sanitize_text_field( str_replace('&quot;', '', $author) ) : 'Anonymous Review',

                    'post_type'    => 'reviews',

                    'post_status'  => 'publish',

                    'post_content' => $review['review'] ?? '',

                ]);

                if ( ! $post_id || is_wp_error( $post_id ) ) {

                    continue;

                    }

                update_post_meta( $post_id, 'review_rating', (int) $review['rating'] );

                update_post_meta( $post_id, 'review_date', $review['date_created'] );

                update_post_meta( $post_id, 'product_review_id', $review['product_review_id'] );

                update_post_meta( $post_id, 'product_sku', $sku ); // Store specific SKU



                $posted_date = 'Posted ' . date( 'F Y', strtotime( $review['date_created'] ) );



                if ( function_exists( 'update_field' ) ) {

                    update_field( 'posted_date', $posted_date, $post_id );

                } else {

                    update_post_meta( $post_id, 'posted_date', $posted_date );

                }



                wp_set_object_terms( $post_id, 494, 'reviews_catgory' );



                if ( ! empty( $review['tags'] ) && is_array( $review['tags'] ) ) {

                    $tag_slugs = [];

                    foreach ( $review['tags'] as $tag ) {

                        if ( ! empty( $tag['tag'] ) ) {

                            $tag_slugs[] = sanitize_title( $tag['tag'] );

                        }

                    }

                    if ( $tag_slugs ) {

                        wp_set_object_terms( $post_id, $tag_slugs, 'review_tags' );

                    }

                }

            }



            $page++;

        }

    }

}



function kv_display_product_reviews_shortcode($atts) {

    // Get SKU from attribute, or fallback to current post field

    $sku = isset($atts['sku']) ? sanitize_text_field($atts['sku']) : '';



    if ( empty($sku) ) {

        $sku = get_field('prd_sku');

    }



    if (empty($sku)) {

        return;

    }



    $query_args = [

        'post_type'      => 'reviews',

        'post_status'    => 'publish',

        'posts_per_page' => -1,

        'tax_query'      => [

            [

                'taxonomy' => 'reviews_catgory',

                'field'    => 'term_id',

                'terms'    => 494,

            ],

        ],

        'meta_query'     => [

            [

                'key'     => 'product_sku',

                'value'   => $sku, // No need for semicolons if only one SKU is stored

                'compare' => '=',  // Use exact comparison

            ],

        ],

    ];



    $review_query = new WP_Query($query_args);

    $review_count = $review_query->found_posts;

    $is_slider    = ($review_count > 3);



    ob_start();



    if ($review_query->have_posts()) {



        echo '<div class="prop-reviews-track js-slick-reviews">';

        while ($review_query->have_posts()) {

            $review_query->the_post();

            $rating      = get_post_meta(get_the_ID(), 'review_rating', true);

            $posted_date = get_post_meta(get_the_ID(), 'posted_date', true);

            $stars       = intval($rating);

            ?>

            <div class="review-card">

                <div class="review-card-stars">

                    <?php for ($i = 1; $i <= 5; $i++) : 

                        $fill = ($i <= $stars) ? '#FFCC02' : 'rgba(255,255,255,0.15)';

                    ?>

                        <svg viewBox="0 0 20 20" fill="<?php echo $fill; ?>" width="14" height="14">

                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>

                        </svg>

                    <?php endfor; ?>

                </div>

                <p class="review-quote"><?php echo wp_trim_words(get_the_content(), 50); ?></p>

                <div class="review-author">

                    <span class="review-author-name"><?php the_title(); ?></span>

                    <span><?php echo esc_html($posted_date); ?></span>

                </div>

            </div>

            <?php

        }

        echo '</div>';



        // if ($is_slider) : ?>

            <script>

                jQuery(document).ready(function($) {

                    $('.reviewCounts').html('<?php echo $review_count; ?>');

                    $('.js-slick-reviews:not(.slick-initialized)').slick({

                        infinite: true,

                        slidesToShow: 3,

                        slidesToScroll: 1,

                        arrows: true,

                        dots: false,

                        prevArrow: '<button type="button" class="slick-prev"><img src="' + base_url + '/wp-content/themes/kingdomvision/images/left_arrow.svg" alt="Previous"></button>',

                        nextArrow: '<button type="button" class="slick-next"><img src="' + base_url + '/wp-content/themes/kingdomvision/images/right_arrow.svg" alt="Next"></button>',

                        responsive: [{

                            breakpoint: 1024,

                            settings: {

                                slidesToShow: 2

                            }

                        }, {

                            breakpoint: 768,

                            settings: {

                                slidesToShow: 1

                            }

                        }]

                    });



                    if( $( '.slick-list .review-card' ).length <= 4 ){

                        $('.prop-reviews-section').addClass('hide');

                    }

                });

            </script>

        <?php //endif;

        wp_reset_postdata();

    } else {

        echo '<p style="color: rgba(255,255,255,0.5); text-align: center;">' . esc_html__('No verified guest reviews found for this property.', 'generatepress') . '</p>';

    }



    return ob_get_clean();

}

add_shortcode('kv_product_reviews', 'kv_display_product_reviews_shortcode');





/* =====================================================================

 *  BATCHED / RESUMABLE ACCOMMODATION SYNC (replaces hz_get_data_from_booking_sys)

 * =====================================================================

 *

 *  Design goals

 *  ------------

 *  - Server-friendly: small batches per cron tick (default 5 properties),

 *    each wrapped in try/catch so one bad record never kills the batch.

 *  - Resumable: the last successfully processed `property_id` is persisted

 *    in an option, so an interrupted run picks up exactly where it left off.

 *  - Change-aware: a stable hash of the API payload is stored on the post

 *    and compared before writing — unchanged posts are skipped entirely.

 *  - Memory/time safe: explicit `wp_cache_flush_runtime()` + a soft time

 *    budget that yields control back to WP-Cron when exceeded.

 *  - Stale-aware: only after a *complete* pass do we move properties

 *    absent from the API to `draft` — never mid-cycle.

 *  - Fully logged: every property produces a `kv_sync_log_entry()` row

 *    (success / failed / skipped) and per-run counters are stored.

 *

 *  How a run works (one cron tick)

 *  -------------------------------

 *  1. Read `kv_sync_state` (offset, total_seen, last_id, run_started_at,

 *     pass_complete, etc.).

 *  2. Fetch one page from the API via `hz_get_limited_properties()`.

 *  3. For each property, call `kv_process_single_property()` which:

 *       - looks up the existing post by `property_id`,

 *       - compares the new payload hash to the stored hash,

 *       - if unchanged → skip (log + continue),

 *       - otherwise delegates to `sq_mapping_properties()` (the existing

 *         saver) inside try/catch, then records the new hash.

 *  4. Persist the running totals and `last_id`.

 *  5. When the page returns fewer items than `per_page`, the pass is

 *     considered complete → we then sweep the database once to move

 *     unseen properties to `draft` (if enabled), then reset state.

 *  6. If the soft time budget is exceeded, exit cleanly so the next

 *     cron tick resumes from `last_id`.

 *

 * ===================================================================== */



/**

 * Get the current sync state (default values on first run).

 *

 * @return array

 */

function kv_get_sync_state() {

    $defaults = [

        'is_running'        => 0,        // 1 while a pass is in progress

        'pass_started_at'   => 0,        // unix ts of current pass

        'run_started_at'    => 0,        // unix ts of current cron tick

        'offset'            => 0,        // last processed offset (0-based)

        'page'              => 1,        // last requested API page

        'per_page'          => 5,        // properties per cron tick

        'seen_ids'          => [],       // property IDs seen this pass

        'last_id'           => '',       // last successfully processed ID

        'pass_complete'     => 0,        // 1 when API has been fully consumed

        'total_processed'   => 0,        // processed this pass

        'total_created'     => 0,        // NEW posts created this pass

        'total_updated'     => 0,        // existing posts updated this pass

        'total_skipped'     => 0,        // unchanged this pass

        'total_failed'      => 0,        // errors this pass

        'last_run_at'       => 0,        // last cron tick ts

        'last_run_summary'  => '',       // human-readable last run summary

    ];

    $state = get_option( 'kv_sync_state', [] );

    if ( ! is_array( $state ) ) {

        $state = [];

    }

    return array_merge( $defaults, $state );

}



/**

 * Persist sync state.

 *

 * @param array $state

 * @return void

 */

function kv_save_sync_state( array $state ) {

    update_option( 'kv_sync_state', $state, false );

}



/**

 * Reset the sync state (start a fresh pass from the beginning).

 *

 * @return void

 */

function kv_reset_sync_state() {

    delete_option( 'kv_sync_state' );

}



/**

 * Compute a stable hash of an API property payload used for change detection.

 * Only the fields that should trigger a rewrite are included.

 *

 * @param array $property

 * @return string

 */

function kv_property_payload_hash( array $property ) {

    // Whitelist the keys that affect the rendered post. Anything outside

    // this list is ignored for change detection (e.g. transient stats).

    $keys = [

        'id', 'name', 'client_property_name', 'is_enabled', 'status',

        'property_type', 'resort_id', 'resort', 'resort_location_id',

        'address_one', 'address_two', 'country', 'latitude', 'longitude',

        'location_info', 'detail', 'images', 'extra_properties',

        'ratePlanDescriptions', 'rooms',

    ];

    $slice = [];

    foreach ( $keys as $k ) {

        if ( array_key_exists( $k, $property ) ) {

            $slice[ $k ] = $property[ $k ];

        }

    }

    return md5( wp_json_encode( $slice ) );

}



/**

 * Look up an existing accommodation post ID by booking system property_id.

 * Centralized so we don't have multiple definitions drifting apart.

 *

 * @param string $property_id

 * @return int 0 when not found

 */

function kv_find_accommodation_by_property_id( $property_id ) {

    $property_id = trim( (string) $property_id );

    if ( $property_id === '' ) {

        return 0;

    }

    $existing = get_post_id_by_typeId( $property_id, 'accommodation' );

    return $existing ? intval( $existing ) : 0;

}



/**

 * Process a single property payload with try/catch isolation and

 * change detection. Returns a status string used by the batch engine.

 *

 * @param array $property

 * @return array { status:'created'|'updated'|'skipped'|'failed', post_id:int, error:string, action:string }

 */

function kv_process_single_property( array $property ) {

    $result = [

        'status'  => 'failed',

        'action'  => 'failed',

        'post_id' => 0,

        'error'   => '',

    ];



    // Validate property structure

    if ( empty( $property['id'] ) ) {

        $result['error'] = 'Missing property id';

        return $result;

    }



    $property_id   = trim( (string) $property['id'] );

    $property_name = trim( wp_strip_all_tags( $property['client_property_name'] ?? ( $property['name'] ?? $property_id ) ) );



    $existing_post_id = kv_find_accommodation_by_property_id( $property_id );

    $is_new_post      = ( $existing_post_id === 0 );

    $new_hash         = kv_property_payload_hash( $property );



    // Change detection: skip if the post exists and the hash is identical.

    // Empty/missing old hash is treated as "no prior sync" → never skipped.

    if ( ! $is_new_post ) {

        $old_hash = (string) get_post_meta( $existing_post_id, '_kv_sync_hash', true );

        if ( $old_hash !== '' && $old_hash === $new_hash ) {

            $result['status']  = 'skipped';

            $result['action']  = 'skipped';

            $result['post_id'] = $existing_post_id;

            return $result;

        }

    }



    // Run the existing saver (which writes the post, meta, rooms, images, etc).

    // It expects an array of properties — we wrap our single property in one.

    try {

        // Reduce memory pressure before doing the heavy work.

        wp_suspend_cache_addition( true );

        sq_mapping_properties( [ $property ] );

        wp_suspend_cache_addition( false );



        // Resolve post ID again — it may have just been created.

        $post_id = kv_find_accommodation_by_property_id( $property_id );

        if ( ! $post_id ) {

            $result['error'] = 'Property processed but no post could be found afterwards';

            $result['action'] = 'failed';

            return $result;

        }



        update_post_meta( $post_id, '_kv_sync_hash', $new_hash );

        update_post_meta( $post_id, '_kv_sync_last_synced_at', current_time( 'Y-m-d H:i:s' ) );



        // ✅ FIX #2: distinguish create vs update so logs and counters

        // are accurate. If $existing_post_id was 0 going in, this was a

        // brand-new property being added. Otherwise it was an update.

        $result['status']  = $is_new_post ? 'created' : 'updated';

        $result['action']  = $result['status'];

        $result['post_id'] = $post_id;

        return $result;



    } catch ( Exception $e ) {

        wp_suspend_cache_addition( false );

        $result['error'] = 'Exception: ' . $e->getMessage();

        $result['action'] = 'failed';

        return $result;

    } catch ( Error $e ) {

        wp_suspend_cache_addition( false );

        $result['error'] = 'Fatal: ' . $e->getMessage();

        $result['action'] = 'failed';

        return $result;

    }

}



/**

 * Mark accommodations whose `property_id` is not in $seen_ids as draft.

 * Only invoked AFTER a full pass is complete, so it never falsely demotes

 * properties that simply haven't been processed yet.

 *

 * @param array $seen_ids Property IDs seen in the most recent pass

 * @return int Number of posts moved to draft

 */

function kv_draft_unseen_accommodations( array $seen_ids ) {

    global $wpdb;



    $seen_ids = array_filter( array_map( 'strval', $seen_ids ) );

    if ( empty( $seen_ids ) ) {

        return 0;

    }



    // Build placeholders for the IN clause.

    $placeholders = implode( ',', array_fill( 0, count( $seen_ids ), '%s' ) );



    // Find all accommodation posts that were last synced by us but are

    // not in the current pass's seen set.

    $query = $wpdb->prepare(

        "SELECT p.ID, pm.meta_value AS pid

         FROM {$wpdb->posts} p

         INNER JOIN {$wpdb->postmeta} pm

             ON pm.post_id = p.ID AND pm.meta_key = 'property_id'

         WHERE p.post_type = 'accommodation'

           AND p.post_status = 'publish'

           AND pm.meta_value NOT IN ($placeholders)",

        $seen_ids

    );



    $rows = $wpdb->get_results( $query );

    if ( empty( $rows ) ) {

        return 0;

    }



    $count = 0;

    foreach ( $rows as $row ) {

        $update = wp_update_post( [

            'ID'          => intval( $row->ID ),

            'post_status' => 'draft',

        ], true );

        if ( ! is_wp_error( $update ) ) {

            update_post_meta( intval( $row->ID ), '_kv_sync_stale_at', current_time( 'Y-m-d H:i:s' ) );

            $count++;

        }

    }

    return $count;

}



/**

 * Main sync batch runner. Fetches ONE page of properties, processes each

 * one in isolation, persists progress, and decides whether the pass is

 * complete (in which case stale properties are demoted to draft).

 *

 * Tunables:

 *   ?kv_sync_force=1   → ignore time budget, process a full pass in one go.

 *   ?kv_sync_reset=1   → wipe state and start over.

 *   ?kv_sync_per_page=N→ override batch size for this run.

 *

 * @return array Summary of the run

 */

function kv_cron_run_sync_batch() {

    $t0 = microtime( true );



    // Soft time budget per cron tick. We yield when we exceed this so the

    // server stays responsive. 20s is safe for a 30s PHP max_execution_time.

    $time_budget = 20;

    $force       = ! empty( $_GET['kv_sync_force'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $reset       = ! empty( $_GET['kv_sync_reset'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( $reset ) {

        kv_reset_sync_state();

    }



    $state = kv_get_sync_state();



    // If this is the first call of a new pass, mark it.

    $is_new_pass = empty( $state['is_running'] );

    if ( $is_new_pass ) {

        $state['is_running']      = 1;

        $state['pass_started_at'] = current_time( 'timestamp' );

        $state['seen_ids']        = [];

        $state['total_processed'] = 0;

        $state['total_created']   = 0;

        $state['total_updated']   = 0;

        $state['total_skipped']   = 0;

        $state['total_failed']    = 0;

    }



    $state['run_started_at'] = current_time( 'timestamp' );



    $per_page = isset( $_GET['kv_sync_per_page'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        ? max( 1, intval( $_GET['kv_sync_per_page'] ) )

        : max( 1, intval( $state['per_page'] ) );

    $state['per_page'] = $per_page;



    $page = max( 1, intval( $state['page'] ) );



    // Fetch one page.

    $response = hz_get_limited_properties( $page, $per_page );



    if ( $response === false || ! is_array( $response ) ) {

        // API failure → leave state intact so we retry next tick.

        $state['last_run_summary'] = sprintf( 'API fetch failed on page %d', $page );

        $state['last_run_at']      = current_time( 'timestamp' );

        kv_save_sync_state( $state );

        cf_log( $state['last_run_summary'], 'sync_api_fail', 'txt', false, true );

        return [

            'status'  => 'failed',

            'message' => $state['last_run_summary'],

        ];

    }



    $properties   = $response['properties'] ?? [];    

    $default_faqs = $response['faq'];

    $total_pages  = intval( $response['total_pages'] ?? 0 );



    // Process each property with per-record isolation.

    if ( ! empty( $properties ) ) {

        foreach ( $properties as $property ) {

            // Time-budget guard. Skip remaining items in this tick; state is

            // already saved so the next tick will resume after $last_id.

            if ( ! $force && ( microtime( true ) - $t0 ) > $time_budget ) {

                kv_save_sync_state( $state );

                $state['last_run_summary'] = sprintf(

                    'Yielded after %d items (time budget %ds)',

                    $state['total_processed'],

                    $time_budget

                );

                $state['last_run_at'] = current_time( 'timestamp' );

                kv_save_sync_state( $state );

                return [

                    'status'  => 'yielded',

                    'message' => $state['last_run_summary'],

                ];

            }



            $property_id = isset( $property['id'] ) ? trim( (string) $property['id'] ) : '';

            if ( $property_id === '' ) {

                continue;

            }



            $property_name = trim( wp_strip_all_tags( $property['client_property_name'] ?? ( $property['name'] ?? $property_id ) ) );



            $state['seen_ids'][] = $property_id;

            $state['total_processed']++;



            $outcome = kv_process_single_property( $property );



            switch ( $outcome['status'] ) {

                case 'created':

                    $state['total_created']++;

                    kv_sync_log_entry( [

                        'property_id'   => $property_id,

                        'property_name' => $property_name,

                        'status'        => 'created',

                        'error'         => '',

                    ] );

                    break;



                case 'updated':

                    $state['total_updated']++;

                    kv_sync_log_entry( [

                        'property_id'   => $property_id,

                        'property_name' => $property_name,

                        'status'        => 'updated',

                        'error'         => '',

                    ] );

                    break;



                case 'skipped':

                    $state['total_skipped']++;

                    kv_sync_log_entry( [

                        'property_id'   => $property_id,

                        'property_name' => $property_name,

                        'status'        => 'skipped',

                        'error'         => 'No changes since last sync',

                    ] );

                    break;



                default: // failed

                    $state['total_failed']++;

                    kv_sync_log_entry( [

                        'property_id'   => $property_id,

                        'property_name' => $property_name,

                        'status'        => 'failed',

                        'error'         => $outcome['error'] ?: 'Unknown error',

                    ] );

                    break;

            }



            $state['last_id'] = $property_id;

            $state['offset']++;



            // Free runtime cache every few records to keep memory bounded.

            if ( function_exists( 'wp_cache_flush_runtime' ) && ( $state['total_processed'] % 5 ) === 0 ) {

                wp_cache_flush_runtime();

            }

        }

    }



    if (!empty($default_faqs) && is_array($default_faqs)) {

        foreach ($default_faqs as $faq) {

            $bs_id = intval( $faq['id'] ?? 0 );

            if (!$bs_id) continue;



            $existing_faq = get_posts([

                'post_type'      => 'accomodation_faq',

                'meta_query'     => [

                    [

                        'key'     => 'bs_id',

                        'value'   => $bs_id,

                        'compare' => '=',

                    ],

                ],

                'posts_per_page' => 1,

                'fields'         => 'ids',

                'post_status'    => 'any',

            ]);



            $faq_post_id = !empty($existing_faq) ? $existing_faq[0] : 0;



            $faq_post_data = [

                'ID'           => $faq_post_id,

                'post_title'   => wp_strip_all_tags($faq['title']),

                'post_content' => html_entity_decode($faq['description']),

                'post_type'    => 'accomodation_faq',

                'post_status'  => 'publish',

            ];



            $updated_faq_id = wp_insert_post($faq_post_data);

            if (!is_wp_error($updated_faq_id)) {

                update_post_meta($updated_faq_id, 'bs_id', $bs_id);

            }

        }

    }

    // Decide whether the pass is complete.

    // We treat the pass as complete when the API tells us we've reached the

    // last page AND the returned page is short of `per_page`, OR when the

    // page we just fetched is empty.

    $page_is_short = count( $properties ) < $per_page;

    $last_page_hit = ( $total_pages > 0 && $page >= $total_pages );



    $pass_complete = $page_is_short || $last_page_hit || empty( $properties );



    if ( $pass_complete ) {

        // Run stale-property sweep.

        $drafted = kv_draft_unseen_accommodations( $state['seen_ids'] );



        $summary = sprintf(

            'Pass complete. processed=%d created=%d updated=%d skipped=%d failed=%d drafted=%d',

            $state['total_processed'],

            $state['total_created'],

            $state['total_updated'],

            $state['total_skipped'],

            $state['total_failed'],

            $drafted

        );



        kv_sync_log_entry( [

            'property_id'   => '-',

            'property_name' => 'PASS SUMMARY',

            'status'        => 'pass_complete',

            'error'         => $summary,

        ] );



        // Reset state for the next pass.

        kv_reset_sync_state();

        $state = kv_get_sync_state(); // get fresh defaults

        $state['last_run_summary'] = $summary;

        $state['last_run_at']      = current_time( 'timestamp' );

        kv_save_sync_state( $state );



        return [

            'status'  => 'pass_complete',

            'message' => $summary,

        ];

    }



    // Move to the next page and persist.

    $state['page'] = $page + 1;

    $state['last_run_summary'] = sprintf(

        'Batch ok. processed=%d created=%d updated=%d skipped=%d failed=%d next_page=%d',

        $state['total_processed'],

        $state['total_created'],

        $state['total_updated'],

        $state['total_skipped'],

        $state['total_failed'],

        $state['page']

    );

    $state['last_run_at'] = current_time( 'timestamp' );

    kv_save_sync_state( $state );



    return [

        'status'  => 'in_progress',

        'message' => $state['last_run_summary'],

    ];

}



// ✅ Register the new cron callback.

add_action( 'kv_cron_sync_accommodations', 'kv_cron_run_sync_batch' );



/**

 * Manual trigger / status endpoint.

 *  - ?kv_sync_run=1  → run one batch (respects time budget unless &kv_sync_force=1)

 *  - ?kv_sync_status=1 → print the current state as JSON

 *  - ?kv_sync_reset=1 → reset state

 *  - ?kv_sync_logs=1  → print last 100 log entries

 *

 *  Access is restricted to users that can manage_options.

 */

add_action( 'admin_init', 'kv_sync_manual_trigger' );

function kv_sync_manual_trigger() {

    if ( empty( $_GET['kv_sync_run'] ) && empty( $_GET['kv_sync_status'] ) && empty( $_GET['kv_sync_reset'] ) && empty( $_GET['kv_sync_logs'] ) ) {

        return;

    }

    if ( ! current_user_can( 'manage_options' ) ) {

        return;

    }



    if ( ! empty( $_GET['kv_sync_reset'] ) ) {

        kv_reset_sync_state();

        wp_safe_redirect( remove_query_arg( [ 'kv_sync_reset' ] ) );

        exit;

    }



    if ( ! empty( $_GET['kv_sync_status'] ) ) {

        $state = kv_get_sync_state();

        wp_send_json( $state );

    }



    if ( ! empty( $_GET['kv_sync_logs'] ) ) {

        $logs = get_option( 'kv_sync_logs', [] );

        $logs = array_slice( $logs, -100 );

        wp_send_json( $logs );

    }



    if ( ! empty( $_GET['kv_sync_run'] ) ) {

        $result = kv_cron_run_sync_batch();

        wp_send_json( $result );

    }

}





/* =====================================================================

 *  THREE-CRONS ARCHITECTURE

 * =====================================================================

 *

 *  The unified `kv_cron_sync_accommodations` cron has been retired in favor

 *  of three dedicated, single-purpose crons. Each one owns exactly one

 *  responsibility, runs on its own schedule, and uses its own state option.

 *

 *    1) ADD    — `kv_cron_add_accommodations`    every 2 minutes

 *       - Fetches a small page of recent API properties

 *       - Filters to IDs that DON'T exist locally yet

 *       - Inserts each one via sq_mapping_properties()

 *

 *    2) UPDATE — `kv_cron_update_accommodations` every 15 minutes

 *       - Iterates locally-known property IDs (in chunks)

 *       - Fetches each by ID via get-properties-by-ids

 *       - Skips when payload hash is unchanged; otherwise updates

 *       - NO insert / delete logic

 *

 *    3) STATUS — `kv_cron_status_accommodations` every 12 hours

 *       - Walks the FULL API list

 *       - Diff against local IDs

 *       - Demotes missing posts to draft (post_status='draft')

 *       - Demotes posts whose payload reports status=0 / is_enabled=0

 *       - Keeps the system in sync with what the API currently publishes

 *

 *  All three share:

 *    - kv_cron_accommodation_state_get($key)

 *    - kv_cron_accommodation_state_set($key, $value)

 *    - kv_cron_accommodation_state_reset($key)

 *    - kv_cron_log($cron, $entry)   (unified log with cron prefix)

 *

 * ===================================================================== */



/**

 * Read per-cron persistent state.

 *

 * @param string $key Cron identifier (e.g. 'add', 'update', 'status').

 * @return array

 */

function kv_cron_accommodation_state_get( $key ) {

    $defaults = [

        'is_running'       => 0,

        'started_at'       => 0,

        'last_run_at'      => 0,

        'last_run_summary' => '',

        'cursor'           => 0,   // pagination cursor

        'processed'        => 0,

        'created'          => 0,

        'updated'          => 0,

        'skipped'          => 0,

        'failed'           => 0,

        'drafted'          => 0,

    ];

    $opt_key = 'kv_cron_acc_state_' . sanitize_key( $key );

    $state   = get_option( $opt_key, [] );

    if ( ! is_array( $state ) ) {

        $state = [];

    }

    return array_merge( $defaults, $state );

}



function kv_cron_accommodation_state_set( $key, array $state ) {

    update_option( 'kv_cron_acc_state_' . sanitize_key( $key ), $state, false );

}



function kv_cron_accommodation_state_reset( $key ) {

    delete_option( 'kv_cron_acc_state_' . sanitize_key( $key ) );

}



/**

 * Unified log writer that prefixes entries with the cron name so they're

 * easy to filter in the admin or via grep.

 *

 * @param string $cron  Cron identifier.

 * @param array  $entry Log row (property_id, property_name, status, error, timestamp).

 */

function kv_cron_log( $cron, array $entry ) {

    $defaults = [

        'property_id'   => 'unknown',

        'property_name' => 'unknown',

        'status'        => 'unknown',

        'error'         => '',

        'timestamp'     => current_time( 'Y-m-d H:i:s' ),

        'cron'          => sanitize_key( $cron ),

    ];

    $entry = array_merge( $defaults, $entry );



    $logs   = get_option( 'kv_cron_logs', [] );

    $logs[] = $entry;

    if ( count( $logs ) > 1000 ) {

        $logs = array_slice( $logs, -1000 );

    }

    update_option( 'kv_cron_logs', $logs, false );



    error_log( sprintf(

        '[SYNC/%s] %s | Property %s (%s) | %s | %s',

        strtoupper( $cron ),

        $entry['timestamp'],

        $entry['property_id'],

        $entry['property_name'],

        strtoupper( $entry['status'] ),

        $entry['error'] ?: 'none'

    ) );

}



/**

 * Fetch a single API property by its ID. Reuses the existing endpoint.

 *

 * @param string|int $property_id

 * @return array|null Normalized property array or null on failure / not found

 */

function kv_cron_fetch_property_by_id( $property_id ) {

    $property_id = trim( (string) $property_id );

    if ( $property_id === '' ) {

        return null;

    }



    $apiUrl = add_query_arg( [

        'propertyIds' => $property_id,

    ], KV_BOOKING_SYSTEM_BASE . '/api/get-properties-by-ids' );



    $args = booking_sys_api_args();

    if ( empty( $args ) ) {

        return null;

    }



    $response = wp_remote_post( $apiUrl, $args );

    if ( is_wp_error( $response ) ) {

        return null;

    }



    $http_code = wp_remote_retrieve_response_code( $response );

    if ( $http_code !== 200 ) {

        return null;

    }



    $body = wp_remote_retrieve_body( $response );

    if ( empty( $body ) ) {

        return null;

    }



    $result = json_decode( $body, true );

    if ( ! is_array( $result ) || empty( $result['properties'] ) || ! is_array( $result['properties'] ) ) {

        return null;

    }



    foreach ( $result['properties'] as $p ) {

        if ( isset( $p['id'] ) && (string) $p['id'] === $property_id ) {

            return $p;

        }

    }

    return null;

}



/**

 * Soft time-budget helper shared by all three crons. Returns true when the

 * caller should yield control back to WP-Cron.

 *

 * @param float $t0        microtime() value at the start of the run

 * @param bool  $force     when true, never yield

 * @param int   $budget_s  soft budget in seconds

 * @return bool true when budget exceeded

 */

function kv_cron_time_budget_exceeded( $t0, $force, $budget_s = 20 ) {

    if ( $force ) {

        return false;

    }

    return ( microtime( true ) - $t0 ) > $budget_s;

}



/* ---------------------------------------------------------------------

 *  1) ADD CRON — detect + insert new properties only

 * --------------------------------------------------------------------- */



add_action( 'kv_cron_add_accommodations', 'kv_cron_run_add_batch' );



/**

 * Run one tick of the ADD cron.

 *

 * Tunables (GET params, manage_options only):

 *   ?kv_add_force=1       ignore time budget

 *   ?kv_add_reset=1       wipe state

 *   ?kv_add_per_page=N    page size for API fetch

 *

 * @return array Summary of the run

 */

function kv_cron_run_add_batch() {

    $cron    = 'add';

    $t0      = microtime( true );

    $force   = ! empty( $_GET['kv_add_force'] );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $reset   = ! empty( $_GET['kv_add_reset'] );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( $reset ) {

        kv_cron_accommodation_state_reset( $cron );

    }



    $state = kv_cron_accommodation_state_get( $cron );



    // Per-tick reset of counters — each cron tick is its own mini-run.

    $state['is_running']  = 1;

    $state['started_at']  = current_time( 'timestamp' );

    $state['processed']   = 0;

    $state['created']     = 0;

    $state['updated']     = 0;

    $state['skipped']     = 0;

    $state['failed']      = 0;



    $per_page = isset( $_GET['kv_add_per_page'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        ? max( 1, intval( $_GET['kv_add_per_page'] ) )

        : 20; // ADD cron pulls a wider window so it catches new entries fast



    // Always start from page 1 — we want to detect any new entries,

    // not walk a long cursor. Change detection is done by ID lookup.

    $page = 1;



    // Fetch one page.

    $response = hz_get_limited_properties( $page, $per_page );

    if ( $response === false || ! is_array( $response ) ) {

        $state['last_run_summary'] = 'ADD: API fetch failed';

        $state['last_run_at']      = current_time( 'timestamp' );

        kv_cron_accommodation_state_set( $cron, $state );

        kv_cron_log( $cron, [ 'status' => 'api_failed', 'error' => 'hz_get_limited_properties returned false' ] );

        return [ 'status' => 'failed', 'message' => $state['last_run_summary'] ];

    }



    $properties = $response['properties'] ?? [];

    if ( empty( $properties ) ) {

        $state['last_run_summary'] = 'ADD: API returned 0 properties';

        $state['last_run_at']      = current_time( 'timestamp' );

        kv_cron_accommodation_state_set( $cron, $state );

        return [ 'status' => 'noop', 'message' => $state['last_run_summary'] ];

    }



    foreach ( $properties as $property ) {

        if ( kv_cron_time_budget_exceeded( $t0, $force, 20 ) ) {

            $state['last_run_summary'] = sprintf( 'ADD: yielded after %d processed (budget)', $state['processed'] );

            $state['last_run_at']      = current_time( 'timestamp' );

            kv_cron_accommodation_state_set( $cron, $state );

            return [ 'status' => 'yielded', 'message' => $state['last_run_summary'] ];

        }



        $property_id = isset( $property['id'] ) ? trim( (string) $property['id'] ) : '';

        if ( $property_id === '' ) {

            continue;

        }



        $state['processed']++;



        $existing = kv_find_accommodation_by_property_id( $property_id );

        if ( $existing ) {

            // ADD cron doesn't touch existing posts. They're handled by the

            // UPDATE cron on its own schedule.

            $state['skipped']++;

            continue;

        }



        // Genuinely new — insert it.

        try {

            wp_suspend_cache_addition( true );

            sq_mapping_properties( [ $property ] );

            wp_suspend_cache_addition( false );



            $new_id = kv_find_accommodation_by_property_id( $property_id );

            if ( ! $new_id ) {

                $state['failed']++;

                kv_cron_log( $cron, [

                    'property_id'   => $property_id,

                    'property_name' => $property['name'] ?? $property_id,

                    'status'        => 'failed',

                    'error'         => 'Insert returned no post',

                ] );

                continue;

            }



            update_post_meta( $new_id, '_kv_sync_hash', kv_property_payload_hash( $property ) );

            update_post_meta( $new_id, '_kv_sync_last_added_at', current_time( 'Y-m-d H:i:s' ) );



            $state['created']++;

            kv_cron_log( $cron, [

                'property_id'   => $property_id,

                'property_name' => $property['name'] ?? $property_id,

                'status'        => 'created',

                'error'         => '',

            ] );

        } catch ( Exception $e ) {

            wp_suspend_cache_addition( false );

            $state['failed']++;

            kv_cron_log( $cron, [

                'property_id'   => $property_id,

                'property_name' => $property['name'] ?? $property_id,

                'status'        => 'failed',

                'error'         => 'Exception: ' . $e->getMessage(),

            ] );

        } catch ( Error $e ) {

            wp_suspend_cache_addition( false );

            $state['failed']++;

            kv_cron_log( $cron, [

                'property_id'   => $property_id,

                'property_name' => $property['name'] ?? $property_id,

                'status'        => 'failed',

                'error'         => 'Fatal: ' . $e->getMessage(),

            ] );

        }



        if ( function_exists( 'wp_cache_flush_runtime' ) && ( $state['processed'] % 5 ) === 0 ) {

            wp_cache_flush_runtime();

        }

    }



    $state['last_run_summary'] = sprintf(

        'ADD: processed=%d created=%d skipped=%d failed=%d',

        $state['processed'],

        $state['created'],

        $state['skipped'],

        $state['failed']

    );

    $state['last_run_at'] = current_time( 'timestamp' );

    kv_cron_accommodation_state_set( $cron, $state );



    return [

        'status'  => 'done',

        'message' => $state['last_run_summary'],

    ];

}



/* ---------------------------------------------------------------------

 *  2) UPDATE CRON — refresh existing properties only

 * --------------------------------------------------------------------- */



add_action( 'kv_cron_update_accommodations', 'kv_cron_run_update_batch' );



/**

 * Run one tick of the UPDATE cron.

 *

 * Tunables:

 *   ?kv_upd_force=1       ignore time budget

 *   ?kv_upd_reset=1       wipe state (restart from beginning)

 *   ?kv_upd_chunk=N       properties per tick (default 25)

 *   ?kv_upd_force_all=1   ignore change-detection hash and rewrite everything

 *

 * @return array Summary of the run

 */

function kv_cron_run_update_batch() {

    $cron      = 'update';

    $t0        = microtime( true );

    $force     = ! empty( $_GET['kv_upd_force'] );     // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $reset     = ! empty( $_GET['kv_upd_reset'] );     // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $force_all = ! empty( $_GET['kv_upd_force_all'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( $reset ) {

        kv_cron_accommodation_state_reset( $cron );

    }



    $state = kv_cron_accommodation_state_get( $cron );

    $state['is_running'] = 1;

    $state['started_at'] = current_time( 'timestamp' );

    if ( $reset ) {

        $state['processed'] = 0;

        $state['updated']   = 0;

        $state['skipped']   = 0;

        $state['failed']    = 0;

        $state['cursor']    = 0;

    }



    $chunk = isset( $_GET['kv_upd_chunk'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        ? max( 1, intval( $_GET['kv_upd_chunk'] ) )

        : 25;



    // Get one chunk of local accommodation IDs.

    $ids = kv_cron_get_local_property_ids_chunk( $state['cursor'], $chunk );



    if ( empty( $ids ) ) {

        // Cursor has run past the end → restart from the top next tick.

        $state['cursor']    = 0;

        $state['last_run_summary'] = 'UPDATE: end of cursor, will restart next tick';

        $state['last_run_at']      = current_time( 'timestamp' );

        kv_cron_accommodation_state_set( $cron, $state );

        return [ 'status' => 'noop', 'message' => $state['last_run_summary'] ];

    }



    foreach ( $ids as $property_id ) {

        if ( kv_cron_time_budget_exceeded( $t0, $force, 20 ) ) {

            $state['last_run_summary'] = sprintf( 'UPDATE: yielded at cursor %d (budget)', $state['cursor'] );

            $state['last_run_at']      = current_time( 'timestamp' );

            kv_cron_accommodation_state_set( $cron, $state );

            return [ 'status' => 'yielded', 'message' => $state['last_run_summary'] ];

        }



        $state['processed']++;



        // Fetch the property from API.

        $property = kv_cron_fetch_property_by_id( $property_id );

        if ( ! $property ) {

            // Not returned (could be deleted or transient API error). Leave

            // alone — the STATUS cron will demote it if it's permanently gone.

            $state['skipped']++;

            continue;

        }



        $existing_post_id = kv_find_accommodation_by_property_id( $property_id );

        if ( ! $existing_post_id ) {

            // Property was deleted locally between the cursor query and the API call.

            // Skip — ADD cron won't re-insert it because it doesn't see it either;

            // a manual re-add or full re-sync is required.

            $state['skipped']++;

            continue;

        }



        $new_hash = kv_property_payload_hash( $property );

        $old_hash = (string) get_post_meta( $existing_post_id, '_kv_sync_hash', true );



        // Skip when nothing changed (unless caller forced a full rewrite).

        if ( ! $force_all && $old_hash !== '' && $old_hash === $new_hash ) {

            $state['skipped']++;

            continue;

        }



        try {

            wp_suspend_cache_addition( true );

            sq_mapping_properties( [ $property ] );

            wp_suspend_cache_addition( false );



            update_post_meta( $existing_post_id, '_kv_sync_hash', $new_hash );

            update_post_meta( $existing_post_id, '_kv_sync_last_synced_at', current_time( 'Y-m-d H:i:s' ) );



            $state['updated']++;

            kv_cron_log( $cron, [

                'property_id'   => $property_id,

                'property_name' => $property['name'] ?? $property_id,

                'status'        => 'updated',

                'error'         => '',

            ] );

        } catch ( Exception $e ) {

            wp_suspend_cache_addition( false );

            $state['failed']++;

            kv_cron_log( $cron, [

                'property_id'   => $property_id,

                'property_name' => $property['name'] ?? $property_id,

                'status'        => 'failed',

                'error'         => 'Exception: ' . $e->getMessage(),

            ] );

        } catch ( Error $e ) {

            wp_suspend_cache_addition( false );

            $state['failed']++;

            kv_cron_log( $cron, [

                'property_id'   => $property_id,

                'property_name' => $property['name'] ?? $property_id,

                'status'        => 'failed',

                'error'         => 'Fatal: ' . $e->getMessage(),

            ] );

        }



        if ( function_exists( 'wp_cache_flush_runtime' ) && ( $state['processed'] % 5 ) === 0 ) {

            wp_cache_flush_runtime();

        }

    }



    // Advance the cursor by however many IDs we attempted this tick.

    $state['cursor']         += count( $ids );

    $state['last_run_summary'] = sprintf(

        'UPDATE: processed=%d updated=%d skipped=%d failed=%d cursor=%d',

        $state['processed'],

        $state['updated'],

        $state['skipped'],

        $state['failed'],

        $state['cursor']

    );

    $state['last_run_at'] = current_time( 'timestamp' );

    kv_cron_accommodation_state_set( $cron, $state );



    return [

        'status'  => 'done',

        'message' => $state['last_run_summary'],

    ];

}



/**

 * Get a chunk of locally-known accommodation property IDs ordered by ID so

 * the UPDATE cron can walk them deterministically.

 *

 * @param int $offset

 * @param int $limit

 * @return array<string>

 */

function kv_cron_get_local_property_ids_chunk( $offset = 0, $limit = 25 ) {

    global $wpdb;

    $offset = max( 0, intval( $offset ) );

    $limit  = max( 1, intval( $limit ) );



    $rows = $wpdb->get_col( $wpdb->prepare(

        "SELECT pm.meta_value

         FROM {$wpdb->postmeta} pm

         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id

         WHERE pm.meta_key = 'property_id'

           AND pm.meta_value <> ''

           AND p.post_type = 'accommodation'

         ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC, pm.meta_value ASC

         LIMIT %d OFFSET %d",

        $limit,

        $offset

    ) );



    return array_values( array_filter( array_map( 'strval', (array) $rows ) ) );

}



/* ---------------------------------------------------------------------

 *  3) STATUS / DELETE CRON — mark missing/inactive posts as draft

 * --------------------------------------------------------------------- */



add_action( 'kv_cron_status_accommodations', 'kv_cron_run_status_batch' );



/**

 * Run one tick of the STATUS cron.

 *

 * Walks the API in pages, accumulates the FULL set of currently-published

 * IDs, then compares them against local IDs. Any post whose property_id is

 * NOT in the API set is moved to draft. Posts whose API payload reports

 * `status=0` or `is_enabled=0` are also moved to draft.

 *

 * Tunables:

 *   ?kv_st_force=1       ignore time budget

 *   ?kv_st_reset=1       wipe state

 *   ?kv_st_dry_run=1     report what would be drafted without writing

 *

 * @return array Summary

 */

function kv_cron_run_status_batch() {

    $cron    = 'status';

    $t0      = microtime( true );

    $force   = ! empty( $_GET['kv_st_force'] );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $reset   = ! empty( $_GET['kv_st_reset'] );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $dry_run = ! empty( $_GET['kv_st_dry_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended



    if ( $reset ) {

        kv_cron_accommodation_state_reset( $cron );

    }



    $state = kv_cron_accommodation_state_get( $cron );

    $state['is_running'] = 1;

    $state['started_at'] = current_time( 'timestamp' );



    // Cached, transient-stored API snapshot — survives across cron ticks.

    $api_ids = $state['api_ids'] ?? [];

    $inactive_ids = $state['inactive_ids'] ?? [];

    $current_page = max( 1, intval( $state['cursor'] ?: 1 ) );

    $per_page     = 50;

    $total_pages  = intval( $state['total_pages'] ?? 0 );



    // Continue paginating the API until we hit the end.

    if ( empty( $api_ids ) || $current_page <= max( 1, $total_pages ) ) {

        $response = hz_get_limited_properties( $current_page, $per_page );

        if ( $response === false || ! is_array( $response ) ) {

            $state['last_run_summary'] = 'STATUS: API fetch failed';

            $state['last_run_at']      = current_time( 'timestamp' );

            kv_cron_accommodation_state_set( $cron, $state );

            kv_cron_log( $cron, [ 'status' => 'api_failed', 'error' => 'hz_get_limited_properties returned false' ] );

            return [ 'status' => 'failed', 'message' => $state['last_run_summary'] ];

        }



        $properties  = $response['properties'] ?? [];

        $total_pages = intval( $response['total_pages'] ?? 0 );

        $state['total_pages'] = $total_pages;



        foreach ( $properties as $p ) {

            if ( empty( $p['id'] ) ) {

                continue;

            }

            $pid = (string) $p['id'];

            $api_ids[ $pid ] = true;



            // If the API says this property is disabled/inactive, record it.

            $is_inactive = ( isset( $p['is_enabled'] ) && intval( $p['is_enabled'] ) === 0 )

                        || ( isset( $p['status'] ) && intval( $p['status'] ) === 0 );

            if ( $is_inactive ) {

                $inactive_ids[ $pid ] = true;

            }

        }



        $state['api_ids']      = $api_ids;

        $state['inactive_ids'] = $inactive_ids;

        $state['cursor']       = $current_page + 1;

        kv_cron_accommodation_state_set( $cron, $state );



        if ( kv_cron_time_budget_exceeded( $t0, $force, 25 ) ) {

            $state['last_run_summary'] = sprintf(

                'STATUS: paginating (page %d/%d) yielded',

                $current_page, $total_pages

            );

            $state['last_run_at'] = current_time( 'timestamp' );

            kv_cron_accommodation_state_set( $cron, $state );

            return [ 'status' => 'yielded', 'message' => $state['last_run_summary'] ];

        }



        // Not at the end yet — keep going next tick.

        if ( $current_page < max( 1, $total_pages ) ) {

            $state['last_run_summary'] = sprintf(

                'STATUS: pagination continues (page %d/%d)',

                $current_page + 1, $total_pages

            );

            $state['last_run_at'] = current_time( 'timestamp' );

            kv_cron_accommodation_state_set( $cron, $state );

            return [ 'status' => 'in_progress', 'message' => $state['last_run_summary'] ];

        }

    }



    // Pagination finished → perform reconciliation.

    $api_ids_list      = array_keys( $api_ids );

    $inactive_ids_list = array_keys( $inactive_ids );



    // Build the list of property_ids to draft:

    //   a) any local property_id not returned by the API

    //   b) any local property_id whose API entry is inactive

    $missing_locally = kv_cron_get_property_ids_not_in( $api_ids_list );

    $missing_ids     = array_merge( $missing_locally, $inactive_ids_list );

    $missing_ids     = array_values( array_unique( array_filter( array_map( 'strval', $missing_ids ) ) ) );



    $drafted = 0;

    if ( ! empty( $missing_ids ) ) {

        $drafted = kv_cron_draft_property_ids( $missing_ids, $dry_run );

    }



    // Log a per-pass summary.

    kv_cron_log( $cron, [

        'property_id'   => '-',

        'property_name' => 'STATUS PASS',

        'status'        => $dry_run ? 'dry_run_complete' : 'pass_complete',

        'error'         => sprintf(

            'api_total=%d missing_or_inactive=%d drafted=%d',

            count( $api_ids_list ),

            count( $missing_ids ),

            $drafted

        ),

    ] );



    $summary = sprintf(

        'STATUS: api_total=%d missing_or_inactive=%d drafted=%d%s',

        count( $api_ids_list ),

        count( $missing_ids ),

        $drafted,

        $dry_run ? ' (DRY RUN)' : ''

    );



    $state['last_run_summary'] = $summary;

    $state['last_run_at']      = current_time( 'timestamp' );

    $state['processed']        = count( $api_ids_list );

    $state['drafted']          = $drafted;

    // Reset for the next pass — the API snapshot is intentionally not kept.

    $state['cursor']       = 1;

    $state['api_ids']      = [];

    $state['inactive_ids'] = [];

    $state['total_pages']  = 0;

    kv_cron_accommodation_state_set( $cron, $state );



    return [

        'status'  => 'pass_complete',

        'message' => $summary,

    ];

}



/**

 * Return all local property_id values that are NOT present in $api_ids.

 *

 * @param array $api_ids Array of property IDs currently exposed by the API.

 * @return array<string>

 */

function kv_cron_get_property_ids_not_in( array $api_ids ) {

    global $wpdb;

    $api_ids = array_filter( array_map( 'strval', $api_ids ) );

    if ( empty( $api_ids ) ) {

        return [];

    }



    // Fetch every local property_id (could be a few hundred — fine for a

    // twice-daily cron). For very large catalogs this can be swapped for

    // a chunked diff without changing the rest of the flow.

    $rows = $wpdb->get_col(

        "SELECT meta_value

         FROM {$wpdb->postmeta} pm

         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id

         WHERE pm.meta_key = 'property_id'

           AND pm.meta_value <> ''

           AND p.post_type = 'accommodation'"

    );



    $local = array_values( array_unique( array_filter( array_map( 'strval', (array) $rows ) ) ) );

    return array_values( array_diff( $local, $api_ids ) );

}



/**

 * Demote the accommodation posts whose property_id is in $ids to draft.

 *

 * @param array $ids

 * @param bool  $dry_run

 * @return int Number of posts actually drafted

 */

function kv_cron_draft_property_ids( array $ids, $dry_run = false ) {

    global $wpdb;



    $ids = array_values( array_unique( array_filter( array_map( 'strval', $ids ) ) ) );

    if ( empty( $ids ) ) {

        return 0;

    }



    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );



    // Only touch posts that are currently publish; drafts/trash are

    // intentionally left alone (they're already hidden from the site).

    $rows = $wpdb->get_results( $wpdb->prepare(

        "SELECT p.ID, pm.meta_value AS pid

         FROM {$wpdb->posts} p

         INNER JOIN {$wpdb->postmeta} pm

             ON pm.post_id = p.ID AND pm.meta_key = 'property_id'

         WHERE p.post_type = 'accommodation'

           AND p.post_status = 'publish'

           AND pm.meta_value IN ($placeholders)",

        $ids

    ) );



    if ( empty( $rows ) ) {

        return 0;

    }



    $count = 0;

    foreach ( $rows as $row ) {

        if ( $dry_run ) {

            $count++;

            continue;

        }

        $update = wp_update_post( [

            'ID'          => intval( $row->ID ),

            'post_status' => 'draft',

        ], true );

        if ( ! is_wp_error( $update ) ) {

            update_post_meta( intval( $row->ID ), '_kv_sync_stale_at', current_time( 'Y-m-d H:i:s' ) );

            kv_cron_log( 'status', [

                'property_id'   => (string) $row->pid,

                'property_name' => get_the_title( intval( $row->ID ) ),

                'status'        => 'drafted',

                'error'         => 'Missing or inactive in API',

            ] );

            $count++;

        }

    }

    return $count;

}



/* ---------------------------------------------------------------------

 *  Manual admin triggers for the three crons

 * --------------------------------------------------------------------- */



add_action( 'admin_init', 'kv_cron_manual_triggers' );

function kv_cron_manual_triggers() {

    $flags = [

        'kv_add_run', 'kv_add_status', 'kv_add_reset',

        'kv_upd_run', 'kv_upd_status', 'kv_upd_reset',

        'kv_st_run',  'kv_st_status',  'kv_st_reset',

        'kv_cron_logs',

    ];

    $hit = false;

    foreach ( $flags as $f ) {

        if ( ! empty( $_GET[ $f ] ) ) { $hit = true; break; }

    }

    if ( ! $hit ) {

        return;

    }

    if ( ! current_user_can( 'manage_options' ) ) {

        return;

    }



    // Logs (shared across all three crons)

    if ( ! empty( $_GET['kv_cron_logs'] ) ) {

        $logs = get_option( 'kv_cron_logs', [] );

        $logs = array_slice( $logs, -100 );

        wp_send_json( $logs );

    }



    // ADD

    if ( ! empty( $_GET['kv_add_reset'] ) ) {

        kv_cron_accommodation_state_reset( 'add' );

        wp_safe_redirect( remove_query_arg( [ 'kv_add_reset' ] ) ); exit;

    }

    if ( ! empty( $_GET['kv_add_status'] ) ) {

        wp_send_json( kv_cron_accommodation_state_get( 'add' ) );

    }

    if ( ! empty( $_GET['kv_add_run'] ) ) {

        wp_send_json( kv_cron_run_add_batch() );

    }



    // UPDATE

    if ( ! empty( $_GET['kv_upd_reset'] ) ) {

        kv_cron_accommodation_state_reset( 'update' );

        wp_safe_redirect( remove_query_arg( [ 'kv_upd_reset' ] ) ); exit;

    }

    if ( ! empty( $_GET['kv_upd_status'] ) ) {

        wp_send_json( kv_cron_accommodation_state_get( 'update' ) );

    }

    if ( ! empty( $_GET['kv_upd_run'] ) ) {

        wp_send_json( kv_cron_run_update_batch() );

    }



    // STATUS

    if ( ! empty( $_GET['kv_st_reset'] ) ) {

        kv_cron_accommodation_state_reset( 'status' );

        wp_safe_redirect( remove_query_arg( [ 'kv_st_reset' ] ) ); exit;

    }

    if ( ! empty( $_GET['kv_st_status'] ) ) {

        wp_send_json( kv_cron_accommodation_state_get( 'status' ) );

    }

    if ( ! empty( $_GET['kv_st_run'] ) ) {

        wp_send_json( kv_cron_run_status_batch() );

    }

}

