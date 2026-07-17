<?php

// define('KV_BOOKING_SYSTEM_BASE', 'https://trip.japanskiexperience.com');

// define('KV_BS_authToken', '8739|UjozT7ySSpts5CS0cKdoaGAvtou3JEeLWHemt4c9ac7a433c');

$is_stay_user = !is_user_logged_in() || get_current_user_id() != 14 ? true : false;

// $external_url = 'https://trip.japanskiexperience.com';

// $authToken = '12498|nxYGHNDbeM400rHiOftxkjGcI0wKVZ41APd22jBJfa490d93';

$external_url = 'https://stay.japanskiexperience.com'; $authToken = '12810|nNp9PKBDHe6e9W5AYBzVwFpONivDUIBsHpQmRCcLe4d9bfbb'; //stay.japanskiexperience.com
// $external_url = 'https://trip.japanskiexperience.com'; $authToken = '12594|V1sCeHAYmb17icD6v9JDqCrA2MpmSdJgxzFYWYMWe4de053e'; // trip.japanskiexperience.com
// pre( $external_url );


define('KV_BOOKING_SYSTEM_BASE', $external_url);

define('KV_BS_authToken', $authToken); // This token seems to be duplicated in h_functions.php as well. Consider centralizing.

define('KV_ROOMBOSS_BASE', 'https://api.roomboss.com/extws');

define('KV_ROOMBOSS_USER', 'JSE');

define('KV_ROOMBOSS_PASS', 'B1DcyHegA5TRRHjF');

function get_accommodation_base_areas( $resort = null ) {

    $global_allowed_areas = hz_get_global_accommodation_whitelist();

    $areas = [];



    // Check if we should return all areas (if no resort provided or if it's a global keyword like 'offers')

    $is_global = empty($resort) || in_array(str_replace('-accommodation', '', $resort), ['offers', 'deals', 'accommodation']);



    if ( $is_global ) {

        // If no resort is provided, collect all allowed child names from all resorts in the whitelist

        $allowed_child_names = [];

        foreach ( $global_allowed_areas as $resort_areas ) {

            if ( is_array( $resort_areas ) ) {

                $allowed_child_names = array_merge( $allowed_child_names, $resort_areas );

            }

        }

        $allowed_child_names = array_unique( $allowed_child_names );



        // Fetch all categories to check against the flat whitelist

        $child_terms = get_terms( [

            'taxonomy'   => 'accommodation-cat',

            'hide_empty' => false,

        ] );

    } else {

        // Attempt to find the parent term by slug.

        $parent_term = get_term_by( 'slug', $resort, 'accommodation-cat' );



        if ( ! $parent_term ) {

            $parent_term = get_term_by( 'slug', $resort . '-accommodation', 'accommodation-cat' );

        }



        if ( ! $parent_term || is_wp_error( $parent_term ) ) {

            return [];

        }



        // Fetch only child categories for the specific resort.

        $child_terms = get_terms( [

            'taxonomy'   => 'accommodation-cat',

            'parent'     => $parent_term->term_id,

            'hide_empty' => false,

        ] );



        $parent_term_name = str_replace(' Accommodation', '', sanitize_text_field($parent_term->name));

        $allowed_child_names = $global_allowed_areas[$parent_term_name] ?? [];

    }



    if ( is_wp_error( $child_terms ) || empty( $child_terms ) ) {

        return [];

    }



    foreach ( $child_terms as $term ) {

        $child_term_name = sanitize_text_field($term->name);

        // Only add child terms if their name is in the allowed list

        if (in_array($child_term_name, $allowed_child_names)) {

            $areas[$term->slug] = $child_term_name;

        }

    }



    // Sort areas by name for consistent display

    asort($areas);



    return $areas;

}



add_action('wp_ajax_niseko_search', 'niseko_search');

add_action('wp_ajax_nopriv_niseko_search', 'niseko_search');



/**

 * Format date from one format to another

 * 

 * @param string $date_str - Date string

 * @param string $newFormat - Target format (default 'Y-m-d')

 * @param string $format - Source format (default 'Ymd')

 * @return string - Formatted date or original string if parsing fails

 */

function date_format_readable($date_str, $newFormat = 'Y-m-d', $format = 'Y-m-d') {

    $date = DateTime::createFromFormat($format, $date_str);

    if ($date) {

        return $date->format($newFormat);

    }

    return $date_str;

}



/**

 * Main search dispatcher - routes to booking system or local search

 * Validates input and delegates to appropriate search function

 * 

 * @return void Sends JSON response

 */

function niseko_search() {

    try {

        // ✅ STEP 1: Validate POST data exists

        if (empty($_POST)) {

            return wp_send_json_error([

                'message' => 'No search parameters provided',

                'code' => 'missing_post_data'

            ], 400);

        }



        $selected_resort = isset($_POST['resort']) ? sanitize_text_field($_POST['resort']) : '';

        // if (!empty($selected_resort)) {

            // Normalize the resort slug (ensure lowercase and remove suffix)

        $resort_base = str_replace('-accommodation', '/', strtolower($selected_resort));

        $referer = wp_get_referer();

        

        if ($referer) {

            $referer_path = parse_url($referer, PHP_URL_PATH);

            // The expected URL structure is /{resort}/accommodation/

            $required_segment = '/' . $resort_base . 'accommodation/';

            

            // If the referer path doesn't strictly contain the resort + /accommodation/ segment, trigger redirect

            if (strpos($referer_path, $required_segment) === false) {

                return wp_send_json_success([

                    'redirect' => home_url($required_segment),

                    'message'  => 'Redirecting to ' . ucfirst($resort_base) . ' accommodation...'

                ]);

            }

        }

        // }

        // ✅ STEP 2: Validate and sanitize date parameters

        $checkin = isset($_POST['checkin']) ? sanitize_text_field($_POST['checkin']) : '';

        $checkout = isset($_POST['checkout']) ? sanitize_text_field($_POST['checkout']) : '';



        $has_dates = !empty($checkin) && !empty($checkout);



        // ✅ STEP 3: Check if hotel/booking system search is requested

        $is_hotel_search = !empty($_POST['hotel_search']);



        // ✅ STEP 4: Route to appropriate search function

        if ($has_dates && $is_hotel_search) {

            // Use Booking System API for hotel search

            hz_search_in_booking_system();

        } else {

            // Fall back to local WordPress search

            niseko_search_local();

        }



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        return wp_send_json_error([

            'message' => 'An unexpected error occurred during search',

            'code' => 'unexpected_error',

            'details' => $e->getMessage()

        ], 500);

    }

}



/**

 * Search accommodations using local WordPress database

 * Queries accommodation posts matching user filters and returns paginated results

 * 

 * @return void Sends JSON response with HTML and pagination info

 */

function niseko_search_local()

{

    try {

        // ✅ STEP 1: Validate and build search query arguments

        if (empty($_POST)) {

            return wp_send_json_error([

                'message' => 'No search parameters provided',

                'code' => 'missing_search_params'

            ], 400);

        }



        $args = niseko_build_search_query_args($_POST);

        $all_acc_args = niseko_build_search_query_args($_POST, ['posts_per_page' => -1]);



        // Apply price filter on local min_room_price for date-free searches

        $price_min_local = isset($_POST['price_min']) && $_POST['price_min'] !== '' ? (int) $_POST['price_min'] : 0;

        $price_max_local = isset($_POST['price_max']) && $_POST['price_max'] !== '' ? (int) $_POST['price_max'] : 0;



        if ($price_min_local > 0 && $price_max_local > 0) {

            $price_clause = [

                'key'     => 'min_room_price',

                'value'   => [$price_min_local, $price_max_local],

                'type'    => 'NUMERIC',

                'compare' => 'BETWEEN',

            ];

            $args['meta_query'][]     = $price_clause;

            $all_acc_args['meta_query'][] = $price_clause;

        } elseif ($price_min_local > 0) {

            $price_clause = [

                'key'     => 'min_room_price',

                'value'   => $price_min_local,

                'type'    => 'NUMERIC',

                'compare' => '>=',

            ];

            $args['meta_query'][]     = $price_clause;

            $all_acc_args['meta_query'][] = $price_clause;

        } elseif ($price_max_local > 0) {

            $price_clause = [

                'key'     => 'min_room_price',

                'value'   => $price_max_local,

                'type'    => 'NUMERIC',

                'compare' => '<=',

            ];

            $args['meta_query'][]     = $price_clause;

            $all_acc_args['meta_query'][] = $price_clause;

        }



        if (!is_array($args) || empty($args)) {

            return wp_send_json_error([

                'message' => 'Failed to build search query',

                'code' => 'query_build_failed'

            ], 500);

        }

        $exclude_post_ids = kv_niseko_parse_exclude_ids( $_POST['exclude_post_ids'] ?? [] );
        $per_page = isset( $args['posts_per_page'] ) ? max( 1, (int) $args['posts_per_page'] ) : 6;
        $pagination_page = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;

        // Stable remaining-slice pagination (avoids WP offset/paged duplicates).
        $id_args = niseko_build_search_query_args(
            $_POST,
            [
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'paged'          => 1,
            ]
        );
        unset( $id_args['offset'] );

        if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
            $id_args['meta_query'] = $args['meta_query'];
        }

        $all_post_ids = get_posts( $id_args );
        if ( ! is_array( $all_post_ids ) ) {
            $all_post_ids = [];
        }
        $all_post_ids = array_values( array_unique( array_map( 'intval', $all_post_ids ) ) );

        if ( ! empty( $exclude_post_ids ) ) {
            $exclude_lookup = array_flip( $exclude_post_ids );
            $all_post_ids   = array_values(
                array_filter(
                    $all_post_ids,
                    static function ( $post_id ) use ( $exclude_lookup ) {
                        return ! isset( $exclude_lookup[ (int) $post_id ] );
                    }
                )
            );
            $slice_ids       = array_slice( $all_post_ids, 0, $per_page );
            $pagination_page = 1;
        } else {
            $offset    = ( $pagination_page - 1 ) * $per_page;
            $slice_ids = array_slice( $all_post_ids, $offset, $per_page );
        }

        $found_posts = count( $all_post_ids ) + count( $exclude_post_ids );
        $has_more    = ! empty( $exclude_post_ids )
            ? count( $all_post_ids ) > $per_page
            : ( ( ( $pagination_page - 1 ) * $per_page ) + count( $slice_ids ) ) < count( $all_post_ids );

        if ( empty( $slice_ids ) ) {
            $query = new WP_Query( [ 'post__in' => [ 0 ] ] );
        } else {
            $query = new WP_Query(
                [
                    'post_type'      => 'accommodation',
                    'post_status'    => 'publish',
                    'posts_per_page' => count( $slice_ids ),
                    'post__in'       => $slice_ids,
                    'orderby'        => 'post__in',
                ]
            );
        }

        // Preserve price metadata usage below via found counts.
        $args['paged'] = $pagination_page;
        $args['posts_per_page'] = $per_page;



        if (!is_object($query) || !isset($query->posts)) {

            return wp_send_json_error([

                'message' => 'Failed to query accommodations',

                'code' => 'query_execution_failed'

            ], 500);

        }



        // ✅ STEP 3: Build accommodation HTML output

        ob_start();



        $current_path = $_POST['current_url'];

        

        if ($query->have_posts()) {

            $count = 0;

            while ($query->have_posts()) {

                $query->the_post();

                $count++;



                $top_label = '';

                global $wp;

                // Get current clean path

                /* check if url ends with "/deals" excluding query params */

                if (strpos($current_path, '/deals') !== false){

                    if (have_rows('rate_plan', get_the_ID())) {

                        while (have_rows('rate_plan', get_the_ID())) {

                            the_row();

                            $rate_plan_name = get_sub_field('rate_plan_name');

                            if( !empty( $rate_plan_name ) ){

                                if (stripos($rate_plan_name, 'discount') !== false) {

                                    $top_label = wp_strip_all_tags( $rate_plan_name, true );

                                    break; // stop at first match

                                }

                            }

                        }



                        // reset repeater loop

                        reset_rows();

                    }

                }



                get_template_part('partials/accommodation-box', null, [

                    'top_label' => $top_label,

                ] );



                if ($count === 3 && empty( $exclude_post_ids ) && $pagination_page === 1) {

                    echo kv_get_expert_recommendation_cta();

                }



                if ($count === 9 && empty( $exclude_post_ids ) && $pagination_page === 1) {

                    echo kv_get_stronger_recommendation_cta();

                }

            }

        } else {

            echo '<p class="no-results">No accommodation found.</p>';

        }



        $html_output = ob_get_clean();

        wp_reset_postdata();



        // ✅ STEP 4: Get all hotel IDs for room count calculation

        $all_acc_query = new WP_Query($all_acc_args);

        $hotel_ids = [];

        $room_count = 0;



        if (!empty($all_acc_query->posts) && is_array($all_acc_query->posts)) {

            foreach ($all_acc_query->posts as $post) {

                if (!is_object($post) || empty($post->ID)) {

                    continue;

                }



                $hotel_id = get_field('acc_hotel_id', $post->ID);

                if (!empty($hotel_id)) {

                    $hotel_ids[] = $hotel_id;

                }

            }

        }



        // ✅ STEP 5: Query related room counts

        if (!empty($hotel_ids)) {

            $room_args = [

                'post_type'      => 'japan_rooms',

                'posts_per_page' => -1,

                'fields'         => 'ids',

                'meta_query'     => [

                    [

                        'key'     => 'room_hotel_id',

                        'value'   => $hotel_ids,

                        'compare' => 'IN',

                    ],

                ],

            ];



            $room_query = new WP_Query($room_args);

            $room_count = isset($room_query->found_posts) ? (int) $room_query->found_posts : 0;

        }



        // ✅ STEP 6: Return paginated results with metadata

        $enquiry_html = get_acc_enquiry_form();

        return wp_send_json_success([

            'html'      => $html_output,

            'count'     => (int) $found_posts,

            'room_count' => $room_count,

            'has_more'  => (bool) $has_more,
            
            'form_html'  => $enquiry_html,

        ]);



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        return wp_send_json_error([

            'message' => 'An unexpected error occurred during search',

            'code' => 'unexpected_error',

            'details' => $e->getMessage()

        ], 500);

    }

}



/**

 * Search availability in Booking System

 * Validates dates, guests, and retrieves available accommodation from booking system

 * 

 * @return void Sends JSON response

 */

function hz_search_in_booking_system() {

    try {

        $checkin_raw = isset($_POST['checkin']) ? sanitize_text_field($_POST['checkin']) : '';

        $checkout_raw = isset($_POST['checkout']) ? sanitize_text_field($_POST['checkout']) : '';



        if (empty($checkin_raw) || empty($checkout_raw)) {

            return wp_send_json_error([

                'message' => 'Check-in and check-out dates are required',

                'code' => 'missing_dates'

            ], 400);

        }



        $checkInObj = DateTime::createFromFormat('d/m/Y', $checkin_raw);

        $checkOutObj = DateTime::createFromFormat('d/m/Y', $checkout_raw);



        if (!$checkInObj || !$checkOutObj) {

            return wp_send_json_error([

                'message' => 'Invalid date format. Please use DD/MM/YYYY',

                'code' => 'invalid_date_format'

            ], 400);

        }



        if ($checkOutObj <= $checkInObj) {

            return wp_send_json_error([

                'message' => 'Check-out date must be after check-in date',

                'code' => 'invalid_date_range'

            ], 400);

        }



        $guests = intval($_POST['guests']);



        if ($guests < 1 || $guests > 20) {

            return wp_send_json_error([

                'message' => 'Guest count must be between 1 and 20',

                'code' => 'invalid_guest_count'

            ], 400);

        }



        $checkIn = hz_convert_date_format($checkin_raw, 'd-M-Y');

        $checkOut = hz_convert_date_format($checkout_raw, 'd-M-Y');



        if (!$checkIn || !$checkOut) {

            return wp_send_json_error([

                'message' => 'Failed to process dates',

                'code' => 'date_conversion_failed'

            ], 500);

        }



        $localHotelData = hz_get_local_property_ids_and_args($_POST);



        if (!is_array($localHotelData)) {

            return wp_send_json_error([

                'message' => 'Failed to retrieve property data',

                'code' => 'property_retrieval_failed'

            ], 500);

        }



        $hotel_ids = $localHotelData['ids'] ?? [];

        $args = $localHotelData['args'] ?? [];



        if (empty($hotel_ids)) {

            return wp_send_json_success(array_merge([

                'html' => '<p>No properties match your search criteria. Please try different filters.</p>',

                'count' => 0,

                'room_count' => 0,

                'has_more' => false,

                'form_html' => '',

            ], kv_roomboss_frontend_pagination_fields(kv_roomboss_get_pagination_meta([], $args))));

        }



        $roombossPayload = kv_roomboss_available_ids_cached($hotel_ids, $args, true);



        if (is_wp_error($roombossPayload)) {

            return wp_send_json_error([

                'message' => 'Error retrieving availability: ' . $roombossPayload->get_error_message(),

                'code' => 'availability_query_failed'

            ], 500);

        }



        $roombossData = $roombossPayload['results'] ?? [];

        $bookingPagination = $roombossPayload['pagination'] ?? kv_roomboss_get_pagination_meta([], $args);

        $paginationFields = kv_roomboss_frontend_pagination_fields($bookingPagination);



        $availableHotelIds = !empty($roombossData) && is_array($roombossData)
            ? array_keys($roombossData)
            : [];

        $booking_offset = isset($_POST['booking_offset']) ? max(0, intval($_POST['booking_offset'])) : 0;



        $exclude_price_property_ids = [];

        $bedbank_property_ids = [];



        // Always keep the same property universe for booking_offset 0 across WP pages.
        // Injecting price-excluded hotels only on page=1 made pages 2+ use a different ID list,
        // which caused Load More to re-serve already shown properties.
        if ( $booking_offset === 0 ) {

            $exclude_price_args = niseko_build_search_query_args($_POST, [

                'fields'         => 'ids',

                'posts_per_page' => -1,

                'paged'          => 1,

            ]);



            $exclude_price_args['meta_query'][] = [

                'key'     => 'is_price_excluded',

                'value'   => '1',

                'compare' => '=',

            ];



            $exclude_price_query = get_posts($exclude_price_args);



            $exclude_price_property_ids = array_filter(array_map(function($exclude_price_post) {

                return get_post_meta($exclude_price_post, 'property_id', true);

            }, $exclude_price_query));



            // BedBank properties are enquiry-based and often have no live Unit count.
            // Include them in date search results even when the booking API omits them.
            $bedbank_args = niseko_build_search_query_args($_POST, [

                'fields'         => 'ids',

                'posts_per_page' => -1,

                'paged'          => 1,

            ]);



            $bedbank_args['meta_query'][] = [

                'relation' => 'OR',

                [

                    'key'     => 'is_roomboss',

                    'value'   => '1',

                    'compare' => '!=',

                ],

                [

                    'key'     => 'is_roomboss',

                    'compare' => 'NOT EXISTS',

                ],

            ];



            $bedbank_query = get_posts($bedbank_args);



            $bedbank_property_ids = array_filter(array_map(function ($bedbank_post) {

                return get_post_meta($bedbank_post, 'property_id', true);

            }, $bedbank_query));



        }



        $merged_property_id = array_values(array_unique( array_merge( $exclude_price_property_ids, $bedbank_property_ids, $availableHotelIds ) ));



        if (empty($merged_property_id)) {

            return wp_send_json_success(array_merge([

                'html' => '',

                'count' => 0,

                'room_count' => 0,

                'has_more' => false,

            ], $paginationFields));

        }



        // cf_log( $merged_property_id, 'merged_property_id' );



        niseko_search_roomboss_wp($merged_property_id, $roombossData, $bookingPagination);



    } catch (Exception $e) {

        return wp_send_json_error([

            'message' => 'An unexpected error occurred while searching',

            'code' => 'unexpected_error',

            'details' => $e->getMessage()

        ], 500);

    }

}



function get_room_count_from_roomboss_hotels($hotel_ids)

{

    $hotel_ids = empty( $hotel_ids ) ? [0] : $hotel_ids;

    $rooms_query = new WP_Query([

        'post_type'      => 'japan_rooms',

        'post_status'    => 'publish',

        'posts_per_page' => -1,

        'fields'         => 'ids',

        'meta_query'     => [

            [

                'key'     => 'property_id',

                'value'   => $hotel_ids,

                'compare' => 'IN',

            ],

        ],

    ]);

    return (int)$rooms_query->found_posts ?? 0;

    // return $rooms_query ?? [];

}



/**

 * Get available hotel IDs with pricing from Booking System (cached)

 * 

 * @param array $hotelIds List of hotel IDs to query

 * @param array $args Search parameters (start_date, end_date, maxPersons, etc.)

 * @return array|WP_Error Array of results or WP_Error on failure

 */

function kv_roomboss_available_ids_cached(

    array $hotelIds,

    array $args,

    bool $withMeta = false

) {

    try {

        if (empty($hotelIds) || !is_array($hotelIds)) {

            return $withMeta ? [

                'results' => [],

                'pagination' => kv_roomboss_get_pagination_meta([], $args),

            ] : [];

        }



        $hotelIds = array_filter($hotelIds);



        if (empty($hotelIds)) {

            return $withMeta ? [

                'results' => [],

                'pagination' => kv_roomboss_get_pagination_meta([], $args),

            ] : [];

        }



        cf_log($args, 'acc_args', 'txt', true, true);



        $checkIn = isset($args['start_date']) ? sanitize_text_field($args['start_date']) : '';

        $checkOut = isset($args['end_date']) ? sanitize_text_field($args['end_date']) : '';



        $guests = 1;



        if (!empty($args['maxPersons'])) {

            $guests = is_array($args['maxPersons'])

                ? intval(reset($args['maxPersons']))

                : intval($args['maxPersons']);

        }



        if (empty($checkIn) || empty($checkOut)) {

            return new WP_Error(

                'invalid_dates',

                'Start date and end date are required for availability check'

            );

        }



        $offset = isset($args['offset']) ? intval($args['offset']) : 0;

        $limit  = isset($args['limit']) ? intval($args['limit']) : 50;



        $sortedIds = $hotelIds;

        sort($sortedIds);

        $cache_key = 'rb_full_' . md5(

            $checkIn . '|' .

            $checkOut . '|' .

            $guests . '|' .

            $offset . '|' .

            $limit . '|' .

            wp_json_encode($args) . '|' .

            implode(',', $sortedIds)

        );



        $cached_body = get_transient($cache_key);



        if ($cached_body !== false && is_array($cached_body)) {

            return $withMeta

                ? kv_roomboss_build_availability_payload($cached_body, $args)

                : kv_roomboss_parse_availability($cached_body);

        }



        $url = KV_BOOKING_SYSTEM_BASE . '/api/quotation-filteration';

        $bs_args = kv_booking_system_filter_args(KV_BS_authToken, $args);



        if (!$bs_args) {

            return new WP_Error(

                'invalid_request_args',

                'Failed to build booking system request arguments'

            );

        }



        $response = wp_remote_get($url, $bs_args);



        if (is_wp_error($response)) {

            return new WP_Error(

                'api_request_failed',

                'Failed to connect to booking system: ' . $response->get_error_message()

            );

        }



        $http_code = wp_remote_retrieve_response_code($response);



        if ($http_code !== 200) {

            return new WP_Error(

                'api_error',

                'Booking system returned HTTP ' . $http_code

            );

        }



        $response_body = wp_remote_retrieve_body($response);



        if (empty($response_body)) {

            return new WP_Error(

                'empty_response',

                'Booking system returned empty response'

            );

        }



        $body = json_decode($response_body, true);



        if (!is_array($body)) {

            return new WP_Error(

                'invalid_json',

                'Booking system returned invalid JSON'

            );

        }



        set_transient($cache_key, $body, 10 * MINUTE_IN_SECONDS);



        return $withMeta

            ? kv_roomboss_build_availability_payload($body, $args)

            : kv_roomboss_parse_availability($body);



    } catch (Exception $e) {

        return new WP_Error(

            'unexpected_error',

            'Error retrieving availability: ' . $e->getMessage()

        );

    }

}



function kv_roomboss_build_availability_payload(array $body, array $args = []): array {

    return [

        'results'    => kv_roomboss_parse_availability($body),

        'pagination' => kv_roomboss_get_pagination_meta($body, $args),

    ];

}



function kv_roomboss_get_pagination_meta(array $body = [], array $args = []): array {

    $filterModel = isset($body['filterModel']) && is_array($body['filterModel']) ? $body['filterModel'] : [];

    $pagination  = isset($body['pagination']) && is_array($body['pagination']) ? $body['pagination'] : [];



    $offset = isset($filterModel['offset'])

        ? intval($filterModel['offset'])

        : (isset($args['offset']) ? intval($args['offset']) : 0);



    $limit = isset($filterModel['limit'])

        ? intval($filterModel['limit'])

        : (isset($args['limit']) ? intval($args['limit']) : 50);



    if ($limit < 1) {

        $limit = 50;

    }



    $totalDbProperties = isset($pagination['total_db_properties'])

        ? intval($pagination['total_db_properties'])

        : 0;



    $totalDbRecords = isset($pagination['total_db_records'])

        ? intval($pagination['total_db_records'])

        : 0;



    $totalPages = $totalDbProperties > 0

        ? (int) ceil($totalDbProperties / $limit)

        : 1;



    $maxOffset = max(0, $totalPages - 1);

    $hasMore = $offset < $maxOffset;



    return [

        'offset'              => $offset,

        'limit'               => $limit,

        'total_db_properties' => $totalDbProperties,

        'total_db_records'    => $totalDbRecords,

        'total_pages'         => $totalPages,

        'max_offset'          => $maxOffset,

        'next_offset'         => $hasMore ? ($offset + 1) : null,

        'has_more'            => $hasMore,

    ];

}



function kv_roomboss_frontend_pagination_fields(array $pagination = []): array {

    return [

        'booking_offset'           => isset($pagination['offset']) ? intval($pagination['offset']) : 0,

        'booking_limit'            => isset($pagination['limit']) ? intval($pagination['limit']) : 50,

        'booking_total_properties' => isset($pagination['total_db_properties']) ? intval($pagination['total_db_properties']) : 0,

        'booking_total_records'    => isset($pagination['total_db_records']) ? intval($pagination['total_db_records']) : 0,

        'booking_total_pages'      => isset($pagination['total_pages']) ? intval($pagination['total_pages']) : 1,

        'booking_max_offset'       => isset($pagination['max_offset']) ? intval($pagination['max_offset']) : 0,

        'booking_next_offset'      => array_key_exists('next_offset', $pagination) ? $pagination['next_offset'] : null,

        'booking_has_more'         => !empty($pagination['has_more']),

    ];

}

/**
 * Parse exclude ID arrays from load-more AJAX requests.
 */
function kv_niseko_parse_exclude_ids( $raw ) {
    if ( empty( $raw ) ) {
        return [];
    }

    if ( is_string( $raw ) ) {
        $raw = preg_split( '/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY );
    }

    if ( ! is_array( $raw ) ) {
        $raw = [ $raw ];
    }

    return array_values(
        array_unique(
            array_filter(
                array_map( 'intval', $raw ),
                static function ( $id ) {
                    return $id > 0;
                }
            )
        )
    );
}

/**
 * Parse exclude property ID strings from load-more AJAX requests.
 */
function kv_niseko_parse_exclude_property_ids( $raw ) {
    if ( empty( $raw ) ) {
        return [];
    }

    if ( is_string( $raw ) ) {
        $raw = preg_split( '/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY );
    }

    if ( ! is_array( $raw ) ) {
        $raw = [ $raw ];
    }

    return array_values(
        array_unique(
            array_filter(
                array_map(
                    static function ( $id ) {
                        return trim( (string) $id );
                    },
                    $raw
                ),
                static function ( $id ) {
                    return $id !== '';
                }
            )
        )
    );
}

/**

 * Parse booking system availability response into standardized format

 * 

 * @param array $body API response body

 * @return array Parsed results with hotel IDs as keys

 */

function kv_roomboss_parse_availability(array $body): array {

    $results = [];



    // Validate properties exist in response

    if (empty($body['properties']) || !is_array($body['properties'])) {

        return [];

    }



    foreach ($body['properties'] as $hotel) {

        // Skip hotels without units

        if (empty($hotel['Units']) || !is_array($hotel['Units'])) {

            continue;

        }



        // Skip hotels without property ID

        if (empty($hotel['PropertyId'])) {

            continue;

        }





        $prices = [];

        $bedrooms = [];

        $room_count = 0;

        $bookingPermission = $hotel['bookingPermission'] ?? null;



        $price_min = !empty($_POST['price_min']) ? (float) $_POST['price_min'] : 0;

        $price_max = !empty($_POST['price_max']) ? (float) $_POST['price_max'] : 0;



        if(  $bookingPermission !== null  ){

            $bk_p = get_field( 'acc_booking_permission', $hotel['bookingPermission'] );

            $post_id = get_post_id_by_property_id( $hotel['PropertyId'] );

            if( $post_id !== null && !$bk_p ){

                update_field( 'acc_booking_permission', $hotel['bookingPermission'], $post_id );

            }

        }

        // ✅ Iterate through units

        foreach ($hotel['Units'] as $unit) {

            // Validate unit data

            if (empty($unit) || !is_array($unit)) {

                continue;

            }



            // Skip invalid units (Unit count <= 0)

            if (empty($unit['Unit']) || intval($unit['Unit']) <= 0) {

                continue;

            }



            // Validate rooms data

            if (empty($unit['Rooms']) || !is_array($unit['Rooms'])) {

                continue;

            }



            // ✅ Iterate through rooms

            foreach ($unit['Rooms'] as $room) {

                if (empty($room) || !is_array($room)) {

                    continue;

                }



                $room_count++;



                // Collect pricing data

                if (!empty($room['ActualPrice']) && is_numeric($room['ActualPrice'])) {

                    $prices[] = (float) $room['ActualPrice'];

                }



                // Collect bedroom count data

                if (!empty($room['numberBedrooms']) && is_numeric($room['numberBedrooms'])) {

                    $bedrooms[] = (int) $room['numberBedrooms'];

                }

            }

        }



        // Only include hotels with at least one room

        if ($room_count === 0) {

            continue;

        }



        if (!empty($prices)) {

            $product_min = min($prices);

            $product_max = max($prices);



            // Skip if the property's starting (minimum) price is outside the user's filtered price range

            if (

                ($price_max > 0 && $product_min > $price_max) ||

                ($price_min > 0 && $product_min < $price_min)

            ) {

                continue;

            }

        } elseif ($price_min > 0) {

            // No price data from API but user set a minimum price — exclude this property

            continue;

        }



        // Build result entry for this hotel

        $results[$hotel['PropertyId']] = [

            'min_price'         => !empty($prices) ? min($prices) : null,

            'bedrooms'          => !empty($bedrooms) ? array_values(array_unique($bedrooms)) : [],

            'room_count'        => $room_count,

            'bookingPermission' => $bookingPermission,

        ];

    }

    return $results;

}



/**

 * Build Booking System API request arguments

 * Merges default body with user overrides and creates HTTP headers

 * 

 * @param string $authorization Bearer token for API authentication

 * @param array $overrides Optional request body parameters to override defaults

 * @return array HTTP request arguments (method, headers, body, timeout)

 */

function kv_booking_system_filter_args( string $authorization, array $overrides = []): array {

    try {

        // ✅ STEP 1: Validate authorization token

        $authorization = trim($authorization);

        if (empty($authorization)) {

            return [];

        }



        // ✅ STEP 2: Build default request body

        $body = [

            "accommodationName" => "",

            "minRange" => null,

            "maxRange" => null,

            "resortId" => null,

            "locationCode" => "",

            "boardBasisId" => "",

            "start_date" => "02-Apr-2026",

            "end_date" => "08-Apr-2026",

            "duration" => 6,

            "units" => 1,

            "channel" => true,

            "bedBank" => true,

            "testMode" => false,

            "propertType" => [

                "apartment" => false,

                "chalet" => false,

                "hotel" => false

            ],

            "maxPersons" => 1,

            "totalAdults" => 1,

            "totalChildren" => 0,

            "totalInfants" => 0,

            "offset" => 0,

            "limit" => 50,

            "adults" => [1],

            "children" => [0],

            "infants" => [0],

            "propertyIds" => []

        ];



        // ✅ STEP 3: Merge overrides safely (only process if overrides is array)

        if (!empty($overrides) && is_array($overrides)) {

            $body = array_replace_recursive($body, $overrides);

        }



        // ✅ STEP 4: Build HTTP headers with authorization

        $headers = [

            'Content-Type'  => 'application/json',

            'Authorization' => 'Bearer ' . $authorization,

        ];



        // ✅ STEP 5: Return complete request arguments

        return [

            'method'      => 'POST',

            'headers'     => $headers,

            'body'        => wp_json_encode($body),

            'timeout'     => 30,

            'data_format' => 'body',

        ];



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error building booking system filter args: ' . $e->getMessage());

        return [];

    }

}



/**

 * Calculate number of days between two dates

 * 

 * @param string $start_date Start date in 'd-M-Y' format (e.g., '02-Apr-2026')

 * @param string $end_date End date in 'd-M-Y' format (e.g., '08-Apr-2026')

 * @return int|false Number of days as integer, or false on error

 */

function get_duration_from_date( $start_date, $end_date ) {

    try {

        // ✅ STEP 1: Validate parameters are not empty

        if (empty($start_date) || empty($end_date)) {

            return false;

        }



        // ✅ STEP 2: Sanitize input dates

        $start_date = trim((string) $start_date);

        $end_date = trim((string) $end_date);



        if (empty($start_date) || empty($end_date)) {

            return false;

        }



        // ✅ STEP 3: Parse dates with error handling

        $date1 = DateTime::createFromFormat('d-M-Y', $start_date);

        $date2 = DateTime::createFromFormat('d-M-Y', $end_date);



        if (!$date1 || !$date2) {

            return false;

        }



        // ✅ STEP 4: Calculate and return duration

        $duration = $date1->diff($date2);

        return (int) $duration->days;



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error calculating date duration: ' . $e->getMessage());

        return false;

    }

}



/**

 * Convert date from d/m/Y format to specified output format

 * Input format: d/m/Y (e.g., '10/04/2026' = April 10, 2026)

 * Output formats: 'd-M-Y' (02-Apr-2026), 'Y-m-d', etc.

 * 

 * @param string $date Input date in d/m/Y format

 * @param string $format Output format string for DateTime

 * @return string|false Formatted date string, or false on error

 */

function hz_convert_date_format( string $date, string $format ) {

    try {

        // ✅ STEP 1: Validate input parameters

        $date = trim($date);

        $format = trim($format);



        if (empty($date) || empty($format)) {

            return false;

        }



        // ✅ STEP 2: Parse date with input format d/m/Y

        $dateObject = DateTime::createFromFormat('d/m/Y', $date);



        if (!$dateObject) {

            return false;

        }



        // ✅ STEP 3: Format and return result

        return $dateObject->format($format);



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error converting date format: ' . $e->getMessage());

        return false;

    }

}



/**

 * Get local property IDs and build booking system API arguments

 * Queries WordPress database for accommodation matching filters and constructs API request body

 * 

 * @param array $input Search input containing filters (checkin, checkout, resort, prices, etc.)

 * @return array Array with 'ids' => property IDs array and 'args' => booking system request body

 */

function hz_get_local_property_ids_and_args(array $input): array {

    $empty_result = ['ids' => [], 'args' => []];

    try {

        // ✅ STEP 1: Validate input and query local properties

        if (empty($input) || !is_array($input)) {

            return $empty_result;

        }



        $args = niseko_build_search_query_args($input, [

            'fields'         => 'ids',

            'posts_per_page' => -1,

        ]);



        if (!is_array($args) || empty($args)) {

            return $empty_result;

        }

        

        $query = new WP_Query($args);



        if (empty($query->posts) || !is_array($query->posts)) {

            return $empty_result;

        }



        $booking_sys_args = [];



        $booking_sys_args['offset'] = isset($input['booking_offset'])

            ? max(0, intval($input['booking_offset']))

            : 0;



        $booking_sys_args['limit'] = isset($input['booking_limit'])

            ? max(1, intval($input['booking_limit']))

            : 50;



        // ✅ STEP 2: Process date parameters

        if (isset($input['checkin'], $input['checkout'])) {

            $checkin_raw = sanitize_text_field($input['checkin']);

            $checkout_raw = sanitize_text_field($input['checkout']);



            if (!empty($checkin_raw) && !empty($checkout_raw)) {

                // Convert dates d/m/Y → d-M-Y format for API

                $checkin = hz_convert_date_format($checkin_raw, 'd-M-Y');

                $checkout = hz_convert_date_format($checkout_raw, 'd-M-Y');



                if ($checkin && $checkout) {

                    $duration = get_duration_from_date($checkin, $checkout);



                    if ($duration !== false && $duration > 0) {

                        $booking_sys_args['start_date'] = $checkin;

                        $booking_sys_args['end_date'] = $checkout;

                        $booking_sys_args['duration'] = (int) $duration;

                    }

                }

            }

        }

        

        // ✅ STEP 3: Process resort parameter

        if (!empty($input['resort'])) {

            $taxonomy_name = sanitize_text_field($input['resort']);

            $term = get_term_by('slug', $taxonomy_name, 'accommodation-cat');



            if ($term && !is_wp_error($term)) {

                $term_id = intval($term->term_id);

                $resortId = get_field('bs_resort_id', 'term_' . $term_id);



                if (!empty($resortId)) {

                    $booking_sys_args['resortId'] = intval($resortId);

                }

            }

        }



        // ✅ STEP 4: Process price parameters

        // Note: price_min can legitimately be 0, so !empty() would wrongly block it.

        // Only send the range when price_max is explicitly provided and positive.

        if (isset($input['price_max']) && $input['price_max'] !== '') {

            $maxRange = (float) $input['price_max'];

            if ($maxRange > 0) {

                $minRange = isset($input['price_min']) && $input['price_min'] !== '' ? (float) $input['price_min'] : 0;

                $booking_sys_args['minRange'] = $minRange;

                $booking_sys_args['maxRange'] = $maxRange;

            }

        }



        // ✅ STEP 5: Process accommodation type parameters

        if (!empty($input['accommodation_type']) && is_array($input['accommodation_type'])) {

            $acc_types = array_map('strtolower', $input['accommodation_type']);

            $acc_types = array_map('sanitize_text_field', $acc_types);



            $booking_sys_args['propertType']['apartment'] = in_array('apartment', $acc_types, true);

            $booking_sys_args['propertType']['chalet'] = in_array('chalet', $acc_types, true);

            $booking_sys_args['propertType']['hotel'] = in_array('hotel', $acc_types, true);

        }   



        // ✅ STEP 6: Process booking channel parameter

        if (!empty($input['booking'])) {

            $booking_sys_args['channel'] = true;

            $booking_sys_args['bedBank'] = false;

        }



        // ✅ STEP 7: Process areas/location parameter

        if (!empty($input['areas[]'])) {

            $areas = sanitize_text_field($input['areas[]']);

            if (!empty($areas)) {

                $booking_sys_args['locationCode'] = $areas;

            }

        }



        if (!empty($input['adults'])) {

            $adults = intval($input['adults']);

            if (!empty($adults)) {

                $booking_sys_args['adults'] = [$adults];

                $booking_sys_args['totalAdults'] = $adults;

            }

        }



        if (!empty($input['children'])) {

            $children = intval($input['children']);

            if (!empty($children)) {

                $booking_sys_args['children'] = [$children];

                $booking_sys_args['totalChildren'] = $children;

            }

        }



        if (!empty($input['infants'])) {

            $infants = intval($input['infants']);

            if (!empty($infants)) {

                $booking_sys_args['infants'] = [$infants];

                $booking_sys_args['totalInfants'] = $infants;

            }

        }



        if (!empty($input['guests'])) {

            $guests = intval($input['guests']);

            if (!empty($guests)) {

                $booking_sys_args['maxPersons'] = $guests;

            }

        }



        if (!empty($input['property_id']) && $input['property_id'] !== '0') {

            $property_id = sanitize_text_field($input['property_id']);



            if (!empty($property_id)) {

                $booking_sys_args['propertyIds'] = [$property_id];

            }

        }



        // ✅ STEP 8: Extract property IDs from query results

        $ids = array_values(

            array_unique(

                array_filter(

                    array_map(

                        function($post_id) {

                            if (!is_numeric($post_id)) {

                                return null;

                            }

                            $property_id = get_field( 'property_id', (int) $post_id );

                            return !empty($property_id) ? $property_id : null;

                        },

                        $query->posts

                    )

                )

            )

        );



        if (!empty($booking_sys_args['propertyIds'])) {

            $property_ids_from_input = is_array($booking_sys_args['propertyIds'])

                ? $booking_sys_args['propertyIds']

                : [$booking_sys_args['propertyIds']];



            $new_ids = array_values(array_unique(array_filter(array_merge($ids, $property_ids_from_input))));



            $booking_sys_args['propertyIds'] = $new_ids;



            // Important: return merged IDs too, otherwise selected property search can stop before API call.

            $ids = $new_ids;

        } else {

            $booking_sys_args['propertyIds'] = $ids;

        }



        // ✅ STEP 9: Return property IDs and booking system arguments

        return [

            'ids' => $ids,

            'args' => $booking_sys_args,

        ];



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error building property IDs and booking args: ' . $e->getMessage());

        return $empty_result;

    }

}



/**

 * Retrieve rooms for a hotel property with optional room type filtering

 * 

 * @param mixed $property_id Property ID to query rooms for

 * @param array $allowedRoomTypeIds Optional array of room type IDs to filter by

 * @return array Array containing 'rooms' (array of posts) and 'bedroom_types' (unique bedroom counts)

 */

if (!function_exists('kv_property_uses_roomboss_rooms')) {
    /**
     * Treat RoomBoss and hybrid properties as RoomBoss on the website.
     *
     * Explicit is_roomboss=0 (BedBank conversion) always wins — stale
     * acc_hotel_id / leftover room IDs must not force the RoomBoss flow.
     * Hybrid/RoomBoss (is_roomboss=1) still use the RoomBoss room flow.
     */
    function kv_property_uses_roomboss_rooms($post_id = 0, $property_id = 0): bool {
        $post_id = absint($post_id);
        $property_id = absint($property_id);

        // Returns true/false when is_roomboss is explicitly set, null when unset.
        $explicit_is_roomboss = static function ($id): ?bool {
            $raw = get_post_meta($id, 'is_roomboss', true);
            if ($raw === true || $raw === 1 || $raw === '1') {
                return true;
            }
            if ($raw === false || $raw === 0 || $raw === '0') {
                return false;
            }
            $field = function_exists('get_field') ? get_field('is_roomboss', $id) : null;
            if ($field === true || $field === 1 || $field === '1') {
                return true;
            }
            if ($field === false || $field === 0 || $field === '0') {
                return false;
            }
            return null;
        };

        $has_roomboss_hotel_id = static function ($id): bool {
            $hotel_id = trim((string) get_post_meta($id, 'acc_hotel_id', true));
            return $hotel_id !== '' && $hotel_id !== '0';
        };

        if ($post_id > 0) {
            $explicit = $explicit_is_roomboss($post_id);
            if ($explicit === false) {
                return false;
            }
            if ($explicit === true || $has_roomboss_hotel_id($post_id)) {
                return true;
            }
        }

        if ($property_id > 0) {
            $accommodation_ids = get_posts([
                'post_type'      => 'accommodation',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post_status'    => 'any',
                'meta_query'     => [
                    [
                        'key'     => 'property_id',
                        'value'   => $property_id,
                        'compare' => '=',
                    ],
                ],
            ]);

            if (!empty($accommodation_ids)) {
                $accommodation_id = absint($accommodation_ids[0]);
                $explicit = $explicit_is_roomboss($accommodation_id);
                if ($explicit === false) {
                    return false;
                }
                if ($explicit === true || $has_roomboss_hotel_id($accommodation_id)) {
                    return true;
                }
            }

            // Fallback only when is_roomboss meta is unset: real non-zero RoomBoss room IDs.
            $room_ids = get_posts([
                'post_type'      => 'japan_rooms',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post_status'    => ['publish', 'draft', 'pending'],
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => 'property_id',
                        'value'   => $property_id,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'roomboss_room_id',
                        'value'   => ['', '0'],
                        'compare' => 'NOT IN',
                    ],
                ],
            ]);

            if (!empty($room_ids)) {
                return true;
            }
        }

        return false;
    }
}

function get_hotel_rooms($property_id, array $allowedRoomTypeIds = [], string $room_source = '')

{

    try {

        // ✅ STEP 1: Validate and sanitize prop$property_id parameter

        if (empty($property_id)) {

            return [

                'rooms' => [],

                'bedroom_types' => [],

            ];

        }



        // ✅ STEP 2: Build base meta query

        $meta_query = [

            'relation' => 'AND',

            [

                'key'     => 'property_id',

                'value'   => $property_id,

                'compare' => '=',

            ],

        ];



        // ✅ STEP 3: Add optional room type filter

        if (!empty($allowedRoomTypeIds) && is_array($allowedRoomTypeIds)) {

            // Sanitize and filter room type IDs

            $allowedRoomTypeIds = array_filter(array_map('absint', $allowedRoomTypeIds));



            if (!empty($allowedRoomTypeIds)) {

                $meta_query[] = [

                    'key'     => 'actual_room_id',

                    'value'   => $allowedRoomTypeIds,

                    'compare' => 'IN',

                ];

            }

        }

        if ($room_source === 'roomboss') {
            $meta_query[] = [
                'key'     => 'roomboss_room_id',
                'value'   => ['', '0'],
                'compare' => 'NOT IN',
            ];
        }



        // ✅ STEP 4: Query for rooms

        $args = [

            'post_type'      => 'japan_rooms',

            'posts_per_page' => -1,

            'post_status'    => ['publish', 'draft', 'pending'],

            'meta_query'     => $meta_query,

        ];



        $rooms = get_posts($args);



        // Validate query result

        if (!is_array($rooms)) {

            $rooms = [];

        }



        // ✅ STEP 5: Collect distinct bedroom types from results

        $bedroom_types = [];



        foreach ($rooms as $room) {

            // Validate room object structure

            if (!is_object($room) || empty($room->ID)) {

                continue;

            }



            $bedrooms = get_field('room_bedroom', $room->ID);



            // Validate and type-cast bedroom count

            if (!empty($bedrooms)) {

                $bedrooms = (int) $bedrooms;



                if ($bedrooms > 0 && !in_array($bedrooms, $bedroom_types, true)) {

                    $bedroom_types[] = $bedrooms;

                }

            }

        }



        // ✅ STEP 6: Sort bedroom types for consistent output

        sort($bedroom_types);



        // ✅ STEP 7: Return results

        return [

            'rooms'         => $rooms,

            'bedroom_types' => $bedroom_types,

        ];



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error in get_hotel_rooms: ' . $e->getMessage());



        return [

            'rooms' => [],

            'bedroom_types' => [],

        ];

    }

}



function niseko_build_search_query_args(array $input, array $overrides = []): array

{



    $paged    = isset($input['page']) ? max(1, (int) $input['page']) : 1;

    $per_page = isset($input['per_page']) ? intval( $input['per_page'] ) : 6;

    // $per_page = 20;



    $meta_query = [ 'relation' => 'AND' ];

    $tax_query  = [];



    /* =========================

     * TAXONOMY

     * ========================= */

    if (!empty($input['category_id']) || !empty($input['category_slug']) || !empty($input['resort'])) {

        $tax_query = [

            'relation' => 'AND',

        ];



        if (!empty($input['category_id'])) {

            $tax_query[] = [

                'taxonomy'         => 'accommodation-cat',

                'field'            => 'term_id',

                'terms'            => [(int) $input['category_id']],

                'include_children' => true,

            ];

        }



        if (!empty($input['category_slug'])) {

            $tax_query[] = [

                'taxonomy' => 'accommodation-cat',

                'field'    => 'slug',

                'terms'    => array(sanitize_text_field($input['category_slug'])),

                'include_children' => true,

            ];

        }



        /* =========================

         * RESORT (OPTIONAL)

         * ========================= */



        if (!empty($input['resort'])) {

            $tax_query[] = [

                'taxonomy' => 'accommodation-cat',

                'field'    => 'slug',

                'terms'    => array(sanitize_text_field($input['resort'])),

                'include_children' => true,

            ];

        }

    }



    /* =========================

     * BEDROOMS

     * ========================= */

    if (!empty($input['bedrooms']) && is_array($input['bedrooms'])) {

        $bedroom_sub = ['relation' => 'OR'];

        foreach ($input['bedrooms'] as $bedroom) {

            $bedroom_sub[] = [

                'key'     => 'acc_no_of_bedrooms',

                'value'   => 'i:' . (int) $bedroom . ';',

                'compare' => 'LIKE',

            ];

        }

        $meta_query[] = $bedroom_sub;

    }



    /* =========================

     * PRICE

     * ========================= */

    // Price filtering is intentionally not applied here because this function is shared

    // across booking-system paths (where live prices from roomboss override local meta)

    // and the exclude-price path (is_price_excluded properties bypass price filters).

    // For local-only search price filtering, see niseko_search_local().



    /* =========================

     * AREA

     * ========================= */

    if (!empty($input['areas']) && is_array($input['areas'])) {

        $tax_query[] = [

            'taxonomy' => 'accommodation-cat',

            'field'    => 'slug',

            'terms'    => array_map('sanitize_text_field', $input['areas']),

            'operator' => 'IN',

        ];

    }



    /* =========================

     * TYPE

     * ========================= */

    if (!empty($input['accommodation_type']) && is_array($input['accommodation_type'])) {

        $acc_type = [

            'taxonomy' => 'property_types',

            'field'    => 'slug',

            'terms'    => array_map('sanitize_text_field', $input['accommodation_type']),

            'operator' => 'IN',

        ];

        $tax_query[] = $acc_type;

    }



    /* =========================

     * ski in ski out

     * ========================= */

    if (!empty($input['ski_in_ski_out'])) {

        $ski_in_out = [

            'taxonomy' => 'property_ammenites',

            'field'    => 'slug',

            'terms'    => 'ski-in-ski-out',

        ];

        $tax_query[] = $ski_in_out;

    }



    /* =========================

     * onsen

     * ========================= */

    if (!empty($input['onsen'])) {

        $onsen = [

            'taxonomy' => 'property_ammenites',

            'field'    => 'slug',

            'terms'    => 'onsen',

        ];

        $tax_query[] = $onsen;

    }



    /* =========================

     * discount

     * ========================= */

    if (!empty($input['discount'])) {

        $discount = ['relation' => 'AND'];

        $discount[] = [

            'key'     => 'is_discount',

            'value'   => '1',

            'compare' => '=',

        ];

        $meta_query[] = $discount;

    }



    if (!empty($input['booking'])) {

        $booking = ['relation' => 'AND'];

        $booking[] = [

            'key'     => 'is_roomboss',

            'value'   => '1',

            'compare' => '=',

        ];



        $booking[] = [

            'key'     => 'acc_hotel_id',

            'compare' => 'EXISTS',

        ];

        $meta_query[] = $booking;

    }



    /* =========================

     * SORTING

     * ========================= */



    // Default: menu_order

    $orderby  = ['menu_order' => 'ASC', 'title' => 'ASC'];

    $order    = 'ASC';

    $meta_key = '';



    if (!empty($input['sort'])) {

        switch ($input['sort']) {

            case 'price_asc':

                $orderby  = 'meta_value_num';

                $meta_key = 'min_room_price';

                $order    = 'ASC';

                break;



            case 'price_desc':

                $orderby  = 'meta_value_num';

                $meta_key = 'min_room_price';

                $order    = 'DESC';

                break;



            case 'title_asc':

                $orderby  = 'title';

                $order    = 'ASC';

                $meta_key = '';

                break;



            case 'title_desc':

                $orderby  = 'title';

                $order    = 'DESC';

                $meta_key = '';

                break;



            case 'date_desc':

                $orderby  = 'date';

                $order    = 'DESC';

                $meta_key = '';

                break;

        }

    }



    $args = [

        'post_type'      => 'accommodation',

        'post_status'    => 'publish',

        'posts_per_page' => $per_page,

        'paged'          => $paged,

        'meta_query'     => $meta_query,

        'orderby'        => $orderby,

        'order'          => $order,

    ];



    if (!empty($input['location'])) {

        $args['title'] = $input['location'];

    }



    if (!empty($meta_key)) {

        $args['meta_key'] = $meta_key;

    }    



    if ($tax_query) {

        $args['tax_query'] = $tax_query;

    }



    // 🔁 Allow overrides (fields, posts_per_page, etc.)

    return array_replace_recursive($args, $overrides);

}



/**

 * Search and display accommodations from RoomBoss availability data

 * Queries local WordPress posts matching available hotel IDs and renders accommodation cards

 * 

 * @param array $availableHotelIds List of property IDs available from booking system

 * @param array $roombossData Optional associative array of booking system data keyed by property ID

 * @return void Sends JSON response with HTML content and pagination info

 */

function niseko_search_roomboss_wp(array $availableHotelIds, array $roombossData = [], array $bookingPagination = []) {

    try {

        $paginationFields = kv_roomboss_frontend_pagination_fields($bookingPagination);



        if (empty($availableHotelIds) || !is_array($availableHotelIds)) {

            wp_send_json_success(array_merge([

                'html' => '',

                'count'      => 0,

                'room_count' => 0,

                'has_more'   => false,

            ], $paginationFields));

        }



        if (!is_array($roombossData)) {

            $roombossData = [];

        }



        $per_page = isset( $_POST['per_page'] ) ? max( 1, intval( $_POST['per_page'] ) ) : 6;
        $exclude_post_ids = kv_niseko_parse_exclude_ids( $_POST['exclude_post_ids'] ?? [] );
        $exclude_property_ids = kv_niseko_parse_exclude_property_ids( $_POST['exclude_property_ids'] ?? [] );
        $is_load_more = ! empty( $_POST['load_more'] ) || ! empty( $exclude_post_ids );

        // Drop already-shown property IDs from this booking batch (handles API overlap across offsets).
        if ( ! empty( $exclude_property_ids ) ) {
            $exclude_prop_lookup = array_flip( $exclude_property_ids );
            $availableHotelIds  = array_values(
                array_filter(
                    $availableHotelIds,
                    static function ( $pid ) use ( $exclude_prop_lookup ) {
                        return ! isset( $exclude_prop_lookup[ (string) $pid ] );
                    }
                )
            );
        }

        if ( empty( $availableHotelIds ) ) {
            wp_send_json_success(
                array_merge(
                    [
                        'html'       => '',
                        'count'      => count( $exclude_post_ids ),
                        'room_count' => 0,
                        'has_more'   => false,
                    ],
                    $paginationFields
                )
            );
        }

        // Full matching ID list for this booking batch (stable order), then slice remaining.
        $id_args = niseko_build_search_query_args(
            $_POST,
            [
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'paged'          => 1,
            ]
        );
        unset( $id_args['offset'] );

        $id_args['meta_query'][] = [
            'key'     => 'property_id',
            'value'   => $availableHotelIds,
            'compare' => 'IN',
        ];

        if ( ! is_array( $id_args ) || empty( $id_args ) ) {
            wp_send_json_success(
                array_merge(
                    [
                        'html'       => '',
                        'count'      => 0,
                        'room_count' => 0,
                        'has_more'   => false,
                    ],
                    $paginationFields
                )
            );
        }

        $all_post_ids = get_posts( $id_args );
        if ( ! is_array( $all_post_ids ) ) {
            $all_post_ids = [];
        }
        $all_post_ids = array_values( array_unique( array_map( 'intval', $all_post_ids ) ) );

        if ( ! empty( $exclude_post_ids ) ) {
            $exclude_lookup = array_flip( $exclude_post_ids );
            $all_post_ids   = array_values(
                array_filter(
                    $all_post_ids,
                    static function ( $post_id ) use ( $exclude_lookup ) {
                        return ! isset( $exclude_lookup[ (int) $post_id ] );
                    }
                )
            );
        }

        $total_matching = count( $all_post_ids ) + count( $exclude_post_ids );
        $pagination_page = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;

        if ( ! empty( $exclude_post_ids ) ) {
            // Load-more with known shown IDs: always next remaining cards.
            $slice_ids       = array_slice( $all_post_ids, 0, $per_page );
            $has_more_wp     = count( $all_post_ids ) > $per_page;
            $pagination_page = 1;
        } else {
            // Normal / fallback page-based slice (Load More now also increments page).
            $offset      = ( $pagination_page - 1 ) * $per_page;
            $slice_ids   = array_slice( $all_post_ids, $offset, $per_page );
            $has_more_wp = count( $all_post_ids ) > ( $offset + count( $slice_ids ) );
        }

        if ( empty( $slice_ids ) ) {
            wp_send_json_success(
                array_merge(
                    [
                        'html'       => '',
                        'count'      => $total_matching,
                        'room_count' => 0,
                        'has_more'   => false,
                    ],
                    $paginationFields
                )
            );
        }

        $query = new WP_Query(
            [
                'post_type'      => 'accommodation',
                'post_status'    => 'publish',
                'posts_per_page' => count( $slice_ids ),
                'post__in'       => $slice_ids,
                'orderby'        => 'post__in',
            ]
        );

        $property_ids = array_map(
            static function ( $post ) {
                return get_post_meta( $post->ID, 'property_id', true );
            },
            $query->posts
        );

        $room_count = get_room_count_from_roomboss_hotels( $property_ids );
        $room_count = intval( $room_count );

        if ( ! is_object( $query ) || ! isset( $query->posts ) || ! is_array( $query->posts ) ) {
            wp_send_json_success(
                array_merge(
                    [
                        'html'       => '',
                        'count'      => 0,
                        'room_count' => $room_count,
                        'has_more'   => false,
                    ],
                    $paginationFields
                )
            );
        }

        $current_path = isset( $_POST['current_url'] ) ? $_POST['current_url'] : '';
        $total_count  = $total_matching;



        if ($query->have_posts()) {

            $count = 0;

            ob_start();



            while ($query->have_posts()) {

                $query->the_post();

                $count++;



                $post_id = get_the_ID();

                $property_id = get_field('property_id', $post_id);



                if (!empty($property_id) && isset($roombossData[$property_id])) {

                    $GLOBALS['kv_roomboss_current'] = $roombossData[$property_id];

                } else {

                    $GLOBALS['kv_roomboss_current'] = null;

                }



                $top_label = '';



                if (strpos($current_path, '/deals') !== false) {

                    if (have_rows('rate_plan', get_the_ID())) {

                        while (have_rows('rate_plan', get_the_ID())) {

                            the_row();



                            $rate_plan_name = get_sub_field('rate_plan_name');



                            if (!empty($rate_plan_name) && stripos($rate_plan_name, 'discount') !== false) {

                                $top_label = wp_strip_all_tags($rate_plan_name, true);

                                break;

                            }

                        }



                        reset_rows();

                    }

                }



                get_template_part('partials/accommodation-box', null, [

                    'bed_bank'  => false,

                    'room_boss' => true,

                    'top_label' => $top_label,

                ]);



                if ($count === 3 && $pagination_page === 1 && ! $is_load_more ) {

                    echo kv_get_expert_recommendation_cta();

                }



                if ($count === 9 && $pagination_page === 1 && ! $is_load_more ) {

                    echo kv_get_stronger_recommendation_cta();

                }



                unset($GLOBALS['kv_roomboss_current']);

            }



            wp_reset_postdata();

            $enquiry_html = get_acc_enquiry_form();

            wp_send_json_success(array_merge([

                'html'       => ob_get_clean(),

                'count'      => $total_count,

                'room_count' => $room_count,

                'has_more'   => $has_more_wp,

                'form_html'  => $enquiry_html,

            ], $paginationFields));

        } else {

            wp_reset_postdata();

            $enquiry_html = get_acc_enquiry_form();

            wp_send_json_success(array_merge([

                'html'       => '',

                'count'      => $total_count,

                'room_count' => $room_count,

                'has_more'   => false,

                'form_html'  => $enquiry_html,

            ], $paginationFields));

        }



    } catch (Exception $e) {

        error_log('Error in niseko_search_roomboss_wp: ' . $e->getMessage());



        wp_send_json_success(array_merge([

            'html' => '',

            'count'      => 0,

            'room_count' => 0,

            'has_more'   => false,

        ], kv_roomboss_frontend_pagination_fields($bookingPagination)));

    }

}



function get_post_id_by_property_id( $property_id ){

    $query = new WP_Query([

        'post_type'      => 'accommodation',

        'posts_per_page' => 1,

        'meta_query'     => [

            [

                'key'     => 'property_id',

                'value'   => $property_id,

                'compare' => '=',

            ],

        ],

    ]);



    if ( $query->have_posts() ) {

        return $query->posts[0]->ID;

    }



    return null;

}



function get_resort_id_by_property_id( $property_id ){



    $post_id = get_post_id_by_property_id( $property_id );



    if ( ! $post_id ) {

        return null;

    }



    $terms = get_the_terms( $post_id, 'accommodation-cat' );



    if ( is_wp_error( $terms ) || empty( $terms ) ) {

        return null;

    }



    foreach ( $terms as $term ) {

        $resort_id = get_field( 'bs_resort_id', 'term_' . $term->term_id );

        if ( $resort_id ) {

            return $resort_id;

        }

    }



    return null;

}



function get_available_bedrooms( $rooms ){

    $available_bedroom_types = [];

    if (!empty($rooms)) {

    

        foreach ($rooms as $room) {

            if (!empty($room['numberBedrooms'])) {

                $available_bedroom_types[] = (int) $room['numberBedrooms'];

            }

        }



        $available_bedroom_types = array_values( array_unique($available_bedroom_types) );

        sort($available_bedroom_types);

    }



    return $available_bedroom_types;

}



// Register the AJAX action

add_action('wp_ajax_niseko_search_roomboss_single', 'niseko_search_roomboss_single');

add_action('wp_ajax_nopriv_niseko_search_roomboss_single', 'niseko_search_roomboss_single');



/**

 * Retrieve and display available rooms for a single hotel property

 * Fetches room availability data and renders room cards with amenities

 * 

 * @return void Sends JSON response with HTML content and room metadata

 */

function niseko_search_roomboss_single()

{

    try {

        // ✅ STEP 1: Validate and sanitize POST parameters

        if (empty($_POST['checkin']) || empty($_POST['checkout']) || empty($_POST['property_id'])) {

            return wp_send_json_error([

                'message' => 'Check-in, check-out, and property ID are required',

                'code' => 'missing_parameters'

            ], 400);

        }



        $checkin = sanitize_text_field($_POST['checkin']);

        $checkout = sanitize_text_field($_POST['checkout']);

        $property_id = intval($_POST['property_id']);



        // Validate property ID is numeric and positive

        if ($property_id < 1) {

            return wp_send_json_error([

                'message' => 'Invalid property ID',

                'code' => 'invalid_property_id'

            ], 400);

        }



        // ✅ STEP 2: Fetch room availability data

        $roomData = kv_bs_available_room_data($property_id, $checkin, $checkout, 1);

        $is_roomboss = is_array($roomData) && intval($roomData['is_roomboss'] ?? 0) === 1;

        // Collect ActualRoomIds already returned by the live API so we can
        // still append manually-added / BedBank WP rooms that are not in the response.
        $api_actual_room_ids = [];
        if (is_array($roomData)) {
            foreach ($roomData as $key => $room) {
                if (!is_array($room) || $key === 'is_roomboss') {
                    continue;
                }
                $actual_id = intval($room['ActualRoomId'] ?? 0);
                if ($actual_id > 0) {
                    $api_actual_room_ids[$actual_id] = true;
                }
            }
        }

        // ✅ STEP 4: Build HTML output from room data

        ob_start();

        $room_count = 0;

        if (is_array($roomData)) {
            foreach ($roomData as $key => $room) {
                if (!is_array($room) || $key === 'is_roomboss') {
                    continue;
                }

                $room_count++;

                get_template_part('partials/room-box-bs', null, [
                    'room' => $room,
                    'rb' => $is_roomboss,
                    'property_id' => $property_id,
                ]);
            }
        }

        // Always include WP rooms that are not RoomBoss-backed (manual / BedBank),
        // even when the live API returns only RoomBoss inventory (or nothing).
        $wp_rooms_data = function_exists('get_hotel_rooms') ? get_hotel_rooms($property_id, [], '') : [];
        $wp_rooms = is_array($wp_rooms_data['rooms'] ?? null) ? $wp_rooms_data['rooms'] : [];

        foreach ($wp_rooms as $wp_room) {
            if (!is_object($wp_room) || empty($wp_room->ID)) {
                continue;
            }

            $rb_room_id = trim((string) get_post_meta($wp_room->ID, 'roomboss_room_id', true));
            $is_manual_or_bedbank = ($rb_room_id === '' || $rb_room_id === '0');
            $actual_room_id = intval(get_post_meta($wp_room->ID, 'actual_room_id', true));

            // Skip RoomBoss-synced rooms already covered by (or intentionally omitted from) API results.
            if (!$is_manual_or_bedbank) {
                continue;
            }

            // Avoid duplicate cards if the same actual_room_id somehow came from the API.
            if ($actual_room_id > 0 && isset($api_actual_room_ids[$actual_room_id])) {
                continue;
            }

            $room_count++;

            get_template_part('partials/room-box', null, [
                'room' => $wp_room,
                'rb' => false,
                'property_id' => $property_id,
            ]);
        }

        $html = ob_get_clean();

        // Handle case where no valid rooms were processed

        if ($room_count === 0) {

            return wp_send_json_success([

                'html' => '<p>No rooms available for the selected dates. Choose new dates or request a quote to find availability.</p>',

                'count' => 0,

                'available_bedroom_types' => [],

                'has_more' => false,

            ]);

        }



        // ✅ STEP 5: Extract available bedroom types

        $available_bedroom_types = is_array($roomData) ? get_available_bedrooms($roomData) : [];
        if (empty($available_bedroom_types) && !empty($wp_rooms_data['bedroom_types'])) {
            $available_bedroom_types = $wp_rooms_data['bedroom_types'];
        }



        // ✅ STEP 6: Send successful response with metadata

        return wp_send_json_success([

            'html' => $html,

            'count' => $room_count,

            'available_bedroom_types' => $available_bedroom_types,

            'has_more' => false,

        ]);



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error in niseko_search_roomboss_single: ' . $e->getMessage());



        return wp_send_json_error([

            'message' => 'An error occurred while loading rooms',

            'code' => 'unexpected_error'

        ], 500);

    }

}



/**

 * Fetch available room data for a specific property from booking system

 * Queries API and extracts room information with property type flag

 * 

 * @param int $propertyId Property/hotel ID to query

 * @param string $checkIn Check-in date in d/m/Y format

 * @param string $checkOut Check-out date in d/m/Y format

 * @param int $guests Number of guests

 * @return array Array of rooms with 'is_roomboss' flag, empty array on failure

 */

function kv_bs_available_room_data(

    int $propertyId,

    string $checkIn,

    string $checkOut,

    int $guests

): array {

    try {

        // ✅ STEP 1: Validate property ID parameter

        if ($propertyId < 1) {

            error_log('Invalid property ID passed to kv_bs_available_room_data: ' . $propertyId);

            return [];

        }



        // ✅ STEP 2: Convert date format from d/m/Y to d-M-Y for API

        $checkInFormatted = hz_convert_date_format($checkIn, 'd-M-Y');

        $checkOutFormatted = hz_convert_date_format($checkOut, 'd-M-Y');



        // Validate date conversion succeeded

        if (!$checkInFormatted || !$checkOutFormatted) {

            error_log('Failed to convert dates in kv_bs_available_room_data');

            return [];

        }



        // ✅ STEP 3: Query booking system for availability

        $hotel = kv_roomboss_get_availability(

            $propertyId,

            $checkInFormatted,

            $checkOutFormatted,

            intval($guests)

        );



        // Validate API response

        if (empty($hotel) || !is_array($hotel)) {

            return [];

        }



        // ✅ STEP 4: Extract room data from all units in the API response.

        $property = $hotel[0] ?? [];

        if (empty($property['Units']) || !is_array($property['Units'])) {

            return [];

        }



        $treat_as_roomboss = kv_property_uses_roomboss_rooms(0, $propertyId);

        $rooms = [];

        foreach ($property['Units'] as $unit) {

            if (empty($unit['Rooms']) || !is_array($unit['Rooms'])) {

                continue;

            }

            foreach ($unit['Rooms'] as $room) {

                if (empty($room) || !is_array($room)) {

                    continue;

                }

                // Include both RoomBoss and BedBank units from the API.
                // (Previously skipped non-RoomBoss rooms when property looked RoomBoss-ish,
                // which hid BedBank inventory after conversion / on hybrid properties.)
                $rooms[] = $room;

            }

        }

        if (empty($rooms)) {

            return [];

        }



        // ✅ STEP 5: Determine property type (RoomBoss vs BedBank)
        // WP accommodation meta wins — stale API PropertyRoomBossHotelId after
        // BedBank conversion must not keep CTAs on "Book Now".
        $is_roomboss = $treat_as_roomboss ? 1 : 0;



        // ✅ STEP 6: Add property type flag to rooms array

        $rooms['is_roomboss'] = $is_roomboss;



        // ✅ STEP 7: Return structured room data

        return $rooms;



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error in kv_bs_available_room_data: ' . $e->getMessage());

        return [];

    }

}



add_action('wp_ajax_niseko_load_room_details', 'niseko_load_room_details');

add_action('wp_ajax_nopriv_niseko_load_room_details', 'niseko_load_room_details');



/**

 * Load detailed room information modal for display

 * Renders room details template with gallery, amenities, and booking info

 * 

 * @return void Sends JSON response with modal HTML

 */

function niseko_load_room_details()

{

    try {

        // ✅ STEP 1: Validate and sanitize parameters

        $room_id = isset($_POST['room_id']) ? $_POST['room_id'] : 0;

        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : 0;



        // Validate room ID is required and positive

        if ($room_id < 1) {

            return wp_send_json_error([

                'message' => 'Room ID is required',

                'code' => 'missing_room_id'

            ], 400);

        }



        // ✅ STEP 2: Type-cast property_id appropriately

        if (is_numeric($property_id)) {

            $property_id = intval($property_id);

        }



        // ✅ STEP 3: Render room details modal template

        ob_start();



        get_template_part('partials/room-details-modal', null, [

            'room_id' => $room_id,

            'property_id' => $property_id,

        ]);



        wp_reset_postdata();



        $html = ob_get_clean();



        // Validate template rendered successfully

        if (empty($html)) {

            return wp_send_json_error([

                'message' => 'Failed to render room details',

                'code' => 'template_render_failed'

            ], 500);

        }



        // ✅ STEP 4: Send successful response with modal HTML

        return wp_send_json_success([

            'html' => $html,

        ]);



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error in niseko_load_room_details: ' . $e->getMessage());



        return wp_send_json_error([

            'message' => 'An error occurred while loading room details',

            'code' => 'unexpected_error'

        ], 500);

    }

}





add_action('wp_ajax_kv_load_accommodation_details', 'kv_ajax_load_accommodation_details');

add_action('wp_ajax_nopriv_kv_load_accommodation_details', 'kv_ajax_load_accommodation_details');



/**

 * Load detailed accommodation/property information modal for display

 * Renders accommodation details template with gallery, description, and facilities

 * 

 * @return void Sends JSON response with modal HTML

 */

function kv_ajax_load_accommodation_details()

{

    try {

        // ✅ STEP 1: Validate and sanitize parameters

        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : 0;



        // Validate property ID is required and positive

        if (empty($property_id)) {

            return wp_send_json_error([

                'message' => 'Property ID is required',

                'code' => 'missing_property_id'

            ], 400);

        }



        // ✅ STEP 2: Type-cast property_id appropriately

        if (is_numeric($property_id)) {

            $property_id = intval($property_id);

        }



        // ✅ STEP 3: Render accommodation details modal template

        ob_start();



        get_template_part('partials/accommodation-details-modal', null, [

            'property_id' => $property_id,

        ]);



        wp_reset_postdata();



        $html = ob_get_clean();



        // Validate template rendered successfully

        if (empty($html)) {

            return wp_send_json_error([

                'message' => 'Failed to render accommodation details',

                'code' => 'template_render_failed'

            ], 500);

        }



        // ✅ STEP 4: Send successful response with modal HTML

        return wp_send_json_success([

            'html' => $html,

        ]);



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        error_log('Error in kv_ajax_load_accommodation_details: ' . $e->getMessage());



        return wp_send_json_error([

            'message' => 'An error occurred while loading accommodation details',

            'code' => 'unexpected_error'

        ], 500);

    }

}



add_action('wp_ajax_load_roomboss_booking', 'kv_ajax_load_roomboss_booking');

add_action('wp_ajax_nopriv_load_roomboss_booking', 'kv_ajax_load_roomboss_booking');



/**

 * Load available rooms for booking with guest details and date range

 * 

 * @return void Sends JSON response with room availability

 */

function kv_ajax_load_roomboss_booking()

{

    try {

        // ✅ STEP 1: Validate and sanitize date inputs

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';

        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';



        if (empty($start_date) || empty($end_date)) {

            return wp_send_json_error([

                'message' => 'Check-in and check-out dates are required',

                'code' => 'missing_dates'

            ], 400);

        }



        // ✅ STEP 2: Validate date format (d/m/Y)

        $checkInObj = DateTime::createFromFormat('d/m/Y', $start_date);

        $checkOutObj = DateTime::createFromFormat('d/m/Y', $end_date);



        if (!$checkInObj || !$checkOutObj) {

            return wp_send_json_error([

                'message' => 'Invalid date format. Please use DD/MM/YYYY',

                'code' => 'invalid_date_format'

            ], 400);

        }



        // ✅ STEP 3: Validate date range (checkout after checkin)

        if ($checkOutObj <= $checkInObj) {

            return wp_send_json_error([

                'message' => 'Check-out date must be after check-in date',

                'code' => 'invalid_date_range'

            ], 400);

        }



        // ✅ STEP 4: Validate and calculate guest count

        $adults = !empty($_POST['adults']) ? intval($_POST['adults']) : 1;

        $children = !empty($_POST['children']) ? intval($_POST['children']) : 0;

        $infants = !empty($_POST['infants']) ? intval($_POST['infants']) : 0;



        $guests = $adults + $children + $infants;



        // ✅ STEP 5: Validate property ID

        $property_id = !empty($_POST['property_id']) ? intval($_POST['property_id']) : 0;



        if ($property_id < 1) {

            return wp_send_json_error([

                'message' => 'Valid property ID is required',

                'code' => 'invalid_property_id'

            ], 400);

        }



        // ✅ STEP 6: Convert date format for API (d/m/Y → d-M-Y)

        $checkIn = $checkInObj->format('d-M-Y');

        $checkOut = $checkOutObj->format('d-M-Y');



        if (!$checkIn || !$checkOut) {

            return wp_send_json_error([

                'message' => 'Failed to process dates',

                'code' => 'date_conversion_failed'

            ], 500);

        }



        // ✅ STEP 7: Build cache key and check transient

        $cache_key = "availability_{$property_id}_{$checkIn}_{$checkOut}";

        $availability = get_transient($cache_key);



        // ✅ STEP 8: Fetch from API if not cached

        // if ($availability === false) {

        if (true) {

            $availability = kv_roomboss_get_availability(

                (string) $property_id,

                $checkIn,

                $checkOut,

                $guests

            );



            // Handle API errors

            if (is_wp_error($availability)) {

                return wp_send_json_error([

                    'message' => 'Error retrieving availability: ' . $availability->get_error_message(),

                    'code' => $availability->get_error_code()

                ], 500);

            }



            // Cache the result for 10 minutes

            if (!empty($availability)) {

                set_transient($cache_key, $availability, 10 * MINUTE_IN_SECONDS);

            }

        }



        // ✅ STEP 9: Validate availability response

        if (empty($availability) || !is_array($availability)) {

            return wp_send_json_success([

                'html' => '<p>No rooms available for the selected dates. Please try different dates.</p>',

                'available_bedroom_types' => [],

            ]);

        }



        // ✅ STEP 10: Validate room data structure

        if (empty($availability[0]['Units'][0]['Rooms'])) {

            return wp_send_json_success([

                'html' => '<p>No rooms available for the selected dates. Please try different dates.</p>',

                'available_bedroom_types' => [],

            ]);

        }

        // pre($availability);



        // $bs_rooms = $availability[0]['Units'][0]['Rooms'];

        $bs_rooms = $availability[0]['Units'][0]['Rooms'];



        // ✅ STEP 11: Prepare room data

        // $grouped_rooms = kv_bs_group_rooms($availability[0]['Units']);

        $grouped_rooms = kv_bs_group_rooms($bs_rooms);

        $available_bedroom_types = get_available_bedrooms($bs_rooms);

        $rate_plans = kv_roomboss_get_rate_plans($grouped_rooms);

        $room_descriptions = kv_roomboss_get_room_descriptions($grouped_rooms);



        // ✅ STEP 12: Render template

        ob_start();

        get_template_part(

            'partials/room-booking-data',

            null,

            [

                'property' => @$availability[0],

                'grouped_rooms' => $grouped_rooms,

                'property_id' => $property_id,

                'rate_plans' => $rate_plans,

                'room_descriptions' => $room_descriptions,

                'dates' => [

                    'check_in' => $start_date,

                    'check_out' => $end_date,

                    'guests' => $guests,

                ],

            ]

        );

        $html = ob_get_clean();



        if (empty($html)) {

            return wp_send_json_error([

                'message' => 'Failed to render rooms template',

                'code' => 'template_render_failed'

            ], 500);

        }



        // ✅ STEP 13: Return success response

        return wp_send_json_success([

            'html' => $html,

            'available_bedroom_types' => $available_bedroom_types,

            'room_count' => count($grouped_rooms),

        ]);



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        return wp_send_json_error([

            'message' => 'An unexpected error occurred while loading rooms',

            'code' => 'unexpected_error',

            'details' => $e->getMessage()

        ], 500);

    }

}



/**

 * Extract unique rate plans from grouped room data

 *

 * @param array $rooms Grouped rooms array from 

 * ()

 * @return array Associative array of rate plans keyed by ratePlanId

 */

function kv_roomboss_get_rate_plans(array $rooms = []): array

{

    $plans = [];



    foreach ($rooms as $p) {

        if (!is_array($p) || empty($p['ratePlanId'])) {

            continue;

        }



        $plans[$p['ratePlanId']] = [

            'name'        => $p['ratePlanName'] ?? '',

            'description' => $p['descriptions'] ?? '',

            'long_desc'   => $p['ratePlanLongDescription'] ?? '',

        ];

    }



    return $plans;

}



function kv_roomboss_get_room_descriptions(array $rooms): array

{

    $desc = [];

    foreach ($rooms as $room) {


        if( !empty($room['RoomId']) ){

            $room_id = $room['RoomId'];
            $ratePlanId = $room['ratePlanId'] ?? null;

            // pre( 'rateplanid' );
            // pre(var_dump( $ratePlanId ) );

            if( empty( $ratePlanId ) ){
                // pre( 'bedbank' );
                if (!empty($room['BedBankDesc'])) {

                    $desc[$room_id] = $room['BedBankDesc'];

                }

            }
            else{
                // pre( 'roomboss' );
                if (!empty($room['ClientRoomDescription'])) {

                    $desc[$room_id] = $room['ClientRoomDescription'];

                }
            }

        }
            // pre( 'desc' );
            // pre( $desc );

    }

    return $desc;
}



/**

 * Group available rooms by room name, collecting rate plans per room type

 *

 * @param array $availableRooms Flat array of room data from booking system API

 * @return array Rooms grouped by RoomName, each with aggregated 'ratePlans' array

 *

 * 3-step flow:

 * 1. Validate input

 * 2. Group rooms by name and collect rate plans

 * 3. Return grouped result

 */

function kv_bs_group_rooms(array $availableRooms): array

{

    // pre($availableRooms, 1);

    // ✅ STEP 1: Validate input

    if (empty($availableRooms)) {

        return [];

    }



    // ✅ STEP 2: Group rooms by name and collect rate plans

    $grouped = $availableRooms;

    // $grouped = [];



    // foreach ($availableRooms as $r) {

    //     if (!is_array($r) || empty($r['RoomName'])) {

    //         continue;

    //     }



    //     $key = $r['RoomName'];



    //     if (!isset($grouped[$key])) {

    //         $grouped[$key] = $r;

    //         $grouped[$key]['ratePlans'] = [];

    //     }



    //     if (!empty($r['ratePlan'])) {

    //         $grouped[$key]['ratePlans'][] = $r['ratePlan'];

    //     }

    // }



    // ✅ STEP 3: Return grouped result

    return $grouped;

}



/**

 * Get room availability from Booking System API

 * 

 * @param string $propertyId Property ID to query

 * @param string $checkIn Check-in date (format: d-M-Y)

 * @param string $checkOut Check-out date (format: d-M-Y)

 * @param int $guests Number of guests

 * @return array|WP_Error Array of properties or WP_Error on failure

 */

function kv_roomboss_get_availability(

    string $propertyId,

    string $checkIn,

    string $checkOut,

    int $guests

) {



    try {

        // ✅ STEP 1: Validate input parameters

        if (empty($propertyId)) {

            return new WP_Error(

                'invalid_property_id',

                'Property ID is required'

            );

        }



        if (empty($checkIn) || empty($checkOut)) {

            return new WP_Error(

                'invalid_dates',

                'Check-in and check-out dates are required'

            );

        }



        // if ($guests < 1 || $guests > 20) {

        //     return new WP_Error(

        //         'invalid_guest_count',

        //         'Guest count must be between 1 and 20'

        //     );

        // }



        // ✅ STEP 2: Get property details

        $resort_id = get_resort_id_by_property_id($propertyId);



        if (empty($resort_id)) {

            return new WP_Error(

                'resort_not_found',

                'Resort ID not found for property: ' . $propertyId

            );

        }



        // ✅ STEP 3: Calculate duration

        $duration = get_duration_from_date($checkIn, $checkOut);



        if ($duration < 1) {

            return new WP_Error(

                'invalid_date_range',

                'Stay duration must be at least 1 day'

            );

        }



        // ✅ STEP 4: Build booking system API arguments

        $args = [

            'start_date' => $checkIn,

            'resortId' => intval($resort_id),

            'end_date' => $checkOut,

            'channel' => true,

            'bedBank' => true,

            'offlineProperties' => false,

            'duration' => $duration,

            'maxPersons' => $guests,

            'propertyIds' => [intval($propertyId)],

            'adults' => [$guests],

        ];



        // ✅ STEP 5: Build API request

        $url = KV_BOOKING_SYSTEM_BASE . '/api/quotation-filteration';

        $bs_args = kv_booking_system_filter_args(KV_BS_authToken, $args);

        // pre($url, 0);

        // pre($bs_args, 0);



        if (!$bs_args) {

            return new WP_Error(

                'invalid_request_args',

                'Failed to build booking system request arguments'

            );

        }



        // ✅ STEP 6: Make API request

        $response = wp_remote_get($url, $bs_args);



        if (is_wp_error($response)) {

            return new WP_Error(

                'api_request_failed',

                'Failed to connect to booking system: ' . $response->get_error_message()

            );

        }



        // ✅ STEP 7: Validate HTTP response code

        $http_code = wp_remote_retrieve_response_code($response);



        if ($http_code !== 200) {

            return new WP_Error(

                'api_error',

                'Booking system returned HTTP ' . $http_code

            );

        }



        // ✅ STEP 8: Parse response body

        $response_body = wp_remote_retrieve_body($response);

        if (empty($response_body)) {

            return new WP_Error(

                'empty_response',

                'Booking system returned empty response'

            );

        }



        $body = json_decode($response_body, true);

        

        if (!is_array($body)) {

            return new WP_Error(

                'invalid_json',

                'Booking system returned invalid JSON'

            );

        }



        // ✅ STEP 9: Validate and extract properties

        if (empty($body['properties']) || !is_array($body['properties'])) {

            return [];

        }



        // ✅ STEP 10: Return properties array

        return $body['properties'];



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        return new WP_Error(

            'unexpected_error',

            'Error retrieving availability: ' . $e->getMessage()

        );

    }

}



/* function to show banner after first 3 records */

function kv_get_expert_recommendation_cta() {

    // Using the background image provided in the existing code as a placeholder

    

    $html = '<div class="expert-recommendation-cta">';

        // Left Content Area

            if(get_field('exp_acc_content', 'options')) {

                $html .= '<p class="expert-cta-content">'.get_field('exp_acc_content', 'options').'</p>';

            }

            if(get_field('exp_acc_cta', 'options')) {

                $html .= '<a href="' . get_field('exp_acc_cta_link', 'options') . '" class="expert-cta-button btn">' . get_field('exp_acc_cta', 'options') . '</a>';

            }

    $html .= '</div>';// End of CTA Container

    

    return $html;

}



/**

 * Generate the stronger CTA banner for page 2 results

 */

function kv_get_stronger_recommendation_cta() {



    $sec_cont = get_field('strong_rec_cont', 'options');

    $sec_cont = $sec_cont ? $sec_cont : 'Let us recommend the best available options for your dates.';



    $cta_text = get_field('strong_rec_cta', 'options');

    $cta_text = $cta_text ? $cta_text : 'Get Expert Recommendations';



    $cta_link = get_field('strong_rec_link', 'options');

    $cta_link = $cta_link ? $cta_link : '/get-a-quote/';



    $html = '<div class="strong-recommendation-cta stronger-message">';

    

        $html .= '<div class="acco cta">';

                $html .= '<h3>'.$sec_cont.'</h3>';

            $html .= '<a class="btn" href="'.$cta_link.'" class="expert-cta-button btn">Get Expert Recommendations</a>';

    

        $html .= '</div>';

    $html .= '</div>';

    

    

    return $html;

}



add_action('wp_ajax_kv_get_resort_areas', 'kv_get_resort_areas_ajax');

add_action('wp_ajax_nopriv_kv_get_resort_areas', 'kv_get_resort_areas_ajax');



/**

 * AJAX handler to retrieve base areas for a specific resort.

 * Triggered when the resort selection changes in the filter panel.

 */

function kv_get_resort_areas_ajax() {

    $resort = isset($_POST['resort']) ? sanitize_text_field($_POST['resort']) : '';



    // If resort is empty, get_accommodation_base_areas will now return all allowed areas.

    $areas = get_accommodation_base_areas($resort);



    wp_send_json_success($areas);

}