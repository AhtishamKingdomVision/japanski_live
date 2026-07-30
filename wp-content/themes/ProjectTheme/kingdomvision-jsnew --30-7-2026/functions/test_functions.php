<?php

// Debug/test triggers

@$_GET['hz_test'] == 'yes' ? add_action('wp_head', 'hz_testing') : '';

@$_GET['test'] == 'yes' ? add_action('wp_head', 'testing') : '';

@$_GET['del_reviews'] == 'yes' ? add_action('wp_head', 'del_reviews') : '';

@$_GET['chk_dup_reviews'] == 'yes' ? add_action('wp_head', 'chk_dup_reviews') : '';

@$_GET['update_menu_order'] == 'yes' ? add_action('wp_head', 'kv_update_accommodation_menu_order') : '';

@$_GET['get_acc_by_order'] == 'yes' ? add_action('wp_head', 'kv_get_accommodation_by_menu_order') : '';

@$_GET['get_product_reviews'] == 'yes' ? add_action('wp_head', 'kv_cron_fetch_product_reviews_fn') : '';

@$_GET['check_review_data'] == 'yes' ? add_action('wp_head', 'kv_chk_review_data_fn') : '';

@$_GET['show_thmem_url'] == 'yes' ? add_action('wp_head', 'hz_theme_url') : '';

@$_GET['kv_sync_live_meta_to_staging'] == 'yes' ? add_action('wp_head', 'kv_sync_live_custom_dates_to_staging_modified_dates') : '';

@$_GET['admin_rep_func'] == 'yes' ? add_action('admin_init', 'admin_rep_func') : '';

@$_GET['kv_send_data_to_trip'] == 'yes' ? add_action('wp_head', 'kv_send_data_to_trip_func') : '';

function kv_send_data_to_trip_func(){
    // Corrected data variable structure
    $tm_data = [
        "data" => [
            "amount_from" => "40233",
            "amount_to" => "84000",
            "country" => "GB",
            "currency_from" => "GBP",
            "currency_to" => "JPY",
            "expiration_date" => "2026-06-25T17:03:25Z",
            "external_reference" => "JSE15678_3",
            "fields" => [
                "booking_reference" => "JSE15678_3",
                "card_issued_redirect" => "true",
            ],
            "payment_id" => "JSE15694788454",
            "payment_method" => [
                "type" => "card",
            ],
            "status" => "guaranteed",
        ]
    ];

    // Log the actual array payload (Changed $data to $tm_data)
    cf_log( $tm_data, 'hz-webhook-body' );

    // Configure the remote post to properly send pure JSON
    $resp = wp_remote_post( 'https://trip.japanskiexperience.com/api/payment-notification', [
        'headers' => [
            'Content-Type'    => 'application/json',
            'User-Agent'      => 'PostmanRuntime/7.32.3', // Mimic Postman
            'Accept'          => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection'      => 'keep-alive',
        ],
        'body'        => wp_json_encode( $tm_data ),
        'timeout'     => 45,
        'data_format' => 'body',
    ]);


    pre( $resp );
    cf_log( $resp, 'hz-webhook-response' );
}


function del_reviews(){

    $allposts = get_posts( array('post_type' => 'reviews', 'numberposts' => -1) );

    foreach ( $allposts as $eachpost ) {

        pre( 'deleting review '. $eachpost->ID );

        wp_delete_post( $eachpost->ID, true ); // Set to 'true' to bypass trash

        pre( 'deleted review '. $eachpost->ID );

    }

}



function hz_theme_url(){

    pre( get_template_directory_uri() );

}



function kv_chk_review_data_fn(){

    pre( get_review_data() );

}



function chk_dup_reviews() {

    $allposts = get_posts(array('post_type' => 'reviews', 'numberposts' => -1));

    $store_ids = [];



    // Collect all store_review_id values with their post IDs

    foreach ($allposts as $eachpost) {

        pre( 'post id' );

        pre( $eachpost->ID );

        

        $store_id = get_field('store_review_id', $eachpost->ID);

        pre( 'store_review_id' );

        pre( $store_id );

    }

}



function hz_testing(){

    // kv_cron_fetch_reviews_fn();

    // hz_get_data_from_booking_sys_func();

    // pre( __media_sideload_image( KV_BOOKING_SYSTEM_BASE . '/storage/photos/1/Phoenix Hotel/Phoenix Hotel superior-singe-1-1536x1024.jpg', 75759) );

    // hz_get_data_from_booking_sys_func();

    // show_all_post_types();

    pre( var_dump( is_page( 'deals' ) ) );

}



function testing(){

    hz_get_data_from_booking_sys_func();

}





function kv_get_accommodation_by_menu_order() {

    $args = [

        'post_type'      => 'accommodation',

        'posts_per_page' => -1,

        'post_status'    => 'any',

        'orderby'        => 'menu_order',

        'order'          => 'ASC',

    ];



    $posts = get_posts($args);



    foreach ($posts as $post) {

        pre($post->menu_order . ' - ' . $post->post_title . ' (ID: ' . $post->ID . ')');

    }

}



/*show all post types in wp_head*/



function test_fetch_reviews_fn() {

    

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



        pre( 'page' );

        pre( $page );



        pre( 'total_reviews' );

        pre( $body['stats']['total_reviews'] );



        pre( 'reviews count' );

        pre( count( $body['reviews'] ) );



        /*

         * Process reviews

         */

        foreach ( $body['reviews'] as $review ) {

            pre( 'review' );

            pre( $review );

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

            pre( 'exist' );

            pre( $exists );



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

                'post_title'   => sanitize_text_field( $author ?: 'Anonymous Review' ),

                'post_type'    => 'reviews',

                'post_status'  => 'publish',

                'post_content' => $review['comments'] ?? '',

            ]);



            pre( 'post_id' );

            pre( $post_id );



            if ( ! $post_id || is_wp_error( $post_id ) ) {



                cf_log( 'review failed to create msg: '.$post_id->get_error_message() , 'err_review_cron', 'txt', false, true );

                continue;

            }

            pre( 'rating' );

            pre( $review['rating'] );





            pre( 'date_created' );

            pre( $review['date_created'] );





            pre( 'store_review_id' );

            pre( $review['store_review_id'] );



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



                pre( 'tags' );

                pre( $tag_slugs );



                if ( ! empty( $tag_slugs ) ) {

                    wp_set_object_terms(

                        $post_id,

                        $tag_slugs,

                        'review_tags',

                        false

                    );



                }

            }

        }



        $page++;

    }

}



// function __media_sideload_image($file, $post_id = 0, $desc = null, $return_type = 'id') {



//     $file = str_replace(' ', '%20', $file);

//     $thumbnail_id = 0;

//     if (empty($file) || !filter_var($file, FILTER_VALIDATE_URL)) {

//         return new WP_Error('invalid_url', 'Invalid image URL');

//     }



//     // Get clean filename from URL

//     $filename = wp_basename(parse_url($file, PHP_URL_PATH));



//     // OPTIONAL: check if attachment already exists

//     $existing = get_posts([

//         'post_type'   => 'attachment',

//         'post_status'  => 'inherit',

//         'numberposts'  => 1,

//         'fields'       => 'ids',

//         'meta_query'   => [

//             [

//                 'key'     => '_source_url',

//                 'value'   => pathinfo($filename, PATHINFO_FILENAME),

//                 'compare' => 'LIKE'

//             ]

//         ]

//     ]);



//     pre( 'file name' );

//     pre( $filename );



//     if (!empty($existing)) {

//         pre( 'existing' );

//         pre( $existing );

//         $thumbnail_id = $existing[0];

//     } else {



//         pre( 'not exist' );

//         require_once ABSPATH . 'wp-admin/includes/file.php';

//         require_once ABSPATH . 'wp-admin/includes/media.php';

//         require_once ABSPATH . 'wp-admin/includes/image.php';



//         $file_array = [

//             'name'     => $filename,

//             'tmp_name' => download_url($file),

//         ];



//         pre( 'filearray' );

//         pre( $file_array );



//         if (is_wp_error($file_array['tmp_name'])) {

//             return $file_array['tmp_name'];

//         }



//         $thumbnail_id = media_handle_sideload($file_array, $post_id, $desc);



//         pre( 'id' );

//         pre( $thumbnail_id );

//         if (is_wp_error($thumbnail_id)) {

//             @unlink($file_array['tmp_name']);

//             return $thumbnail_id;

//         }



//         add_post_meta($thumbnail_id, '_source_url', $file);

//     }



//     $post_thmb_id = get_post_thumbnail_id( $post_id );



//     if( $post_thmb_id != $thumbnail_id ){

//         set_post_thumbnail( $post_id, $thumbnail_id );

//     }



//     return ($return_type === 'id') ? $thumbnail_id : wp_get_attachment_url($thumbnail_id);

// }



function hz_clean_string( $string ){

    $html = html_entity_decode( $string );

    pre( $html );

    $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $html);

    return $clean;

}



function get_product_reviews() {



    $store = 'japanskiexperience-com1';

    $api_key  = 'ae0427dc29a62e2320110a6c157bc45b';

    $per_page = 100;

    $sort     = 'date_created';



    global $wpdb;



    $accommodation_ids = get_posts([

        'post_type'      => 'accommodation',

        'posts_per_page' => -1,

        'post_status'    => 'publish',

        'fields'         => 'ids',

        'meta_query'     => [

            [

                'key'     => 'prd_sku',

                'compare' => 'EXISTS',

            ],

        ],

    ]);



    $sku = 13;

    // foreach ( $accommodation_ids as $acc_id ) {



    //     $sku .= get_field( 'prd_sku', $acc_id ).';';

    // }



    $page = 1; // ✅ RESET per SKU



    while ( true ) {



        $url = 'https://api.reviews.io/product/review?store=' . $store . '&sku=' . $sku . '&per_page='.$per_page.'&page='.$page.'&verified_only=true&comments_only=true';



        pre( $sku );



        $response = wp_remote_get( $url, [

            'headers' => [

                'Content-Type' => 'application/json',

                'store'        => $store,

                'apikey'       => $api_key,

            ],

            'timeout' => 20,

        ]);



        if ( is_wp_error( $response ) ) {

            break;

        }



        $body = json_decode( wp_remote_retrieve_body( $response ), true );



        $reviews = $body['reviews'];

        $data = $reviews[ 'data' ];



        if ( empty( $data ) ) {

            cf_log( "No more reviews for SKU: {$sku}", 'review_cron', 'txt', true, true );

            break;

        }



        foreach ( $data as $review ) {



            if ( empty( $review['product_review_id'] ) ) {

                continue;

            }

            // pre( $review['reviewer']['first_name'] );

            $first_name = hz_clean_string( $review['reviewer']['first_name'] );

            // pre( $first_name );

            // pre( hz_clean_string( $review['reviewer']['last_name'] ) );

            pre( $review );

            }



        $page++; // ✅ pagination only for THIS SKU

    }

}





/**

 * Sync LIVE → STAGING post publish dates

 * Matching by post_name (slug) — safest approach

 */

function kv_validate_wpdb_connection($wpdb_instance, $label = 'DB') {



    // Force connection check

    $wpdb_instance->check_connection();



    if (!empty($wpdb_instance->last_error)) {

        return [

            'success' => false,

            'message' => "{$label} connection failed: " . $wpdb_instance->last_error

        ];

    }



    return [

        'success' => true,

        'message' => "{$label} connection OK"

    ];

}



function kv_sync_live_to_staging_post_dates() {



    // LIVE DB

    $live_wpdb = new wpdb(

        'u1rnuegu3o4go',

        'qjgmy4lnrq4k',

        'db3xe5ybvxulet',

        '127.0.0.1'

    );



    // STAGING DB

    $staging_wpdb = new wpdb(

        'uc4xgcbgolwmf',

        'zhmky9xwxfd1',

        'dbbhtejazeldrr',

        'localhost'

    );



    // Validate connections

    $live_check = kv_validate_wpdb_connection($live_wpdb, 'LIVE');

    if (!$live_check['success']) {

        return $live_check;

    }



    $staging_check = kv_validate_wpdb_connection($staging_wpdb, 'STAGING');

    if (!$staging_check['success']) {

        return $staging_check;

    }



    // Fetch LIVE posts

    $live_posts = $live_wpdb->get_results("

        SELECT

            ID,

            post_name,

            post_date,

            post_date_gmt,

            post_modified,

            post_modified_gmt

        FROM wp_posts

        WHERE post_type = 'post'

        AND post_status = 'publish'

    ");



    $updated = [];

    $not_found = [];



    foreach ($live_posts as $live_post) {



        $slug = $live_post->post_name;



        // Find match in STAGING

        $staging_post = $staging_wpdb->get_row(

            $staging_wpdb->prepare(

                "SELECT ID 

                 FROM wp_posts 

                 WHERE post_type = 'post'

                 AND post_name = %s

                 LIMIT 1",

                $slug

            )

        );



        if (!$staging_post) {

            $not_found[] = $slug;

            continue;

        }



        // Update STAGING dates from LIVE

        $staging_wpdb->query(

            $staging_wpdb->prepare(

                "UPDATE wp_posts

                 SET post_date = %s,

                     post_date_gmt = %s,

                 WHERE ID = %d",

                $live_post->post_date,

                $live_post->post_date_gmt,

                $staging_post->ID

            )

        );



        $updated[] = ['staging' => 

        $staging_post->ID,

        'live' => $live_post->ID];

    }

    pre( ['updated' => $updated, 'missing_slugs' => $not_found] );

    return [

        'updated' => $updated,

        'missing_slugs' => $not_found

    ];

}



/**

 * Sync LIVE custom field "post_date"

 * into STAGING post_modified dates

 *

 * LIVE DB     : db3xe5ybvxulet

 * STAGING DB  : dbbhtejazeldrr

 *

 * Matching method:

 * - post_name (slug)

 *

 * Source meta:

 * - post_date

 *

 * Updates:

 * - post_modified

 * - post_modified_gmt

 */



function kv_sync_live_custom_dates_to_staging_modified_dates() {



    // ---------------------------------

    // LIVE DB CONNECTION

    // ---------------------------------

    $live_wpdb = new wpdb(

        'u1rnuegu3o4go',

        'qjgmy4lnrq4k',

        'db3xe5ybvxulet',

        '127.0.0.1'

    );



    // STAGING DB

    $staging_wpdb = new wpdb(

        'uc4xgcbgolwmf',

        'zhmky9xwxfd1',

        'dbbhtejazeldrr',

        'localhost'

    );



    // ---------------------------------

    // Validate connections

    // ---------------------------------

    $live_wpdb->check_connection();

    $staging_wpdb->check_connection();



    if (!empty($live_wpdb->last_error)) {



        pre([

            'live_db_error' => $live_wpdb->last_error

        ]);



        return;

    }



    if (!empty($staging_wpdb->last_error)) {



        pre([

            'staging_db_error' => $staging_wpdb->last_error

        ]);



        return;

    }



    // ---------------------------------

    // Fetch LIVE posts

    // ---------------------------------

    $live_posts = $live_wpdb->get_results("

        SELECT ID, post_name

        FROM wp_posts

        WHERE post_type = 'post'

        AND post_status = 'publish'

    ");



    pre([

        'live_posts_found' => count($live_posts)

    ]);



    $updated = [];

    $missing_posts = [];

    $missing_meta = [];

    $failed_dates = [];

    $failed_updates = [];



    // ---------------------------------

    // Loop LIVE posts

    // ---------------------------------

    foreach ($live_posts as $live_post) {



        $slug = trim($live_post->post_name);



        pre([

            'processing_slug' => $slug

        ]);



        // ---------------------------------

        // Find STAGING post by slug

        // ---------------------------------

        $staging_post = $staging_wpdb->get_row(

            $staging_wpdb->prepare(

                "

                SELECT ID

                FROM wp_posts

                WHERE post_type = 'post'

                AND post_name = %s

                LIMIT 1

                ",

                $slug

            )

        );



        if (!$staging_post) {



            $missing_posts[] = $slug;



            pre([

                'missing_staging_post' => $slug

            ]);



            continue;

        }



        // ---------------------------------

        // Get LIVE custom field post_date

        // ---------------------------------

        $live_meta_date = $live_wpdb->get_var(

            $live_wpdb->prepare(

                "

                SELECT meta_value

                FROM wp_postmeta

                WHERE post_id = %d

                AND meta_key = 'post_date'

                LIMIT 1

                ",

                $live_post->ID

            )

        );



        if (empty($live_meta_date)) {



            $missing_meta[] = $slug;



            pre([

                'missing_post_date_meta' => $slug

            ]);



            continue;

        }



        pre([

            'raw_meta_date' => $live_meta_date

        ]);



        // ---------------------------------

        // Convert date

        // ---------------------------------

        $post_modified = new_hz_convert_date_format(

            $live_meta_date,

            'Y-m-d H:i:s'

        );



        if (!$post_modified) {



            $failed_dates[] = [

                'slug' => $slug,

                'raw_date' => $live_meta_date

            ];



            pre([

                'date_conversion_failed' => $slug,

                'raw_date' => $live_meta_date

            ]);



            continue;

        }



        // ---------------------------------

        // Create GMT date

        // ---------------------------------

        $date = new DateTime(

            $post_modified,

            wp_timezone()

        );



        $gmt_date = clone $date;



        $gmt_date->setTimezone(

            new DateTimeZone('UTC')

        );



        $post_modified_gmt = $gmt_date->format(

            'Y-m-d H:i:s'

        );



        pre([

            'post_modified' => $post_modified,

            'post_modified_gmt' => $post_modified_gmt

        ]);



        // ---------------------------------

        // Update STAGING post dates

        // ---------------------------------

        $result = $staging_wpdb->query(

            $staging_wpdb->prepare(

                "

                UPDATE wp_posts

                SET post_modified = %s,

                    post_modified_gmt = %s

                WHERE ID = %d

                ",

                $post_modified,

                $post_modified_gmt,

                $staging_post->ID

            )

        );



        pre([

            'update_result' => $result,

            'last_error' => $staging_wpdb->last_error,

            'slug' => $slug

        ]);



        if ($result === false) {



            $failed_updates[] = [

                'slug' => $slug,

                'error' => $staging_wpdb->last_error

            ];



        } else {



            $updated[] = [

                'stg_id' => $staging_post->ID,

                'liv_id' => $live_post->ID,

                'slug' => $slug,

                'post_modified' => $post_modified

            ];

        }

    }



    // ---------------------------------

    // Final report

    // ---------------------------------

    $report = [

        'updated_count' => count($updated),

        'updated' => $updated,

        'missing_posts' => $missing_posts,

        'missing_meta' => $missing_meta,

        'failed_dates' => $failed_dates,

        'failed_updates' => $failed_updates

    ];



    pre($report);



    return $report;

}





/**

 * Replace string values in serialized post meta data

 * Handles ACF fields and nested arrays/objects

 * 

 * Trigger: ?admin_rep_func=yes

 */

function admin_rep_func() {



    // Safety check - only run for administrators

    if (!current_user_can('manage_options')) {

        wp_die('Unauthorized access');

    }



    global $wpdb;



    $old = 'get-a-quote';

    $new = 'get-expert-recommendations';



    // Step 1: Find all meta values containing the old string

    $query = $wpdb->prepare(

        "SELECT post_id, meta_key, meta_value, meta_id

         FROM {$wpdb->postmeta}

         WHERE meta_value LIKE %s",

        '%' . $wpdb->esc_like($old) . '%'

    );



    $rows = $wpdb->get_results($query);



    if (empty($rows)) {

        echo '<div style="padding:20px;background:#f0f0f0;border:1px solid #999;margin:20px;font-family:monospace;">';

        echo '<h2>No records found</h2>';

        echo '<p>No meta values contain: <strong>' . esc_html($old) . '</strong></p>';

        echo '</div>';

        exit;

    }



    $results = [

        'total_found' => count($rows),

        'updated' => [],

        'failed' => [],

        'skipped' => [],

    ];



    // Step 2: Process each record

    foreach ($rows as $row) {

        $post_id = $row->post_id;

        $meta_key = $row->meta_key;

        $meta_value = $row->meta_value;

        $meta_id = $row->meta_id;



        try {

            // Step 3: Unserialize the data

            $unserialized_value = maybe_unserialize($meta_value);



            // Step 4: Recursively replace the string

            $replaced_value = kv_replace_recursive_value($unserialized_value, $old, $new);



            // Step 5: Check if anything actually changed

            if (kv_serialize_safely($unserialized_value) === kv_serialize_safely($replaced_value)) {

                $results['skipped'][] = [

                    'post_id' => $post_id,

                    'meta_key' => $meta_key,

                    'reason' => 'No actual changes after replacement'

                ];

                continue;

            }



            // Step 6: Re-serialize the modified data

            $serialized_value = kv_serialize_safely($replaced_value);



            // Step 7: Update the database directly

            $update = $wpdb->update(

                $wpdb->postmeta,

                ['meta_value' => $serialized_value],

                ['meta_id' => $meta_id],

                ['%s'],

                ['%d']

            );



            if ($update === false) {

                $results['failed'][] = [

                    'post_id' => $post_id,

                    'meta_key' => $meta_key,

                    'error' => $wpdb->last_error ?: 'Unknown error'

                ];

            } else {

                $results['updated'][] = [

                    'post_id' => $post_id,

                    'meta_key' => $meta_key,

                    'meta_id' => $meta_id,

                    'before' => strlen($meta_value) . ' bytes',

                    'after' => strlen($serialized_value) . ' bytes'

                ];

            }



        } catch (Exception $e) {

            $results['failed'][] = [

                'post_id' => $post_id,

                'meta_key' => $meta_key,

                'error' => $e->getMessage()

            ];

        }

    }



    // Step 8: Display results

    echo '<div style="padding:20px;background:#fff;border:2px solid #333;margin:20px;font-family:monospace;font-size:12px;">';

    echo '<h2 style="color:#333;">Meta Value Replacement Report</h2>';

    

    echo '<div style="margin-bottom:20px;padding:10px;background:#e8f5e9;border-left:4px solid #4caf50;">';

    echo '<strong style="color:#2e7d32;">Summary</strong><br>';

    echo 'Total found: ' . $results['total_found'] . '<br>';

    echo 'Updated: <span style="color:#2e7d32;font-weight:bold;">' . count($results['updated']) . '</span><br>';

    echo 'Failed: <span style="color:#d32f2f;font-weight:bold;">' . count($results['failed']) . '</span><br>';

    echo 'Skipped: <span style="color:#f57f17;font-weight:bold;">' . count($results['skipped']) . '</span>';

    echo '</div>';



    if (!empty($results['updated'])) {

        echo '<div style="margin-bottom:20px;padding:10px;background:#e3f2fd;border-left:4px solid #2196f3;">';

        echo '<strong style="color:#1565c0;">Updated Records (' . count($results['updated']) . ')</strong><br>';

        foreach ($results['updated'] as $item) {

            echo 'Post: ' . $item['post_id'] . ' | Key: ' . esc_html($item['meta_key']) . ' | ' . $item['before'] . ' → ' . $item['after'] . '<br>';

        }

        echo '</div>';

    }



    if (!empty($results['failed'])) {

        echo '<div style="margin-bottom:20px;padding:10px;background:#ffebee;border-left:4px solid #d32f2f;">';

        echo '<strong style="color:#b71c1c;">Failed Records (' . count($results['failed']) . ')</strong><br>';

        foreach ($results['failed'] as $item) {

            echo 'Post: ' . $item['post_id'] . ' | Key: ' . esc_html($item['meta_key']) . ' | Error: ' . esc_html($item['error']) . '<br>';

        }

        echo '</div>';

    }



    if (!empty($results['skipped'])) {

        echo '<div style="margin-bottom:20px;padding:10px;background:#fff3e0;border-left:4px solid #f57f17;">';

        echo '<strong style="color:#e65100;">Skipped Records (' . count($results['skipped']) . ')</strong><br>';

        foreach ($results['skipped'] as $item) {

            echo 'Post: ' . $item['post_id'] . ' | Key: ' . esc_html($item['meta_key']) . ' | Reason: ' . esc_html($item['reason']) . '<br>';

        }

        echo '</div>';

    }



    echo '<div style="margin-top:20px;padding:10px;background:#f5f5f5;border:1px solid #ccc;">';

    echo '<strong>Replaced:</strong> ' . esc_html($old) . ' → ' . esc_html($new) . '<br>';

    echo '<strong>Time:</strong> ' . current_time('mysql') . '<br>';

    echo '<strong>User:</strong> ' . esc_html(wp_get_current_user()->user_login);

    echo '</div>';



    echo '</div>';



    exit;

}



/**

 * Recursively replace string in nested arrays, objects, and strings

 * Handles deeply nested ACF field values

 */

function kv_replace_recursive_value($data, $old, $new) {

    

    // Handle strings

    if (is_string($data)) {

        return str_replace($old, $new, $data);

    }



    // Handle arrays

    if (is_array($data)) {

        $result = [];

        foreach ($data as $key => $value) {

            $result[$key] = kv_replace_recursive_value($value, $old, $new);

        }

        return $result;

    }



    // Handle objects

    if (is_object($data)) {

        $obj = clone $data;

        foreach ($obj as $key => $value) {

            $obj->$key = kv_replace_recursive_value($value, $old, $new);

        }

        return $obj;

    }



    // Return other data types as-is

    return $data;

}



/**

 * Safely serialize data, handling edge cases

 * This ensures proper comparison of serialized values

 */

function kv_serialize_safely($data) {

    return serialize($data);

}

