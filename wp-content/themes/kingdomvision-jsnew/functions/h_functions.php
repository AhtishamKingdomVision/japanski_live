<?php

function hz_js_to_enque()

{



    wp_register_style('jquery-ui', '//code.jquery.com/ui/1.11.3/themes/smoothness/jquery-ui.css', false, '1.11.3');



    // slick-carousel

    wp_register_style('slick-carousel', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css', false);

    wp_enqueue_style('slick-carousel');

    wp_register_script('slick-carousel', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), true);

    wp_enqueue_script('slick-carousel');



    // ion.rangeSlider

    wp_register_style('ion-rangeSlider-styles', get_template_directory_uri() . '/ion.rangeSlider-master/css/ion.rangeSlider.css?v=' . filemtime(get_template_directory() . '/ion.rangeSlider-master/css/ion.rangeSlider.css'), false);

    wp_enqueue_style('ion-rangeSlider-styles');

    wp_register_script('ion-rangeSlider-scripts', get_template_directory_uri() . '/ion.rangeSlider-master/js/ion.rangeSlider.js?v=' . filemtime(get_template_directory() . '/ion.rangeSlider-master/js/ion.rangeSlider.js'),  array('jquery'), false);

    wp_enqueue_script('ion-rangeSlider-scripts');



    // Date Dropper

    wp_register_script('datedropper_en', get_template_directory_uri() . '/js/datedropper.min.js', array('jquery', 'kv-script'), '1.0', true);

    wp_enqueue_script('datedropper_en');



    

    // LEAFLET FILES

    // wp_register_style('leaflet', get_template_directory_uri() . '/leaflet/dist/leaflet.css', false);

    wp_register_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', false);

    wp_enqueue_style('leaflet');

    wp_register_script('leaflet', get_template_directory_uri() . '/leaflet/dist/leaflet.js', array('jquery'), false);

    wp_enqueue_script('leaflet');



    // Accommodation Filters Assets (Global)

    $kv_filters_ver = '20260716-lm4.' . (string) filemtime( get_template_directory() . '/js/accommodation-filters.js' );

    wp_enqueue_style('accommodation-filters', get_template_directory_uri() . '/css/accommodation-filters.css', array(), filemtime(get_template_directory() . '/css/accommodation-filters.css'));

    wp_enqueue_script('accommodation-filters', get_template_directory_uri() . '/js/accommodation-filters.js', array('jquery', 'kv-script'), $kv_filters_ver, true);

    

    /* only for single accomodation pages */

    if (is_singular('accommodation')) {

        wp_enqueue_script('room-section', get_template_directory_uri().'/js/rooms-section.js', array('jquery', 'kv-script'), filemtime(get_template_directory() . '/js/rooms-section.js'), true);



        // Cart expiration timer (RoomBoss/BedBank)

        wp_enqueue_style('cart-timer', get_template_directory_uri() . '/css/cart-timer.css', array(), filemtime(get_template_directory() . '/css/cart-timer.css'));

        wp_enqueue_script('cart-timer', get_template_directory_uri() . '/js/kv-cart-timer.js', array('jquery', 'kv-script'), filemtime(get_template_directory() . '/js/kv-cart-timer.js'), true);

    }

    wp_enqueue_style('air_datepicker', '//cdn.jsdelivr.net/npm/air-datepicker@3.3.5/air-datepicker.min.css', false, '3.3.5');

    wp_enqueue_script('air_datepicker', '//cdn.jsdelivr.net/npm/air-datepicker@3.3.5/air-datepicker.min.js', array('jquery', 'kv-script', 'wp-util'), '3.3.5', true);



    // confirm-booking

    if ( is_page_template( 'booking_confirmation.php' ) || is_page( 26812 ) ) {



        /*flywire*/

        wp_register_script('booking-manager', get_template_directory_uri() .'/js/booking-manager.js', array(), filemtime(get_template_directory() . '/js/booking-manager.js'), true);

        wp_enqueue_script('booking-manager');



        // Cart expiration timer (RoomBoss/BedBank) on the booking page

        wp_enqueue_style('cart-timer', get_template_directory_uri() . '/css/cart-timer.css', array(), filemtime(get_template_directory() . '/css/cart-timer.css'));

        wp_enqueue_script('cart-timer', get_template_directory_uri() . '/js/kv-cart-timer.js', array('jquery', 'kv-script'), filemtime(get_template_directory() . '/js/kv-cart-timer.js'), true);

    }

    

    wp_register_script('new-guest-sync', get_template_directory_uri() .'/js/new-guest-sync.js', array(), filemtime(get_template_directory() . '/js/new-guest-sync.js'), true);

    wp_enqueue_script('new-guest-sync');



    if( is_accommodation() ) {

        // isotope

        wp_enqueue_script('isotope', 'https://unpkg.com/isotope-layout@3.0.6/dist/isotope.pkgd.min.js?ver=3.3.5', array('jquery'), '3.3.5', true);

    }



    wp_register_script('js-cookies', 'https://cdn.jsdelivr.net/npm/js-cookie@3.0.5/dist/js.cookie.min.js', array(), false, true);

    wp_enqueue_script('js-cookies');





}



function get_acc_price($key = 'max', $term_slug = '')

{

    global $wpdb;

    $prefix = $wpdb->prefix;



    // Sanitize the aggregation type (only allow min or max)

    $key = strtolower($key) === 'min' ? 'MIN' : 'MAX';



    // Base SQL with taxonomy join

    $query = "

    SELECT {$key}(CAST(pm.meta_value AS DECIMAL(10,2))) 

    FROM {$prefix}postmeta pm

    INNER JOIN {$prefix}posts p ON pm.post_id = p.ID

    INNER JOIN {$prefix}term_relationships tr ON p.ID = tr.object_id

    INNER JOIN {$prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id

    INNER JOIN {$prefix}terms t ON tt.term_id = t.term_id

    WHERE pm.meta_key = 'min_room_price'

    AND pm.meta_value > 0

    AND p.post_type = 'accommodation'

    AND p.post_status = 'publish'

    AND tt.taxonomy = 'accommodation-cat'

    ";



    // Add term slug condition if provided

    $params = [];

    if (! empty($term_slug)) {

        $query .= " AND t.slug = %s";

        $params[] = $term_slug;

    }



    // Prepare query if term is provided

    if (! empty($params)) {

        $prepared_query = $wpdb->prepare($query, ...$params);

    } else {

        $prepared_query = $query;

    }



    // Execute and return float value

    return (float) $wpdb->get_var($prepared_query);

}



add_action('wp_enqueue_scripts', 'hz_js_to_enque');



/* enqueue admin styles */

add_action('admin_enqueue_scripts', 'hz_admin_styles');

function hz_admin_styles()

{

    wp_enqueue_style('admin-style', get_template_directory_uri() . '/admin-style.css', array(), filemtime(get_template_directory() . '/admin-style.css'));

}



function sync_custom_functions_file()

{

    $file_url = 'https://raw.githubusercontent.com/HumzaKV/hz_essentials/main/essentials.php';

    $theme_dir = get_stylesheet_directory();

    $target_file = $theme_dir . '/functions/essentials.php';

    if (!file_exists($target_file)) {

        file_put_contents($target_file, '<?php // Version:0.0.0');

    }



    $remote_response = wp_remote_get($file_url);

    if (is_wp_error($remote_response)) return;

    $remote_code = wp_remote_retrieve_body($remote_response);

    if (empty($remote_code)) return;

    // Get remote version

    preg_match('/Version:\s*([\d.]+)/i', $remote_code, $remote_matches);

    $remote_version = $remote_matches[1] ?? null;

    // cf_log( 'remote_version', 'version_log' );

    // cf_log( $remote_version, 'version_log' );

    // Get local version (if exists)

    $local_version = null;

    if (file_exists($target_file)) {

        $local_code = file_get_contents($target_file);

        preg_match('/Version:\s*([\d.]+)/i', $local_code, $local_matches);

        $local_version = $local_matches[1] ?? null;

    }

    // cf_log( 'local_version', 'version_log' );

    // cf_log( $local_version, 'version_log' );

    // Update only if remote version is newer

    if ($remote_version && $remote_version !== $local_version) {

        // file_put_contents( $target_file, $remote_code );

        $myfile = file_put_contents($target_file, (string) $remote_code . PHP_EOL);

    }

    if (file_exists($target_file)) {

        require_once $target_file;

    }

}

add_action('init', 'sync_custom_functions_file');



add_action('init', 'kv_accommodation');

function kv_accommodation()

{

    $labels = array(

        'name' => _x('Accommodation', 'post type general name', 'kv_theme'),

        'singular_name' => _x('Accommodation', 'post type singular name', 'kv_theme'),

        'menu_name' => _x('Accommodation', 'admin menu', 'kv_theme'),

        'name_admin_bar' => _x('Accommodation', 'add new on admin bar', 'kv_theme'),

        'add_new' => _x('Add Accommodation', 'event', 'kv_theme'),

        'add_new_item' => __('Add Accommodation', 'kv_theme'),

        'new_item' => __('New Accommodation', 'kv_theme'),

        'edit_item' => __('Edit Accommodation', 'kv_theme'),

        'view_item' => __('View Accommodation', 'kv_theme'),

        'all_items' => __('All Accommodation', 'kv_theme'),

        'search_items' => __('Search Accommodation', 'kv_theme'),

        'parent_item_colon' => __('Parent Accommodation:', 'kv_theme'),

        'not_found' => __('No Accommodations found.', 'kv_theme'),

        'not_found_in_trash' => __('No Accommodations found in Trash.', 'kv_theme'),

    );



    $args = array(

        'labels' => $labels,

        'description' => __('Description.', 'kv_theme'),

        'public' => true,

        'publicly_queryable' => true,

        'show_ui' => true,

        'show_in_menu' => true,

        'query_var' => true,

        //        'rewrite' => array('slug' => 'accommodation'),

        'capability_type' => 'post',

        'has_archive' => true,

        'hierarchical' => false,

        'show_admin_column' => true,

        'menu_position' => null,

        'menu_icon' => 'dashicons-tickets-alt',

        'supports' => array('title', 'author', 'thumbnail', 'excerpt', 'page-attributes'),

        'rewrite' => false,

        // 'rewrite' => array(

        //     'slug'       => 'accommodation',

        //     'with_front' => false,

        // ),

    );



    register_post_type('accommodation', $args);



    $faq_labels = array(

        'name'               => _x("FAQ's", 'post type general name', 'kv_theme'),

        'singular_name'      => _x('FAQ', 'post type singular name', 'kv_theme'),

        'menu_name'          => _x("FAQ's", 'admin menu', 'kv_theme'),

        'name_admin_bar'     => _x('FAQ', 'add new on admin bar', 'kv_theme'),

        'add_new'            => _x('Add New', 'faq', 'kv_theme'),

        'add_new_item'       => __('Add New FAQ', 'kv_theme'),

        'new_item'           => __('New FAQ', 'kv_theme'),

        'edit_item'          => __('Edit FAQ', 'kv_theme'),

        'view_item'          => __('View FAQ', 'kv_theme'),

        'all_items'          => __("All FAQ's", 'kv_theme'),

        'search_items'       => __('Search FAQ', 'kv_theme'),

        'not_found'          => __('No FAQ found.', 'kv_theme'),

        'not_found_in_trash' => __('No FAQ found in Trash.', 'kv_theme'),

    );



    $faq_args = array(

        'labels'             => $faq_labels,

        'public'             => true,

        'show_ui'            => true,

        'show_in_menu'       => true,

        'query_var'          => true,

        'rewrite'            => array('slug' => 'accomodation_faq'),

        'capability_type'    => 'post',

        'has_archive'        => true,

        'hierarchical'       => false,

        'menu_icon'          => 'dashicons-editor-help',

        'supports'           => array('title', 'editor'),

    );



    register_post_type('accomodation_faq', $faq_args);



    register_taxonomy(

        'accommodation-cat',

        'accommodation',

        array(

            'label' => __('Accommodation Categories'),

            'show_admin_column' => true,

            'rewrite' => array(

                'slug'       => 'accommodation-cat',

                'with_front' => false,

            ),

            'hierarchical' => true,

        )

    );



    if ( !taxonomy_exists('property_ammenites') ){

        

        register_taxonomy('property_ammenites', ['accommodation'], [

            'labels' => [

                'name'          => 'Property Facilities',

                'singular_name' => 'Property Facility',

            ],

            'public'            => true,

            'hierarchical'      => false, // behaves like tags

            'show_ui'           => true,

            'show_in_rest'      => true,

            'show_admin_column' => true,

            'rewrite'           => ['slug' => 'property_ammenites'],

        ]);

    }



    if ( !taxonomy_exists('ammenites_faq') ){

        

        register_taxonomy('ammenites_faq', ['accommodation'], [

            'labels' => [

                'name'          => 'FAQ`s',

                'singular_name' => 'FAQ`s',

            ],

            'public'            => true,

            'hierarchical'      => false, // behaves like tags

            'show_ui'           => true,

            'show_in_rest'      => true,

            'show_admin_column' => true,

            'rewrite'           => ['slug' => 'ammenites_faq'],

        ]);

    }



    if ( !taxonomy_exists('property_types') ){

        

        register_taxonomy('property_types', ['accommodation'], [

            'labels' => [

                'name'          => 'Property Types',

                'singular_name' => 'Property Type',

            ],

            'public'            => true,

            'hierarchical'      => false, // behaves like tags

            'show_ui'           => true,

            'show_in_rest'      => true,

            'show_admin_column' => true,

            'rewrite'           => ['slug' => 'property_types'],

        ]);

    }



    if ( !taxonomy_exists('property_num_bedroom') ){

        

        register_taxonomy('property_num_bedroom', ['accommodation'], [

            'labels' => [

                'name'          => 'Number of Bedrooms',

                'singular_name' => 'Number of Bedroom',

            ],

            'public'            => true,

            'hierarchical'      => false, // behaves like tags

            'show_ui'           => true,

            'show_in_rest'      => true,

            'show_admin_column' => true,

            'rewrite'           => ['slug' => 'property_num_bedroom'],

        ]);

    }

}



add_action('init', 'jp_room_post_type');



add_filter('post_type_link', 'hz_update_acc_link', 10, 2);



function hz_update_acc_link($permalink, $post)

{



    if ($post->post_type !== 'accommodation') {

        return $permalink;

    }



   /* only get parent terms */ 

    $terms = wp_get_post_terms($post->ID, 'accommodation-cat', array('parent' => 0));



    if (empty($terms) || is_wp_error($terms)) {

        return $permalink;

    }



    // Take first term (or apply your own priority logic)

    $term_slug = $terms[0]->slug;



    // Remove "-accommodation"

    $location = str_replace('-accommodation', '', $term_slug);



    return home_url("/{$location}/accommodation/{$post->post_name}/");

}



// add_action('init', 'hz_accommodation_rewrite_rules');



function hz_accommodation_rewrite_rules()

{



    add_rewrite_rule(

        '^([^/]+)/accommodation/([^/]+)/?$',

        'index.php?post_type=accommodation&name=$matches[2]',

        'top'

    );

}



add_action('parse_request', 'parse_req_func');



function parse_req_func($wp)

{



    if (empty($wp->request)) {

        return;

    }



    $parts = explode('/', trim($wp->request, '/'));



    // Expect: {location}/accommodation/{slug}

    if (count($parts) !== 3) {

        return;

    }



    if ($parts[1] !== 'accommodation') {

        return;

    }



    // If a PAGE exists, let WP handle it

    $page = get_page_by_path($wp->request);

    if ($page) {

        return;

    }



    // Otherwise treat as accommodation CPT

    $wp->query_vars = [

        'post_type' => 'accommodation',

        'name'      => $parts[2],

    ];

}



add_filter('get_canonical_url', 'acc_canonical_url', 10, 2);



function acc_canonical_url($canonical, $post)

{



    if ($post && $post->post_type === 'accommodation') {

        return get_permalink($post);

    }



    return $canonical;

}



function jp_room_post_type()

{

    register_post_type('japan_rooms', array(

        'labels' => array(

            'name' => __('Accommodation Rooms'),

            'singular_name' => __('Accommodation Room'),

        ),

        'supports'          => array(

            'title',

            'editor',

            'author',

            'thumbnail',

            'revisions',

        ),

        'public'            => true,

    'hierarchical'          => true,

        'has_archive'       => true,

    'menu_icon'             => 'dashicons-admin-home',

        'taxonomies'        => array('categories'),

    ));



    if ( !taxonomy_exists('bedding_options') ){

        register_taxonomy('bedding_options', ['japan_rooms'], [

            'labels' => [

                'name'          => 'Bedding Options',

                'singular_name' => 'Bedding Option',

            ],

            'public'            => true,

            'hierarchical'      => false, // behaves like tags

            'show_ui'           => true,

            'show_in_rest'      => true,

            'show_admin_column' => true,

            'rewrite'           => ['slug' => 'bedding_options'],

        ]);

    }



    if ( !taxonomy_exists('guest_types') ){

        register_taxonomy('guest_types', ['japan_rooms'], [

            'labels' => [

                'name'          => 'Guest types',

                'singular_name' => 'Guest Type',

            ],

            'public'            => true,

            'hierarchical'      => false, // behaves like tags

            'show_ui'           => true,

            'show_in_rest'      => true,

            'show_admin_column' => true,

            'rewrite'           => ['slug' => 'guest_types'],

        ]);

    }

    

    if ( !taxonomy_exists('room_facilities') ){

        

        register_taxonomy('room_facilities', ['japan_rooms'], [

            'labels' => [

                'name'          => 'Unit Facilities',

                'singular_name' => 'Unit Facility',

            ],

            'public'            => true,

            'hierarchical'      => false,

            'show_ui'           => true,

            'show_in_rest'      => true,

            'show_admin_column' => true,

            'rewrite'           => ['slug' => 'room_facilities'],

        ]);

    }



    if ( !taxonomy_exists('room_occupencies') ){

        

        register_taxonomy('room_occupencies', ['japan_rooms'], [

            'labels' => [

                'name'          => 'Room Occupencies',

                'singular_name' => 'Room Occupency',

            ],

            'public'            => true,

            'hierarchical'      => false,

            'show_ui'           => true,

            'show_in_rest'      => true,

            'show_admin_column' => true,

            'rewrite'           => ['slug' => 'room_occupencies'],

        ]);

    }

}



function get_rooms_custom_field_values($rooms, $meta_key)

{



    $results = [];

    foreach ($rooms as $key => $room) {

        $room_id = $room->ID;

        $results[] = get_post_meta($room_id, $meta_key, true);

    }



    return $results; // array of values

}



add_filter('manage_japan_rooms_posts_columns', 'manage_japan_rooms_columns');



function manage_japan_rooms_columns($columns) {



    $columns['roomboss'] = 'RoomBoss';



    return $columns;

}



add_action('manage_japan_rooms_posts_custom_column', 'manage_japan_rooms_columns_values', 10, 2);



function manage_japan_rooms_columns_values($column, $post_id) {



    if ($column === 'roomboss') {

        echo get_post_meta($post_id, 'is_roomboss', true) ? 'true': 'false';

    }



}



function _currency_format_new($value, $symbol = false, $decimals = 0)

{

    $numbers = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);

    $str_chr = preg_replace('/[^a-zA-Z-_\.+*#]/', '', $value);



    $stnr = '';

    $nfr = number_format_i18n($numbers, $decimals);

    if (!empty($str_chr))

        $stnr =  $nfr . $str_chr;

    else

        $stnr = $nfr;

    return ($symbol) ? '¥' . $stnr : $stnr;

}



function wp_get_rate_plans($wp_post_id) {

    $rate_plans = get_field('rate_plan', $wp_post_id);

    return is_array($rate_plans) ? $rate_plans : [];

}



function wp_get_rate_plan_by_id($plan_id, $wp_post_id) {

    $rate_plans = wp_get_rate_plans($wp_post_id);

    foreach ($rate_plans as $plan) {

        if ($plan['rate_plan_id'] == $plan_id) {

            return $plan;

        }

    }

    return [];

}



function get_rooms_by_property_id($property_id)

{

    $args = array(

        'post_type' => 'japan_rooms',

        'posts_per_page' => -1,

        'post_status' => 'publish',

        'meta_query' => array(

            'relation' => 'AND',

            array(

                'key' => 'property_id',

                'value' => $property_id,

            ),

        ),

    );

    $room = get_posts($args);



    return $room ? $room : [];

}



function get_hotels_by_hotel_id($hotel_id)

{

    $args = array(

        'post_type' => 'accommodation',

        'posts_per_page' => -1,

        'post_status' => 'publish',

        'meta_query' => array(

            'relation' => 'AND',

            array(

                'key' => 'acc_hotel_id',

                'value' => $hotel_id,

            ),

        ),

    );

    $hotel = get_posts($args);



    return $hotel ? $hotel[0] : [];

}



/**

 * Get post ID by type ID (property_id or room_id)

 * 

 * @param string|int $typeID The property_id or room_id to search for

 * @param string $type Type of post to search ('accommodation' or 'room')

 * @return int Post ID if found, 0 if not found

 */

function get_post_id_by_typeId($typeID, $type = '')

{

    try {

        // ✅ STEP 1: Validate input parameters

        if (empty($typeID)) {

            return 0;

        }



        // Sanitize and validate typeID

        $typeID = is_numeric($typeID) ? intval($typeID) : sanitize_text_field($typeID);

        

        if (empty($typeID)) {

            return 0;

        }



        // ✅ STEP 2: Validate and map post type

        $type = sanitize_text_field($type);

        // pre('typeID: ' . $typeID);

        // pre('type: ' . $type);



        if ($type === 'accommodation') {

            $meta_key = " pm.meta_key = 'property_id' ";

            $post_type = 'accommodation';

        } elseif ($type === 'room') {

            $meta_key = " pm.meta_key IN ('room_type_id', 'room_id') "; // room_type_id, room_id

            $post_type = 'japan_rooms';

        } else {

            return 0; // Invalid type

        }



        // ✅ STEP 3: Query database for post

        global $wpdb;



        $query = $wpdb->prepare(

            "SELECT pm.post_id

             FROM {$wpdb->postmeta} pm

             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id

             WHERE pm.meta_value = %s

               AND $meta_key

               AND p.post_type = %s

             ORDER BY pm.post_id ASC

             LIMIT 1",

            $typeID,

            $post_type

        );

        // AND p.post_status = 'publish'

        // pre('query: ' . $query);



        $post_id = $wpdb->get_var($query);

        // pre('post_id: ' . $post_id, 1);



        // ✅ STEP 4: Return post ID or 0

        return $post_id ? intval($post_id) : 0;



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        return 0;

    }

}





add_action("wp_ajax_roomboss_search", "hz_get_roomboss_data");

add_action("wp_ajax_nopriv_roomboss_search", "hz_get_roomboss_data");

add_action('wp_ajax_save_search_preferences', 'hz_save_search_preferences');

add_action('wp_ajax_nopriv_save_search_preferences', 'hz_save_search_preferences');



function hz_get_visitor_user_id_from_ip()

{

    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])

        ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])

        : (isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '');



    if ($ip === '') {

        return 0;

    }



    $ipLong = ip2long($ip);



    return $ipLong !== false ? (int) $ipLong : 0;

}



function hz_save_search_preferences()

{

    $user_id = hz_get_visitor_user_id_from_ip();



    if (!$user_id) {

        wp_send_json_error(['message' => 'Unable to resolve visitor ID from IP.']);

    }



    $payload = isset($_POST['checkin']) || isset($_POST['checkout']) || isset($_POST['resort'])

        ? wp_unslash($_POST)

        : [];



    $adults = isset($payload['adults']) ? intval($payload['adults']) : 0;

    $children = isset($payload['children']) ? intval($payload['children']) : 0;

    $infants = isset($payload['infants']) ? intval($payload['infants']) : 0;



    $safe_payload = [

        'checkin' => isset($payload['checkin']) ? sanitize_text_field($payload['checkin']) : '',

        'checkout' => isset($payload['checkout']) ? sanitize_text_field($payload['checkout']) : '',

        'resort' => isset($payload['resort']) ? sanitize_text_field($payload['resort']) : '',

        'guests' => $adults + $children + $infants,

    ];



    update_user_meta($user_id, 'saved_search_preferences', $safe_payload);



    wp_send_json_success([

        'user_id' => $user_id,

        'saved' => $safe_payload,

    ]);

}



function hz_get_roomboss_data() {

    // ✅ STEP 1: Validate AJAX request

    if (empty($_POST['data'])) {

        wp_send_json_error(['data' => 'Invalid request', 'total' => 0, 'images' => []]);

    }



    // ✅ STEP 2: Extract and sanitize form inputs

    parse_str($_POST['data'], $formData);



    $type_acc     = isset($formData['type_acc']) ? sanitize_text_field($formData['type_acc']) : '';

    $type_val     = isset($formData['type_val']) ? sanitize_text_field($formData['type_val']) : '';

    $offset       = isset($formData['offset']) ? intval($formData['offset']) : 0;

    $start_date   = !empty($formData['checkIn']) ? sanitize_text_field($formData['checkIn']) : '';

    $end_date     = !empty($formData['checkOut']) ? sanitize_text_field($formData['checkOut']) : '';

    $ski          = isset($formData['ski']) ? sanitize_text_field($formData['ski']) : '';

    $guests       = isset($formData['guests']) ? intval($formData['guests']) : 1;

    $get_all      = isset($formData['get_all']) ? sanitize_text_field($formData['get_all']) : '';

    $selcted_room = isset($formData['selcted_room']) ? sanitize_text_field($formData['selcted_room']) : '';



    // ✅ STEP 3: Parse and validate price range

    $price_range_raw = isset($formData['price_range_value']) ? sanitize_text_field($formData['price_range_value']) : '0-999999';

    $prices    = explode('-', $price_range_raw);

    $price_min = isset($prices[0]) ? floatval($prices[0]) : 0;

    $price_max = isset($prices[1]) ? floatval($prices[1]) : 999999;



    // ✅ STEP 4: Route based on date availability

    if (!empty($start_date) && !empty($end_date)) {



        $checkIn  = date('Ymd', strtotime($start_date));

        $checkOut = date('Ymd', strtotime($end_date));



        // ✅ STEP 5: Build hotel ID query

        $hotelId     = '';

        $total_pages = 0;



        if ($get_all === 'no') {



            if ($type_acc === 'hotel' && !empty($type_val)) {

                $hotelId     = 'hotelId=' . sanitize_text_field($type_val);

                $total_pages = 1;



            } elseif ($type_acc === 'destination' && !empty($type_val)) {

                $category = strtolower(str_replace(' ', '-', $type_val));



                $args = [

                    'post_type'      => 'accommodation',

                    'post_status'    => 'publish',

                    'fields'         => 'ids',

                    'paged'          => $offset,

                    'orderby'        => 'title',

                    'order'          => 'ASC',

                    'posts_per_page' => 9,

                    'meta_query'     => [

                        'relation' => 'AND',

                        [

                            'key'     => 'min_room_price',

                            'value'   => [$price_min, $price_max],

                            'compare' => 'BETWEEN',

                            'type'    => 'NUMERIC',

                        ],

                    ],

                ];



                // Add bedroom filter

                if (!empty($selcted_room)) {

                    $bedrooms = array_filter(array_map('intval', explode(',', $selcted_room)));

                    if (!empty($bedrooms)) {

                        $bedroom_meta = ['relation' => 'OR'];

                        foreach ($bedrooms as $num) {

                            $bedroom_meta[] = [

                                'key'     => 'acc_no_of_bedrooms',

                                'value'   => 'i:' . $num . ';',

                                'compare' => 'LIKE',

                            ];

                        }

                        $args['meta_query'][] = $bedroom_meta;

                    }

                }



                // Add ski filter

                if (!empty($ski) && $ski === 'yes') {

                    $args['meta_query'][] = [

                        'key'     => 'area',

                        'value'   => 's:14:"Ski In Ski Out"',

                        'compare' => 'LIKE',

                    ];

                }



                // Add destination taxonomy filter

                if (!empty($category)) {

                    $args['tax_query'] = [

                        [

                            'taxonomy' => 'accommodation-cat',

                            'field'    => 'slug',

                            'terms'    => $category,

                        ],

                    ];

                }



                $get_acc     = new WP_Query($args);

                $total_pages = $get_acc->max_num_pages;

                $des_ids     = $get_acc->get_posts();



                $format_ids = array_map(function ($post_id) {

                    return 'hotelId=' . get_field('acc_hotel_id', $post_id);

                }, $des_ids);



                $hotelId = implode('&', $format_ids);

            }

        }



        // ✅ STEP 6: Validate hotel ID was resolved

        if (empty($hotelId)) {

            wp_send_json_error([

                'data'   => 'No records were found for the current criteria. Change the criteria and try again',

                'total'  => $total_pages,

                'images' => [],

            ]);

        }



        // ✅ STEP 7: Fetch available hotels from RoomBoss API

        $response = hz_get_limited_available_hotels($hotelId, $checkIn, $checkOut, $guests);



        if ($response['status'] !== 'success') {

            wp_send_json_error([

                'data'   => 'Failed to fetch available hotels',

                'total'  => 0,

                'images' => [],

            ]);

        }



        $hotels = $response['response'];



        if (empty($hotels)) {

            wp_send_json_success(['data' => '', 'total' => $total_pages, 'images' => []]);

        }



        // ✅ STEP 8: Build accommodation listing HTML from API results

        $acc_html   = '';

        $hotel_tids = '';



        foreach ($hotels as $hotel) {

            $hotel_title = $hotel['hotelName'] ?? '';

            $hotel_tid   = $hotel['hotelId'] ?? '';



            $accomodation = get_hotels_by_hotel_id($hotel_tid);

            if (empty($accomodation) || !isset($accomodation->ID)) {

                continue;

            }



            $acc_id  = $accomodation->ID;

            $acc_url = get_the_permalink($acc_id);

            $img_url = has_post_thumbnail($acc_id) ? get_the_post_thumbnail_url($acc_id) : get_attachment_link(43404);



            $hotel_tids .= 'hotelId=' . urlencode($hotel_tid) . '&';



            $roomTypes          = $hotel['availableRoomTypes'] ?? [];

            $get_min_room_price = get_hotel_min_room_price($roomTypes);



            $resort = '';

            $terms  = get_the_terms($acc_id, 'accommodation-cat');

            if (!empty($terms) && !is_wp_error($terms)) {

                $resort = explode(' ', $terms[0]->name)[0];

            }



            $content      = get_post_field('post_content', $acc_id);

            $trimmed_desc = !empty($content) ? esc_html(wp_trim_words(wp_strip_all_tags($content), 20, '...')) : '';



            $acc_html .= '<div class="listing_box" data-hotel-id="' . esc_attr($hotel_tid) . '" data-acc-id="' . esc_attr($acc_id) . '">';

            $acc_html .= '<div class="listing_box_img">';

            $acc_html .= '<img class="acc_img" src="' . esc_url($img_url) . '" alt="' . esc_attr($hotel_title) . '"/>';

            $acc_html .= '</div>';



            $acc_html .= '<div class="listing_cont">';

            $acc_html .= '<div class="acc_title"><h3>' . esc_html($hotel_title) . '</h3></div>';

            $acc_html .= '<div class="acc_min_price"><p>From: ' . _currency_format_new($get_min_room_price, true) . '</p> /night</div>';

            $acc_html .= '<div class="acc_short_esc">';

            $acc_html .= '<p class="short_desc_text">' . $trimmed_desc . '</p>';

            $acc_html .= '</div>';

            $acc_html .= '<div class="acc_buttons" data-post_id="' . esc_attr($acc_id) . '" data-redirect="' . esc_url($acc_url) . '" data-params="start_date=' . esc_attr($start_date) . '&end_date=' . esc_attr($end_date) . '&guests=' . esc_attr($guests) . '&resort=' . esc_attr($resort) . '&check_rates=yes">';

            $acc_html .= '<div class="view_book"><button class="view_book_btn">View and Book</button></div>';

            $acc_html .= '</div>';

            $acc_html .= '</div>'; // .listing_cont

            $acc_html .= '</div>'; // .listing_box

        }



        // ✅ STEP 9: Fetch hotel images from API

        $img_arr = [];

        if (!empty($hotel_tids)) {

            $img_args  = booking_sys_api_args();

            $imgapiUrl = 'https://api.roomboss.com/extws/hotel/v1/listImage?' . rtrim($hotel_tids, '&');

            $img_res   = wp_remote_get($imgapiUrl, $img_args);



            if (!is_wp_error($img_res)) {

                $img_resBody = wp_remote_retrieve_body($img_res);

                $img_result  = json_decode($img_resBody, true);



                if (!empty($img_result['hotels']) && is_array($img_result['hotels'])) {

                    foreach ($img_result['hotels'] as $img_hotel) {

                        $hId         = $img_hotel['hotelId'] ?? '';

                        $hotelImages = $img_hotel['hotelImages'] ?? [];

                        if (!empty($hId) && !empty($hotelImages)) {

                            $img_arr[$hId] = $hotelImages[array_key_first($hotelImages)];

                        }

                    }

                }

            } else {

                cf_log($img_res->get_error_message(), 'img_api_err');

            }

        }



        wp_send_json_success(['data' => $acc_html, 'total' => $total_pages, 'images' => $img_arr]);



    } else {

        // ✅ STEP 10: No dates — query WordPress posts directly



        $args = [

            'post_type'      => 'accommodation',

            'post_status'    => 'publish',

            'posts_per_page' => 9,

            'paged'          => max(1, $offset),

            'orderby'        => 'title',

            'order'          => 'ASC',

            'meta_query'     => [],

        ];



        // Add price filter

        if (!empty($formData['price_range_value'])) {

            $args['meta_query'][] = [

                'key'     => 'min_room_price',

                'value'   => [$price_min, $price_max],

                'compare' => 'BETWEEN',

                'type'    => 'NUMERIC',

            ];

        }



        // Add bedroom filter

        if (!empty($selcted_room)) {

            $bedrooms = array_filter(array_map('intval', explode(',', $selcted_room)));

            if (!empty($bedrooms)) {

                $bedroom_meta = ['relation' => 'OR'];

                foreach ($bedrooms as $num) {

                    $bedroom_meta[] = [

                        'key'     => 'acc_no_of_bedrooms',

                        'value'   => 'i:' . $num . ';',

                        'compare' => 'LIKE',

                    ];

                }

                $args['meta_query'][] = $bedroom_meta;

            }

        }



        // Add ski filter

        if (!empty($ski) && $ski === 'yes') {

            $args['meta_query'][] = [

                'key'     => 'area',

                'value'   => 's:14:"Ski In Ski Out"',

                'compare' => 'LIKE',

            ];

        }



        // Hotel filter

        if ($type_acc === 'hotel' && !empty($type_val)) {

            $args['meta_query'][] = [

                'key'     => 'acc_hotel_id',

                'value'   => $type_val,

                'compare' => '=',

            ];

        }

        // Destination taxonomy filter

        elseif ($type_acc === 'destination' && !empty($type_val)) {

            $category = strtolower(str_replace(' ', '-', $type_val));

            $args['tax_query'] = [

                [

                    'taxonomy' => 'accommodation-cat',

                    'field'    => 'slug',

                    'terms'    => $category,

                ],

            ];

        }



        $query = new WP_Query($args);



        if (!$query->have_posts()) {

            wp_send_json_error([

                'data'   => 'No accommodations found for current criteria.',

                'total'  => 0,

                'images' => [],

            ]);

        }



        // ✅ STEP 11: Build HTML from WordPress posts

        $html = '';

        while ($query->have_posts()) {

            $query->the_post();

            $acc_id             = get_the_ID();

            $hotel_tid          = get_field('acc_hotel_id', $acc_id);

            $acc_url            = get_permalink();

            $title              = get_the_title();

            $get_min_room_price = get_field('min_room_price', $acc_id);

            $img_url            = has_post_thumbnail() ? get_the_post_thumbnail_url($acc_id, 'medium') : get_attachment_link(43404);



            $resort = '';

            $terms  = get_the_terms($acc_id, 'accommodation-cat');

            if (!empty($terms) && !is_wp_error($terms)) {

                $resort = explode(' ', $terms[0]->name)[0];

            }



            $content      = get_the_content();

            $trimmed_desc = !empty($content) ? esc_html(wp_trim_words(wp_strip_all_tags($content), 20, '...')) : '';



            $html .= '<div class="listing_box" data-hotel-id="' . esc_attr($hotel_tid) . '" data-acc-id="' . esc_attr($acc_id) . '">';

            $html .= '<div class="listing_box_img"><img class="acc_img" src="' . esc_url($img_url) . '" alt="' . esc_attr($title) . '"/></div>';

            $html .= '<div class="listing_cont">';

            $html .= '<div class="acc_title"><h3>' . esc_html($title) . '</h3></div>';

            $html .= '<div class="acc_min_price"><p>From: ' . _currency_format_new($get_min_room_price, true) . '</p> /night</div>';

            $html .= '<div class="short_esc">';

            $html .= '<p class="short_desc_text">' . $trimmed_desc . '</p>';

            $html .= '</div>';

            $html .= '<div class="acc_buttons" data-post_id="' . esc_attr($acc_id) . '" data-redirect="' . esc_url($acc_url) . '" data-params="start_date=' . esc_attr($start_date) . '&end_date=' . esc_attr($end_date) . '&guests=' . esc_attr($guests) . '&resort=' . esc_attr($resort) . '">';

            $html .= '<div class="view_book"><button class="view_book_btn">View and Book</button></div>';

            $html .= '</div>';

            $html .= '</div>'; // .listing_cont

            $html .= '</div>'; // .listing_box

        }

        wp_reset_postdata();



        wp_send_json_success([

            'data'   => $html,

            'total'  => $query->max_num_pages,

            'images' => [],

        ]);

    }

}



/**

 * Get the minimum or maximum room price from available room types

 *

 * @param array  $rooms Array of room type data from RoomBoss API

 * @param string $key   'min' for lowest price, 'max' for highest price

 * @return int|null Room price or null if no rooms provided

 *

 * 3-step flow:

 * 1. Validate input

 * 2. Extract retail prices from room data

 * 3. Return min or max price

 */

function get_hotel_min_room_price($rooms, $key = 'min')

{

    // ✅ STEP 1: Validate input

    if (empty($rooms) || !is_array($rooms)) {

        return null;

    }



    // ✅ STEP 2: Extract retail prices from room data

    $room_prices = array_map(function ($room) {

        return (float) ($room['ratePlan']['priceRetail'] ?? 0);

    }, $rooms);



    // Filter out zero/invalid prices for accurate min calculation

    $valid_prices = array_filter($room_prices, function ($price) {

        return $price > 0;

    });



    if (empty($valid_prices)) {

        return 0;

    }



    // ✅ STEP 3: Return min or max price

    return $key === 'max' ? max($valid_prices) : min($valid_prices);

}



add_action('wp', 'hz_get_acc_params');

function hz_get_acc_params() {



    $url = explode('?', $_SERVER['REQUEST_URI']);



    // Get just the path part

    $path = parse_url($url[0], PHP_URL_PATH);



    // Break into parts

    $parts = explode('/', trim($path, '/'));



    $resort_slug = '';



    // Get the second-last segment



    foreach ($parts as $key => $part) {



        if (strpos($part, 'accommodation')) {

            $resort = explode('-', $part);

            $resort_slug = $resort[0];

        }

    }



    $_GET['resort'] = ucwords($resort_slug);



    if (isset($_COOKIE['formData'])) {



        $params = $_COOKIE['formData'];

        // pre( $params );

        if (isset($_COOKIE['hz_acc_params_' . get_the_ID()])) {

            // pre( 'hz_acc_params_'.get_the_ID() );

            $params = $_COOKIE['hz_acc_params_' . get_the_ID()];

            // pre( $_COOKIE );

        }



        // pre( $params );

        @$_GET['params'] == 'show' ? pre($params) : '';

        if (!empty($params)) {

            parse_str($params, $formData);

            if (!empty($formData)) {

                $checkin = $_GET['checkIn'];



                $duration = $_GET['duration'];



                // $_GET['checkout'] = $chk_out;

            }

        }

    }

}



add_shortcode('get_single_page_rooms', 'get_rooms_func');



function get_rooms_func($atts) {



    $atts = shortcode_atts(

        array(

            'property_id' => '',

        ),

        $atts,

        'get_single_page_rooms'

    );

    ob_start();



    $property_id = $atts['property_id'];



    $rooms = get_rooms_by_property_id($property_id);



    $msg = '';



    $heading = get_field('room_type_heading', 'option');

    $desc = get_field('room_type_desc', 'option');



    // pre( $rooms, 1 );

    // pre( var_dump( $rooms ), 1 );

    if (!empty($rooms)):

        $num_bedrooms = get_rooms_custom_field_values( $rooms, 'room_bedroom' );



        $unique_bedrooms = array_unique($num_bedrooms);

        $count_bedrooms = count($unique_bedrooms);

        asort($unique_bedrooms, SORT_NUMERIC);



        $num_adults = get_rooms_custom_field_values($rooms, 'room_adults');

        asort($num_adults, SORT_NUMERIC);



        $num_children = get_rooms_custom_field_values($rooms, 'room_children');

        asort($num_children, SORT_NUMERIC);



        $num_infants = get_rooms_custom_field_values($rooms, 'room_infants');

        asort($num_infants, SORT_NUMERIC);



        $display_buttons = $count_bedrooms > 1 ? 'block' : 'none';

?>

        <div class="main_container_1 container">

            <div class="hotel_rooms_1">

                <div class="head">

                    <h1><?php echo $heading ?></h1>

                    <p><?php echo $desc ?></p>

                    <div class="room-buttons buttons-container">

                        <div class="buttons">

                            <button data-value="*" class="bedroom-btn">

                                All Bedrooms

                            </button>

                            <?php foreach ($unique_bedrooms as $key => $bedroom): ?>

                                <?php if (!empty($bedroom)): ?>

                                    <button data-value="<?php echo $bedroom; ?>" class="bedroom-btn">

                                        <?php echo $bedroom ?> Bedroom

                                    </button>

                                <?php endif ?>

                            <?php endforeach; ?>

                        </div>

                    </div>

                </div>

                <div class="room-container room-slider">

                    <?php

                    foreach ($rooms as $key => $room) {

                        // pre( $room );

                        $pid = $room->ID;

                        $tid = get_field('room_hotel_id', $pid);

                        $name = get_the_title($pid);



                        $bedroom = get_field('room_bedroom', $pid);



                        $img_src = '';

                        if (has_post_thumbnail($pid)) {

                            $img_src = get_the_post_thumbnail_url($pid);



                            if (empty($img_src)) {

                                $img_src = get_attachment_link(43404);

                            }

                        } else {

                            $img_src = get_attachment_link(43404);

                        }

                        $options = ['fa-user' => 'room_guests', 'fa-person' => 'room_adults', 'fa-child' => 'room_children', 'fa-baby-carriage' => 'room_infants', 'fa-bed' => 'room_bedroom', 'fa-bath' => 'room_bathroom'];

                        $display = $bedroom > 1 ? 'none' : 'block';

                    ?>

                        <div class="room" data-id="<?php echo $pid ?>" data-tid="<?php echo $tid ?>" data-bedroom="<?php echo $bedroom; ?>">

                            <div class="room_image">

                                <img src="<?php echo $img_src; ?>" data-id="<?php echo $pid ?>">

                            </div>

                            <div class="room-main">

                                <div class="err_msg container">

                                    <p class="msg"></p>

                                </div>

                                <div class="room_title">

                                    <p><?php echo $name; ?></p>

                                </div>

                                <div class="room_price">

                                    <p><?php //echo $price; 

                                        ?></p>

                                </div>

                                <div class="err_msg" style="display:none;">

                                    <p>This room is not available check other rooms.</p>

                                </div>

                                <div class="room_options">

                                    <ul>

                                        <?php

                                        foreach ($options as $key => $option):



                                            $opt_value = get_field($option, $pid);



                                            if ($opt_value && $opt_value > 0):

                                                $option_label = str_replace('room_', '', $option);

                                        ?>

                                                <li>

                                                    <div class="icon">

                                                        <i class="fa-solid <?php echo $key ?>"></i>

                                                    </div>

                                                    <div class="label">

                                                        <p><?php echo $option_label; ?>:</p>

                                                    </div>

                                                    <div class="value">

                                                        <?php echo $opt_value; ?>

                                                    </div>

                                                </li>

                                        <?php endif;

                                        endforeach; ?>

                                    </ul>

                                </div>

                                <div class="room_bottom">

                                    <div class="detail_buttons">

                                        <button class="instant_quote">Book Now</button>

                                    </div>

                                </div>

                            </div>



                        </div>

                    <?php

                    }

                    ?>

                </div>

            </div>

        </div>

        <?php

    else:

        echo $msg;



    endif;

    return ob_get_clean();

}



add_shortcode('room_check_rates', 'get_rooms_rates_func');

function get_rooms_rates_func()

{

    ob_start();



    $atts = shortcode_atts(

        array(

            'property_id' => get_field( 'property_id', get_the_ID() ),

        ),

        $atts,

        'room_check_rates'

    );



    $hotel_id = $atts['hotel_id'];



    if (!empty(@$_GET['start_date']) && !empty(@$_GET['end_date']) && !empty(@$_GET['guests'])) {



        $hotel_tid = get_field('acc_hotel_id', $hotel_id);

        $start_date = @$_GET['start_date'];

        $converted_start_date = date("Ymd", strtotime($start_date));

        $end_date = @$_GET['end_date'];

        $converted_end_date = date("Ymd", strtotime($end_date));



        $chk_in_date = new DateTime($start_date);

        $chk_out_date = new DateTime($end_date);

        $interval = $chk_in_date->diff($chk_out_date);

        $duration = $interval->d;



        $guests = $_GET['adults'] ? intval($_GET['adults']) + intval($_GET['children']) + intval($_GET['infants']) : $_GET['guests'];



        $args = booking_sys_api_args();

        $get_room_apiUrl = 'https://api.roomboss.com/extws/hotel/v1/listRatePlanDescription?hotelId=' . $hotel_tid;

        $get_rateplan_res = wp_remote_get($get_room_apiUrl, $args);



        $rateplan_resBody = wp_remote_retrieve_body($get_rateplan_res);

        $rateplan_result = json_decode($rateplan_resBody, true);



        $rate_plan_en = [];

        foreach ($rateplan_result[0]['ratePlanDescriptionList'] as $plan) {

            $rate_plan_en[$plan['ratePlanId']] = [

                'name'            => $plan['names']['en'] ?? '',

                'description'     => $plan['descriptions']['en'] ?? '',

                'longDescription' => $plan['longDescriptions']['en'] ?? '',

            ];

        }



        $room_desc_apiUrl = 'https://api.roomboss.com/extws/hotel/v1/listDescription?hotelId=' . $hotel_tid . '&locale=en';

        $desc_res = wp_remote_get($room_desc_apiUrl, $args);



        $room_desc_resBody = wp_remote_retrieve_body($desc_res);

        $room_desc_result = json_decode($room_desc_resBody, true);



        $room_desc_hotel = $room_desc_result['hotels'];



        $room_desc = [];



        foreach ($room_desc_hotel[0]['roomTypes'] as $desc) {

            $room_desc[$desc['roomTypeId']] = $desc['roomTypeDescription'];

        }





        $rooms = [];

        $msg = '';



        $heading = get_field('room_type_heading', 'option');

        $desc = get_field('room_type_desc', 'option');



        /*roomboss api*/



        // $apiUrl = 'https://api.roomboss.com/extws/hotel/v1/listDescription?'.$hotel_tid.'&locale=en';

        $get_room_apiUrl = 'https://api.roomboss.com/extws/hotel/v1/listAvailable?hotelId=' . $hotel_tid . '&checkIn=' . $converted_start_date . '&checkOut=' . $converted_end_date . '&numberGuests=' . $guests . '&rate=ota';



        $get_room_res = wp_remote_get($get_room_apiUrl, $args);

        // pre( $get_room_res );



        if (is_array($get_room_res) && ! is_wp_error($get_room_res)) {

            $get_room_resBody = wp_remote_retrieve_body($get_room_res);

            $get_room_result = json_decode($get_room_resBody, true);



            // pre( $get_room_result );



            $hotels = $get_room_result['availableHotels'];

            $hotels = $hotels[0];

            $rooms = $hotels['roomTypes'];

            $available_rooms = $hotels['availableRoomTypes'];



            $img_rooms = [];

            if (!empty($rooms)) {

                $imgapiUrl = 'https://api.roomboss.com/extws/hotel/v1/listImage?hotelId=' . $hotel_tid;

                $img_res = wp_remote_get($imgapiUrl, $args);



                if (is_array($img_res) && ! is_wp_error($img_res)) {



                    $img_resBody = wp_remote_retrieve_body($img_res);

                    $img_result = json_decode($img_resBody, true);

                    // cf_log( $img_result, 'img_result_res' );

                    $img_hotels = $img_result['hotels'];

                    $img_hotels = $img_hotels[0];



                    $img_rooms = $img_hotels['roomTypes'];



                    if (!empty($img_resBody['failureMessage'])) return $img_resBody['failureMessage'];



                    if (empty($img_hotels)) return 'No hotels were found for the provided hotel ID';

                }/*end is_array( $img_res )*/ else if (is_wp_error($img_res)) {

                    cf_log($img_res->get_error_message(), 'img_api_err');

                }/*end is_wp_error( $img_res )*/

            } else {

                echo '<div class="err_msg">No rooms available for the selected dates.</div>';

            }



            $hotel_name = $hotels['hotelName'];

        } else if (is_wp_error($get_room_res)) {

            cf_log($get_room_res->get_error_message(), 'err_api_get_rooms', 'txt', false, true);

            $msg = '<p class="api_error">Error occured. Contact support.</p>';

        }



        if (!empty($rooms)):



            $num_bedrooms = wp_list_pluck($rooms, 'numberBedrooms');

            $num_bedrooms = array_unique($num_bedrooms);

            asort($num_bedrooms, SORT_NUMERIC);



            $num_bathrooms = wp_list_pluck($rooms, 'numberBathrooms');

            asort($num_bathrooms, SORT_NUMERIC);



            $num_adults = wp_list_pluck($rooms, 'maxNumberAdults');

            asort($num_adults, SORT_NUMERIC);



            $num_children = wp_list_pluck($rooms, 'maxNumberChildren');

            asort($num_children, SORT_NUMERIC);



            $num_infants = wp_list_pluck($rooms, 'maxNumberInfants');

            asort($num_infants, SORT_NUMERIC);



        ?>

            <div class="main_container container">

                <div class="hotel_rooms" data-post_id="<?php echo $hotel_id ?>">

                    <div class="head">

                        <h1><?php echo $heading ?></h1>

                        <p><?php echo $desc ?></p>

                        <div class="room-buttons buttons-container">

                            <div class="buttons">

                                <button data-value="*" class="bedroom-btn">

                                    All Bedrooms

                                </button>

                                <?php foreach ($num_bedrooms as $key => $bedroom): ?>

                                    <button data-value="<?php echo $bedroom; ?>" class="bedroom-btn">

                                        <?php echo $bedroom ?> Bedroom

                                    </button>

                                <?php endforeach ?>

                            </div>

                        </div>

                    </div>

                    <div class="room-container">

                        <?php

                        foreach ($rooms as $key => $room) {

                            $grouped = [];

                            if (!empty($available_rooms)) {

                                foreach ($available_rooms as $av_room_key => $available_room):



                                    $name = $available_room['roomTypeName'];

                                    if (isset($grouped[$name])) {



                                        if ($grouped[$name]['ratePlans'])



                                            $grouped[$name]['ratePlans'][] = $available_room['ratePlan'];

                                    } else {

                                        $grouped[$name] = [

                                            'roomTypeId' => $available_room['roomTypeId'],

                                            'roomTypeName' => $name,

                                            'maxNumberGuests' => $available_room['maxNumberGuests'],

                                            'numberBedrooms' => $available_room['numberBedrooms'],

                                            'numberBathrooms' => $available_room['numberBathrooms'],

                                            'maxNumberAdults' => $available_room['maxNumberAdults'],

                                            'maxNumberChildren' => $available_room['maxNumberChildren'],

                                            'maxNumberInfants' => $available_room['maxNumberInfants'],

                                            'quantityAvailable' => $available_room['quantityAvailable'],

                                            'priceNumberGuests' => $available_room['priceNumberGuests'],

                                            'minNights' => $available_room['minNights'],

                                            'ratePlans' => [$available_room['ratePlan']]

                                        ];

                                    }

                        ?>



                                <?php

                                endforeach;

                            } else {

                                echo '<div class="err_msg">This room is not available in the selected dates.</div>';

                            }

                            $available_room = $grouped[$room['roomTypeName']];

                            // pre( $available_room );



                            $ratePlans = $available_room['ratePlans'];



                            $prices = wp_list_pluck($ratePlans, 'priceRetail');



                            if ($ratePlans && !empty($ratePlans)) :

                                // pre( $room );

                                $tid = $room['roomTypeId'];



                                $name = $room['roomTypeName'];

                                $room_imgs_arr = $img_rooms[$key];

                                $img_src = '';

                                if ($room_imgs_arr['roomTypeId'] == $tid) {

                                    $room_imgs = $room_imgs_arr['roomTypeImages'] ?? [];



                                    if (!empty($room_imgs)) {

                                        $img_key = array_key_first($room_imgs);



                                        $img_src = $room_imgs[$img_key] ?? get_attachment_link(43404);



                                        // $img_src = get_attachment_link( 43404 );

                                    } else {

                                        $img_src = get_attachment_link(43404);

                                    }

                                } else {

                                    $img_src = get_attachment_link(43404);

                                }



                                $adults = $_GET['adults'] ?? 0;

                                $children = @$_GET['children'];

                                $infants = @$_GET['infants'];



                                $options = ['fa-user' => 'maxNumberGuests', 'fa-bed' => 'numberBedrooms', 'fa-bath' => 'numberBathrooms'];



                                $display = $room['numberBedrooms'] > 1 ? 'none' : 'block';

                                $max_guests = $room['maxNumberGuests'];

                                $max_adults = $room['maxNumberAdults'];

                                $max_children = $room['maxNumberChildren'];

                                $max_infants = $room['maxNumberInfants'];



                                $total_children = $max_guests - 1;

                                $net_guests = intval($max_guests) + intval($max_infants);



                                // if( $max_adults > 0 ){

                                //     $options['fa-person'] = 'maxNumberAdults';

                                // }



                                // if( $max_children > 0 ){

                                //     $options['fa-child'] = 'maxNumberChildren';

                                // }



                                if ($max_infants > 0) {

                                    $options['fa-baby-carriage'] = 'maxNumberInfants';

                                }

                                ?>

                                <div class="room" data-tid="<?php echo $tid ?>" data-bedroom="<?php echo $room['numberBedrooms']; ?>">

                                    <div class="room_container_2">

                                        <div class="room_image-1">

                                            <img src="<?php echo $img_src; ?>">

                                        </div>

                                        <div class="room-main-1">

                                            <div class="room_header">

                                                <div class="room_title">

                                                    <p><?php echo $name; ?></p>

                                                </div>

                                                <div class="selected_dates">

                                                    <div class="start-date"><?php echo date('d-M-Y', strtotime(@$_GET['start_date'])); ?></div>

                                                    -

                                                    <div class="end-date"><?php echo date('d-M-Y', strtotime($_GET['end_date'])); ?></div>

                                                </div>



                                            </div>

                                            <div class="room_options">

                                                <ul>

                                                    <?php foreach ($options as $opt_key => $option):



                                                        if ($room[$option] && $room[$option] > 0):

                                                            $option_label = str_replace(['number', 'maxNumber'], '', $option)

                                                    ?>

                                                            <li data-option="<?php echo strtolower($option_label) ?>">

                                                                <div class="icon">

                                                                    <i class="fa-solid <?php echo trim($opt_key) ?>"></i>

                                                                </div>

                                                                <div class="value">

                                                                    <?php echo intval($room[$option]); ?>

                                                                </div>

                                                            </li>



                                                    <?php endif;

                                                    endforeach; ?>

                                                </ul>

                                            </div>

                                            <div class="guests_filter">

                                                <form id="guets_form" class="guets_form" method="POST" data-nights="<?php echo $duration ?>">

                                                    <div class="num_adults guest_search">

                                                        <label>Adults </label>



                                                        <select class="num_adults guest_fields guests_form_select" data-total="<?php echo $max_guests; ?>" name="num_adults" id="num_adults" data-placeholder="adults">

                                                            <option>Adults</option>

                                                            <?php

                                                            for ($i = 1; $i <= $max_guests; $i++) { ?>

                                                                <option class="adult_option" <?php echo $i == $adults ? 'selected' : '' ?> value="<?php echo $i ?>"><?php echo $i ?></option>

                                                            <?php } ?>

                                                        </select>

                                                    </div>



                                                    <div class="num_children guest_search">

                                                        <label>Children </label>

                                                        <select class="num_children guest_fields guests_form_select" data-total="<?php echo $max_guests; ?>" name="num_children" id="num_children" data-placeholder="children">

                                                            <option>Children</option>

                                                            <?php



                                                            for ($i = 0; $i <= $total_children; $i++) { ?>

                                                                <option class="children_option" <?php echo $i == $children ? 'selected' : '' ?> value="<?php echo $i ?>"><?php echo $i ?></option>

                                                            <?php } ?>

                                                        </select>

                                                    </div>



                                                    <div class="num_infants guest_search">

                                                        <label>Infants </label>

                                                        <select class="num_infants guest_fields<?php echo $max_infants == 0 ? ' guests_form_select' : ' net_total_guests' ?>" data-total="<?php echo $max_infants == 0 ? $max_guests : $net_guests; ?>" name="num_infants" id="num_infants" data-placeholder="infants">

                                                            <option>Infants</option>

                                                            <?php



                                                            // $total_infants = $max_infants;

                                                            // if( $max_infants < 1 ){

                                                            $total_infants = intval($max_guests) - 1;

                                                            // }



                                                            for ($i = 0; $i <= $total_infants; $i++) { ?>

                                                                <option class="infant_option" <?php echo $i == $infants ? 'selected' : '' ?> value="<?php echo $i; ?>"><?php echo $i; ?></option>

                                                            <?php } ?>

                                                        </select>

                                                    </div>

                                                </form>

                                            </div>



                                            <div class="error-wrap">

                                                <p class="err_guests" style="display:none;color:red;margin:5px 0;"></p>

                                                <p class="err_infants" style="display:none;color:red;margin:5px 0;"></p>

                                            </div>

                                            <div class="desc">

                                                <p><?php echo $room_desc[$tid]; ?></p>

                                            </div>

                                        </div><!-- room main -->

                                    </div>

                                    <div class="bottom-container">

                                        <div class="rate_plan container">

                                            <?php



                                            foreach (array_reverse($ratePlans) as $rp_key => $ratePlan):



                                                $ratePlanId = $ratePlan['ratePlanId'];

                                                $priceRetail = $ratePlan['priceRetail'];

                                                $ratePlan_info = $rate_plan_en[$ratePlanId] ?? [];



                                            ?>

                                                <div class="rate_plan_box" data-tid="<?php echo $ratePlanId ?>">

                                                    <div class="rate_plan_title">

                                                        <p><?php echo $ratePlan_info['name'] ?? ''; ?></p>

                                                        <i class="toggle_long_desc fa-solid fa-circle-info"></i>

                                                    </div>

                                                    <div class="rate_plan_desc">

                                                        <p><?php echo $ratePlan_info['description'] ?? ''; ?></p>

                                                        <?php

                                                        $desc = $ratePlan_info['description'];



                                                        $old_price = 0;

                                                        if (count($prices) > 1) {

                                                            $old_price = max($prices);

                                                        }

                                                        ?>

                                                    </div>

                                                    <div class="long_desc" style="display:none;">

                                                        <?php

                                                        $long_desc = $ratePlan_info['longDescription'] ?? '';



                                                        $long_desc = preg_replace(

                                                            '/<p>\s*<strong><em>\[?[^\]]*Booking Policy[^\]]*\]?<\/em><\/strong><\/p>[\s\S]*?(?=<p>\s*<strong><em>\[|$)/i',

                                                            '',

                                                            $long_desc

                                                        );

                                                        ?>

                                                        <?php echo $long_desc ?? ''; ?>

                                                    </div>

                                                    <div class="price_container">

                                                        <div class="room_prices">

                                                            <?php

                                                            $per_person_price = ceil(floatval($priceRetail) / floatval($max_guests));

                                                            ?>

                                                            <div class="per_person"><?php echo _currency_format_new($per_person_price, true) ?>

                                                                <label class="label">per stay</label>

                                                            </div>

                                                            <?php

                                                            ?>

                                                            <div class="room-price">

                                                                <?php if ($old_price > 0 && $old_price > $priceRetail) { ?>

                                                                    <!-- <div class="discount_label">Was</div> -->

                                                                    <div class="discount_price" data-price="<?php echo ceil($old_price) ?>"><?php echo _currency_format_new(ceil($old_price), true) ?></div>

                                                                <?php } ?>

                                                                <div class="final_price" data-price="<?php echo $priceRetail; ?>"><?php echo _currency_format_new($priceRetail, true); ?></div>

                                                            </div>

                                                        </div>

                                                        <div class="select-room">

                                                            <button class="room_select">Select</button>

                                                        </div>



                                                    </div>

                                                </div>

                                            <?php endforeach;

                                            ?>

                                        </div>

                                    </div>

                                </div>

                        <?php

                            endif;

                        }

                        ?>

                    </div>

                </div>

                <div class="cart">

                    <div class="cart_conatiner">

                        <div class="cart_head">

                            <div class="cart_heading">

                                <h1>Booking Summary</h1>

                            </div>

                        </div>

                        <div class="cart_body">

                            <?php if ($_COOKIE['cart_data']):

                                $cartData = json_decode(stripslashes($_COOKIE['cart_data']), true);

                                // pre( $cartData);

                                if ($cartData['post_id'] && !empty($cartData['post_id']) && $cartData['post_id'] == get_the_ID()):

                                    $display = 'none';

                                    $cart_rooms = $cartData['rooms'];



                                    $display = !empty($cart_rooms) ? 'block' : 'none';



                                    $total_price = $cartData['total_price'];

                                    foreach ($cart_rooms as $cr_key => $cart_room): ?>



                                        <div class="selected_room">

                                            <div class="close">

                                                <button><i class="fa-solid fa-trash"></i></button>

                                            </div>

                                            <div class="details" data-tid="<?php echo $cart_room['tid'] ?>">

                                                <div class="room_name"><?php echo $cart_room['room_name'] ?></div>

                                                <div class="room_desc"><?php echo $cart_room['rate_plan_title'] ?></div>

                                                <div class="guests_staying"><?php echo $cart_room['guests_staying'] ?></div>

                                                <div class="selected_dates">

                                                    <div class="start_date_container">

                                                        <b>Start Date:</b>

                                                        <div class="start_date"><?php echo $cartData['start_date'] ?></div>

                                                    </div>

                                                    <div class="end_date_container">

                                                        <b>End Date</b>

                                                        <div class="end_date"><?php echo $cartData['end_date'] ?></div>

                                                    </div>

                                                </div>

                                                <?php if ($cart_room['discount_price'] > 0): ?>

                                                    <div class="discount_price" data-price="<?php echo $cart_room['discount_price'] ?>" data-tid="<?php echo $cart_room['tid'] ?>"><?php echo _currency_format_new($cart_room['discount_price'], true) ?></div>

                                                <?php

                                                // $total_price = intval( $total_price ) + intval( $cart_room['price'] );

                                                endif ?>

                                                <div class="price" data-price="<?php echo $cart_room['price'] ?>"><?php echo _currency_format_new($cart_room['price'], true); ?></div>

                                            </div>

                                        </div>

                            <?php endforeach;

                                endif;

                            endif; ?>

                        </div>

                        <div class="cart_footer" style="display:<?php echo $display ?>">

                            <div class="total">

                                <input type="text" readonly data-price="<?php echo $total_price ?>" name="total_price" class="total_price" readonly value="<?php echo _currency_format_new($total_price); ?>">

                            </div>

                            <!-- <a class="proceed-btn btn" href="<?php //echo home_url( '/confirm_booking/' ); 

                                                                    ?>">Proceed</a> -->

                            <a class="proceed-btn btn" href="javascript:void(0)">Proceed</a>

                        </div>

                    </div>

                </div>

            </div>

<?php

        else:



            echo $msg;



        endif;

    } else {

        echo '<div class="err_msg">Please select check-in, check-out dates and number of guests in the previous step.</div>';

    }

    return ob_get_clean();

}





function get_reviews_args( $tags = [], $cat = [], $per_page = -1 ){

    $args = [

        'post_type'     => 'reviews',

        'post_status'   => 'publish',

        'posts_per_page' => $per_page,

        // 'orderby' => 'publish_date',

        'meta_key'      => 'review_date',

        'orderby'       => 'meta_value_num',

        'order'         => 'ASC',

        'tax_query'     => [

            'relation_ship' => 'AND',



        ],

    ];



    if (isset( $tags ) && !empty( $tags )) {

        $args['tax_query'][] = 

        [

            'taxonomy' => 'review_tags',

            'field'    => 'slug',

            'terms'    => $tags,

        ];

    }



        // optional category filter

    if (isset( $cat ) && !empty($cat)) {

        $args['tax_query'][] = array(

            'taxonomy' => 'reviews_catgory', // existing category taxonomy

            'field'    => 'slug',

            'terms'    => $cat,

        );

    }

    return $args;

}



add_shortcode('get_room_data', 'get_room_data_func');



function get_room_data_func()

{



    $atts = shortcode_atts(

        array(

            'post_id' => get_the_ID(),

        ),

        $atts,

        'get_room_data'

    );



    $post_id = $atts['post_id'];



    /*Rom types*/

    $property_id = get_field('property_id', $post_id);

    if ((@$_GET['check_rates'] && !empty($_GET['check_rates'])) && !empty(@$_GET['start_date']) && !empty(@$_GET['end_date']) && !empty(@$_GET['guests'])) {

        return do_shortcode('[room_check_rates property_id=' . $property_id . ']');

    } else {

        return do_shortcode('[get_single_page_rooms property_id=' . $property_id . ']');

    }

}



//hz_get_results_by_name

add_action("wp_ajax_hz_get_results_by_name", "hz_get_results_by_name_func");

add_action("wp_ajax_nopriv_hz_get_results_by_name", "hz_get_results_by_name_func");



function hz_get_results_by_name_func()

{

    $properties = '';

    $destinations = '';

    if (!empty(@$_POST['name'])) {



        $search_term = sanitize_text_field($_POST['name']);

        $cat_slug = sanitize_text_field($_POST['cat_slug']);



        $args = [

            'post_type'          => 'accommodation',

            'post_status'        => 'publish',

            'title_starts_with'  => $search_term,

            'posts_per_page'     => 9,

            'orderby'            => 'title', // Sort by post title

            'order'              => 'ASC',

            'fields'             => 'ids', // only return IDs

        ];



        if (!empty($cat_slug)) {

            $args['tax_query'] = 

            [

                [

                    'taxonomy' => 'accommodation-cat',

                    'field'    => 'slug',   // you can also use 'term_id'

                    'terms'    => $cat_slug,

                ],

            ];

        }



        $get_acc = new WP_Query($args);

        $acc_html = '';



        if ($get_acc->have_posts()) {



            while ($get_acc->have_posts()) {

                $get_acc->the_post();

                $acc_id = get_the_ID();

                $acc_title = get_the_title();

                $property_id = get_field('property_id');

                $area = get_field('area');



                $properties .= '<li data-value="' . $acc_title . '" data-property-id="' . $property_id . '" class="property">' . $acc_title . '</li>';

                wp_reset_postdata();

            }

        }



        $terms = get_terms([

            'taxonomy'   => 'accommodation-cat',

            'hide_empty' => false,

            'search'     => $search_term, // partial match

        ]);



        if (empty($terms) || is_wp_error($terms)) {

            return wp_send_json_success(['data' => ['properties' => $properties, 'destinations' => $destinations]]);

        }



        return wp_send_json_success(['data' => ['properties' => $properties, 'destinations' => $destinations]]);

    } else {

        return wp_send_json_success(['data' => ['properties' => $properties, 'destinations' => $destinations]]);

    }

}



add_filter( 'posts_where', 'wpse_title_starts_with_where', 10, 2 );



function wpse_title_starts_with_where( $where, $query ) {

    global $wpdb;



    // Retrieve our custom argument

    $starts_with = $query->get( 'title_starts_with' );



    if ( $starts_with ) {

        // Use LIKE with the wildcard at the end for "starts with" logic

        $where .= $wpdb->prepare( 

            " AND {$wpdb->posts}.post_title LIKE %s", 

            $wpdb->esc_like( $starts_with ) . '%' 

        );

    }



    return $where;

}



/**

 * Generate dynamic checkboxes for ACF 'bedroom_number' field based on existing post meta values.

 *

 * @param string $post_type The post type to filter meta values from.

 * @param array $selected Optional array of selected bedroom numbers.

 */

function kv_dynamic_bedroom_checkboxes($post_type = 'accommodation', $selected = array())

{

    global $wpdb;



    // Fetch distinct serialized meta values for the given post type

    $results = $wpdb->get_col($wpdb->prepare("

        SELECT DISTINCT pm.meta_value

        FROM $wpdb->postmeta AS pm

        INNER JOIN $wpdb->posts AS p ON p.ID = pm.post_id

        WHERE pm.meta_key = %s

        AND p.post_type = %s

        AND p.post_status = 'publish'

        AND pm.meta_value != ''

        ", 'bedroom_number', $post_type));



    $bedroom_values = array();



    // Unserialize each meta value and collect numbers

    foreach ($results as $meta_value) {

        $unserialized = maybe_unserialize($meta_value);

        if (is_array($unserialized)) {

            foreach ($unserialized as $unserial_key => $val) {



                if (intval($val) > 0)

                    $bedroom_values[] = intval($val);

            }

        } elseif (!empty($unserialized) && $unserialized > 0) {

            $bedroom_values[] = intval($unserialized);

        }

    }



    // Get unique and sorted values

    $bedroom_values = array_unique($bedroom_values);

    sort($bedroom_values, SORT_NUMERIC);



    // Handle selected values (GET or passed array)

    if (empty($selected) && isset($_GET['bedrooms'])) {

        $selected = array_map('intval', (array) $_GET['bedrooms']);

    }



    // Output HTML checkboxes

    if (!empty($bedroom_values)) {

        echo '<div class="bedroom_search accomodation_search">';

        echo '<label><strong>Number of Bedrooms:</strong></label>';

        echo '<div class="bedroom_chkbox">';

        foreach ($bedroom_values as $val) {

            $checked = in_array($val, $selected) ? 'checked' : '';

            echo sprintf(

                '<label style="margin-right:10px;">

                <input type="checkbox" name="bedrooms[]" value="%d" %s> %d

                </label>',

                $val,

                $checked,

                $val

            );

        }

        echo '</div>';



        echo '</div>';

    } else {

        echo '<p>No bedroom data found for this post type.</p>';

    }

}



add_filter('acf/load_field', 'hz_make_acf_readonly_fields');



function hz_make_acf_readonly_fields($field) {

    $readonly_fields = [

        'acc_hotel_id', 'room_hotel_id', 'room_type_id', '_payment_id',

        '_external_reference', '_amount', '_expires_at', '_booking_reference',

        'jp_rooms', 'jp_rooms_link', 'is_roomboss', 'rate_plan_id', 'rate_plan_name', 

        'rate_plan_description', 'rate_plan_long_descriptions', 'supplier_code', 

        'roomboss_room_id', 'accommodation_list_image', 'acc_property_code',

        'acc_phone_number', 'acc_fax_number', 'acc_media_code', 'acc_email', 'acc_no_of_bedrooms',

        'acc_max_child_age', 'acc_deposit_amount', 'acc_supplier_deposit', 'acc_property_type',

        'acc_supplier_commission', 'rate_plan'

    ];



    if ( in_array($field['name'], $readonly_fields) ) {

        $field['readonly'] = 1;

    }



    return $field;

}



add_filter('acf/prepare_field', 'hz_hide_specific_acf_fields');

function hz_hide_specific_acf_fields($field) {

        $hidden_fields = [

            'acc_no_of_bedrooms', 'supplier_type', 'supplier_id', 'room_boss_vendor_id',

            'arrival_departure', 'vendor_type_id', 'booking_permission_id', 'supplier_code', 

            'check_in_time', 'check_out_time', 'backoffice_ref', 'pricing_model', 'room_price',

            'supplier_id', 'id','supplier_type','room_boss_vendor_id','arrival_departure', 

            'vendor_type_id','booking_permission_id','supplier_code',

            'supplier_notes','supplier_email','supplier_address','supplier_telephone',

            'backoffice_ref','auto_confirm','account_notes','travel_e_doc_notes',

            'booking_coaches_strategy','product_type','check_in_time','check_out_time',

            'terms_conditions','supplier_description','image_url','supplier_url',

            'book_and_pay','hide_request_if_book_and_pay_enabled','supplier_latitude',

            'supplier_longitude','supplier_currency_code','supplier_country_code',

            'supplier_location_code','price_mode','supplier_price','sup_is_bb',

            'supp_created_at','supp_updated_at','supplier_name', 'suuplier_currency_code',

            'acc_trip_advisor_url', 'acc_sku_code', 'unit_count', 'acc_supplier_markup', 'property_id',

            'acc_media_code', 'acc_phone_number', 'acc_fax_number', 'acc_email', 'acc_max_child_age', 'long_desc'

        ];



    // Returning false in acf/prepare_field completely removes the field and its label from the DOM

    if (in_array($field['name'], $hidden_fields) || (isset($field['_name']) && in_array($field['_name'], $hidden_fields))) {

        return false;

    }



    return $field;

}



isset($_GET['reset_permalink']) && intval($_GET['reset_permalink']) ? add_action('wp_head', 'hz_reset_perma_link') : '';



function hz_reset_perma_link()

{



    $post_id = $_GET['reset_permalink'];

    $permalink_array = get_option('permalink-manager-uris');



    if ($permalink_array[$post_id]) {

        unset($permalink_array[$post_id]);

    }

    update_option('permalink-manager-uris', $permalink_array);

}



add_action("wp_ajax_fetch_room_details", "hz_get_room_data");

add_action("wp_ajax_nopriv_fetch_room_details", "hz_get_room_data");



/**

 * Fetch detailed room information via AJAX

 * 

 * @return void Sends JSON response with room data

 */

function hz_get_room_data()

{

    try {

        // ✅ STEP 1: Validate and sanitize input

        $room_tid = isset($_POST['room_tid']) ? sanitize_text_field($_POST['room_tid']) : '';

        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['room_tid']) : '';



        if (empty($room_tid)) {

            return wp_send_json_error([

                'message' => 'Room ID is required',

                'code' => 'missing_room_id'

            ], 400);

        }



        $hotel_id = get_post_id_by_typeId($property_id, 'accommodation');

        $room_id = get_post_id_by_typeId($room_tid, 'room');



        if (empty($room_id) || $room_id <= 0) {

            return wp_send_json_error([

                'message' => 'Room not found',

                'code' => 'room_not_found'

            ], 404);

        }



        // ✅ STEP 3: Verify room post exists

        $room_post = get_post($room_id);



        if (!$room_post || $room_post->post_status !== 'publish') {

            return wp_send_json_error([

                'message' => 'Room is not accessible',

                'code' => 'room_not_accessible'

            ], 403);

        }



        // ✅ STEP 4: Get room image

        $room_img = get_the_post_thumbnail_url($room_id, 'medium');



        if (!$room_img) {

            $room_img = get_stylesheet_directory_uri() . '/images/placeholder-listing.jpg'; // Fallback to empty string

        }



        // ✅ STEP 5: Get room description

        $room_desc = get_the_content(null, false, $room_id);



        if (!$room_desc) {

            $room_desc = $room_post->post_excerpt ?? ''; // Fallback to excerpt

        }



        // ✅ STEP 6: Build room fields array

        $field_options = [

            'guest' => 'room_guests',

            'bed' => 'room_bedroom',

            'bath' => 'room_bathroom',

            'infant' => 'room_infants',

        ];



        $room_fields = '';



        foreach ($field_options as $icon_class => $field_name) {

            $field_value = get_field($field_name, $room_id);



            if (!empty($field_value)) {

                $field_value = intval($field_value); // Type-cast to integer

                $room_fields .= '<span class="rb-icon ' . esc_attr($icon_class) . '">' 

                    . esc_html($field_value) 

                    . '</span>';

            }

        }



        // Fetch Terms & Conditions from the accommodation post stored in the cart

        $terms_content = get_field('terms_conditions', $hotel_id);



        if ($terms_content) :



            $terms_popup = '<div id="terms-modal" class="rb-terms-modal">';

                $terms_popup .= '<div class="rb-terms-modal-content">';

                $terms_popup .= '<span class="rb-terms-close" title="Close">&times;</span>';

                    $terms_popup .= '<div class="rb-terms-body">';

                        $terms_popup .= '<h3>Terms & Conditions</h3>';

                        $terms_popup .= wp_kses_post($terms_content);

                    $terms_popup .= '</div>';

                $terms_popup .= '</div>';

            $terms_popup .= '</div>';

        endif;



        // ✅ STEP 7: Return success response

        return wp_send_json_success([

            'hotel_id' => $hotel_id,

            'room_id' => $room_id,

            'room_img' => esc_url($room_img),

            'room_fields' => $room_fields,

            'room_desc' => wp_kses_post($room_desc),

            'terms_popup' => $terms_popup ?? '',

        ]);



    } catch (Exception $e) {

        // ❌ Catch unexpected errors

        return wp_send_json_error([

            'message' => 'An unexpected error occurred',

            'code' => 'unexpected_error',

            'details' => $e->getMessage()

        ], 500);

    }

}



add_filter('wpseo_breadcrumb_links', 'hz_accommodation_breadcrumbs_from_url');

function hz_accommodation_breadcrumbs_from_url($links)

{

    // pre( 'links before' );

    // pre( $links );

    // if (!is_singular('accommodation')) {

    //     return $links;

    // }

    // pre( var_dump( is_page('accommodation') ) );

    if (is_page('accommodation')) { 

        return $links;

    }



    $path = trim(

        wp_make_link_relative(get_permalink()),

        '/'

    );



    $parts = array_values(array_filter(explode('/', $path)));



    // Expecting: [resort, accommodation, slug]

    if (count($parts) < 3) {



        // pre( 'links after 1' );

        // pre( $links );

        return $links;

    }

    

    $title = get_the_title();



    [$resort, $accommodation] = $parts;



    $new = [];



    $new[] = [

        'url'  => home_url('/'),

        'text' => 'Home',

    ];



    $new[] = [

        'url'  => home_url("/{$resort}/"),

        'text' => ucwords(str_replace('-', ' ', $resort)),

    ];



    $new[] = [

        'url'  => home_url("/{$resort}/{$accommodation}/"),

        'text' => ucwords(str_replace('-', ' ', $accommodation)),

    ];



    $new[] = [

        'text' => $title,

    ];

    // pre( 'links after2' );

    // pre( $new );

    return $new;

}



add_shortcode('show_tagged_reviews', 'codex_tagged_reviews');

function codex_tagged_reviews( $atts)

{



    $atts = shortcode_atts( [

        'tags' => 'accommodation-best',

        'required' => 5,

    ], $atts, 'show_tagged_reviews' );



    ob_start();

    $review_data = get_review_data();

    $average = $review_data['average'];

    $total = $review_data['total'];



    $count = $review_data['count'] ?? $average . ' Average ' . $total . ' Reviews';

    $paged = isset($_POST['ajaxpage']) ? $_POST['ajaxpage'] : 1;



    $schemaReviews = [];

    

    $tags = [ trim( $atts['tags'] ) ];

    if( strpos( $atts['tags'] , ',' ) ){

        $tags = explode(',', $atts['tags'] );

    }



    $args = get_reviews_args( $tags );



    $loop = new WP_Query($args);

    ?>

    <div class="cs_reviews" data-page="<?php echo $paged; ?>">

        <div class="cs__inn">

            <div class="cs_total">

                <a href="https://www.reviews.io/company-reviews/store/japanskiexperience-com1" target="_blank">

                    <span class="total_count"><?= $average; ?></span>

                    <span class="fa star-<?= round($average); ?>"></span>

                    <span class="total_reviews v1"><?= $count; ?></span>

                    <img src="https://s3-us-west-1.amazonaws.com/reviews-us-assets/assets/widget-assets/logos/logo-reviewsio--black--md.png"

                         width="105" height="15" alt="">

                </a>

            </div> <!-- cs_total -->

            <div class="cs_blank">&nbsp;</div> <!-- cs_blank -->

        </div> <!-- cs__inn -->

        <div class="reviews-ajax">

            <?php

            while ($loop->have_posts()) : $loop->the_post();

                $date = get_field('review_date', get_the_ID());

                $rating = get_post_meta(get_the_ID(), 'review_rating', 1) ?: '5';

                $reviewDate = date("d/m/Y", strtotime($date));

                ?>

                <div class="review_users">

                    <div class="user_name_rv">

                        

                        <h3><?php echo str_replace('&quot;', '' , html_entity_decode( get_the_title() ) ) ; ?></h3>

                        <span class="date"><?= $reviewDate; ?></span>

                        <div class="rv_image">

                            <a href="https://www.reviews.io/company-reviews/store/japanskiexperience-com1"

                               target="_blank">

                                <img src="https://s3-us-west-1.amazonaws.com/reviews-us-assets/assets/widget-assets/logos/favicon-reviewsio--black--sm.png"

                                     width="25" height="25">

                                <span>- Review.io</span>

                            </a>

                        </div> <!-- rv_image -->

                    </div> <!-- user_name_rv -->

                    <div class="user_comment">

                        <span class="fa star-<?= $rating; ?>"></span>

                        <p class="review-more"><?php echo get_the_content(); ?></p>

                        <!-- Ali bhai is par show hide wala kam karna hai -->

                    </div> <!-- user_comment -->

                </div> <!-- review_users -->

            <?php

            endwhile;

            ?>

        </div>

        <?php

        wp_reset_postdata();

        if ($loop->max_num_pages > 1)

            echo '<div class="_loadmore btn">Load More</div>';

        ?>

    </div> <!-- cs_reviews -->

    <script>

    jQuery(function ($) { // use jQuery code inside this to avoid "$ is not defined" error

        let page = parseInt( $( '.cs_reviews' ).attr( 'data-page' ) ) + 1;

        let max = <?= $loop->max_num_pages; ?>;

        $('._loadmore').click(function () {

            let button = $(this);

            $.ajax({ // you can also use $.post here

                url: window.location.href, // AJAX handler

                data: {ajaxpage: page},

                type: 'POST',

                beforeSend: function (xhr) {

                    button.text('Loading...'); // change the button text, you can also add a preloader image

                },

                success: function (data) {

                    if (data) {

                        let html = $('.reviews-ajax', data).html();

                        $('.reviews-ajax').append(html);

                        button.text('Load More'); // insert new posts

                        page = page + 1;

                        $( '.cs_reviews' ).attr( 'data-page', page );

                        trim_review_content();

                        if (page == max)

                            button.remove(); // if last page, remove the button

                    } else {

                        button.remove(); // if no data, remove the button as well

                    }

                }

            });

        });



        /*review js code*/

        function trim_review_content(){



            // Configure/customize these variables.

            var showChar = 60; // How many characters are shown by default

            var showCharReview = 200; // How many characters are shown by default

            var ellipsestext = "...";

            var moretext = "Read More";

            var lesstext = "Read Less";



            $('.review-more').each(function () {

                var content = $(this).html();

                console.log( content );

                if (content.length > showCharReview && $(this).find('.morecontent').length == 0 ) {

                    var c = content.substr(0, showCharReview);

                    var h = content.substr(showCharReview, content.length - showCharReview);

                    var html = c + '<span class="moreellipses">' + ellipsestext + '&nbsp;</span><span class="morecontent"><span>' + h + '</span>&nbsp;&nbsp;<a href="" class="morelink">' + moretext + '</a></span>';

                    $(this).html(html);

                }

            });



            $('.more').each(function () {

                var content = $(this).html();



                if (content.length > showChar) {

                    var c = content.substr(0, showChar);

                    var h = content.substr(showChar, content.length - showChar);

                    var html = c + '<span class="moreellipses">' + ellipsestext + '&nbsp;</span><span class="morecontent"><span>' + h + '</span>&nbsp;&nbsp;<a href="" class="morelink">' + moretext + '</a></span>';

                    $(this).html(html);

                }

            });



            $(".morelink").attr("rel","nofollow");

            $(".morelink").attr("href","javascript:;");

        }

        trim_review_content();

        var moretext = "Read More";

        var lesstext = "Read Less";



        $(document).on('click', '.morelink', function() {

            if ($(this).hasClass("less")) {

                $(this).removeClass("less");

                $(this).html(moretext);

            } else {

                $(this).addClass("less");

                $(this).html(lesstext);

            }

            $(this).parent().prev().toggle();

            $(this).prev().toggle();

            return false;

        });

    });

    </script>

    <?php

    return '' . ob_get_clean();

}



add_shortcode('company_rating', 'kv_company_rating_shortcode');

function kv_company_rating_shortcode()

{

    // Fetch stored options

    $review_data = get_review_data();

    $rating        = $review_data['average']; //kv_company_average_rating

    $total_reviews = $review_data['total']; //'kv_company_total_reviews'

    // Safety fallback

    if (!$rating || !$total_reviews) {

        return '';

    }

    ob_start();

    ?>

    <div class="ratingarea">

        <div class="rate_star top_style">

            <img src="<?php echo get_template_directory_uri(); ?>/images/rating-star.svg"

                 class="attachment-full size-full"

                 alt="Rating stars"

                 decoding="async">

        </div>



        <span class="top_style">

            <?php echo esc_html($rating); ?> | <?php echo esc_html(number_format($total_reviews)); ?> Reviews

        </span>



        <div class="brand top_style">

            <img src="https://japanskiexperience.com/wp-content/uploads/2025/12/Brand.svg"

                 class="attachment-full size-full"

                 alt="Brand"

                 decoding="async">

        </div>



    </div>

    <?php



    return ob_get_clean();

}



function get_review_data() {



    $average = (float) get_field('average_reviews', 'option');

    $total   = (int) get_field('total_reviews', 'option');

    $average_display = round($average, 1);

    $total_display   = number_format_i18n($total);



    $count_display = $average_display . ' Average ' . $total_display . ' Reviews';



    $title = get_field('review_title', 'option') ?: 'Rated excellent by 900+ customers';



    return [

        'average' => $average,

        'total'   => $total,

        'count'   => $count_display,

        'title'   => $title

    ];

}



add_filter( 'gform_confirmation_anchor_3', '__return_false' );  



function disable_wp_emojicons() {

    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );

    remove_action( 'wp_print_styles', 'print_emoji_styles' );

    remove_filter( 'tiny_mce_plugins', 'wpemoji' );

    add_filter( 'emoji_svg_url', '__return_false' );

}

add_action( 'init', 'disable_wp_emojicons' );



function get_last_gravity_form_entry_id( $form_id ) {

    // Define search criteria, sorting, and paging

    $search_criteria = array();

    $sorting = array(

        'key'      => 'date_created', // Sort by the date the entry was created

        'direction' => 'DESC'          // Sort in descending order (newest first)

    );

    $paging = array(

        'offset'    => 0,            // Start from the first result

        'page_size' => 1             // Only retrieve one entry

    );



    // Retrieve entries using the Gravity Forms API

    $entries = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );



    // Check if any entries were found

    if ( ! empty( $entries ) ) {

        // The first element in the array is the latest entry

        $last_entry = $entries[0];

        return $last_entry['id']; // Return the entry ID

    } else {

        return null; // No entries found

    }

}



/* add hook to sort accomdations in asc order alphbetically then add menu_order in desc order when accomdation is saved */

/*modify this function to wp_head i will execute it manually as executing once will update all accomdations*/

@$_GET['update_acc'] == 'yes' ? add_action('wp_head', 'sort_accommodations', 20, 3) : '';

function sort_accommodations(){



    // only get accommodations which have menu order 0

    $args = [

        'post_type'      => 'accommodation',

        'posts_per_page' => -1,

        'orderby'        => 'title',

        'order'          => 'ASC',

        'menu_order'     => 0, // Targets the native post field directly

    ];



    $accommodations = get_posts($args);



    // Update menu_order based on alphabetical order

    foreach ($accommodations as $index => $accommodation) {

        /*only update if menu_order is 0*/

        if( $accommodation->menu_order == 0 || empty( $accommodation->menu_order ) ){

            wp_update_post([

                'ID' => $accommodation->ID,

                'menu_order' => count( $accommodations ) - $index, // Start menu_order from 1

            ]);

        }

    }

}



function keep_one_delete_duplicate_images($args = []) {

    global $wpdb;



    $defaults = [

        'meta_key'   => '_source_url',

        'like'       => '%dh1msuk8kbcis.cloudfront.net%',

        'batch_size' => 50,

        'dry_run'    => true, // pehle true rakho

    ];



    $args = array_merge($defaults, $args);



    $total_deleted = 0;



    // Step 1: Find duplicate groups

    $duplicates = $wpdb->get_results($wpdb->prepare("

        SELECT meta_value, MIN(post_id) as keep_id, COUNT(*) as total

        FROM {$wpdb->postmeta}

        WHERE meta_key = %s

        AND meta_value LIKE %s

        GROUP BY meta_value

        HAVING COUNT(*) > 1 LIMIT 100

    ", $args['meta_key'], $args['like']));



    // pre(sprintf("

    //     SELECT meta_value, MIN(post_id) as keep_id, COUNT(*) as total

    //     FROM {$wpdb->postmeta}

    //     WHERE meta_key = %s

    //     AND meta_value LIKE %s

    //     GROUP BY meta_value

    //     HAVING COUNT(*) > 1

    // ", $args['meta_key'], $args['like']));

    // pre($duplicates, 1);



    foreach ($duplicates as $dup) {



        // Step 2: Get all IDs except the one we keep

        $ids = $wpdb->get_col($wpdb->prepare("

            SELECT post_id FROM {$wpdb->postmeta}

            WHERE meta_key = %s

            AND meta_value = %s

            AND post_id != %d

        ", $args['meta_key'], $dup->meta_value, $dup->keep_id));



        foreach ($ids as $attachment_id) {



            if ($args['dry_run']) {

                error_log("DRY RUN: Delete ID = " . $attachment_id . " | Keep ID = " . $dup->keep_id);

            } else {

                $deleted = wp_delete_attachment($attachment_id, true);



                if ($deleted) {

                    $total_deleted++;

                } else {

                    error_log("Failed to delete ID: " . $attachment_id);

                }

            }

        }

    }



    return [

        'status' => $args['dry_run'] ? 'dry_run' : 'done',

        'deleted' => $total_deleted

    ];

}

add_action('admin_init', function() {

    if (isset($_GET['delete_dups']) && $_GET['delete_dups'] === 'yes') {

        $result = keep_one_delete_duplicate_images(['dry_run' => false]);

        echo '<pre>';

        print_r($result);

        echo '</pre>';

        exit;

    }

});



/**

 * Add custom bulk action for syncing selected accommodations

 * Adds "Sync with Booking System" option to bulk actions dropdown on accommodation listing page

 * 

 * @param array $bulk_actions Existing bulk actions

 * @return array Updated bulk actions array

 */

function hz_add_accommodation_bulk_sync_action($bulk_actions) {

    $bulk_actions['hz_sync_accommodations'] = __('Sync with Booking System', 'kv_theme');

    return $bulk_actions;

}

add_filter('bulk_actions-edit-accommodation', 'hz_add_accommodation_bulk_sync_action');



/**

 * Handle bulk sync action for accommodations

 * Processes selected accommodation IDs and enqueues them for syncing

 * 

 * @param string $redirect_to URL to redirect to after action

 * @param string $action The bulk action identifier

 * @param array $post_ids Array of post IDs selected for the action

 * @return string Redirect URL with sync status

 */

function hz_handle_accommodation_bulk_sync($redirect_to, $action, $post_ids) {

    if ($action !== 'hz_sync_accommodations' || empty($post_ids)) {

        return $redirect_to;

    }



    // Store selected post IDs in a transient for the AJAX handler

    $transient_key = 'hz_sync_acc_ids_' . uniqid();

    set_transient($transient_key, $post_ids, HOUR_IN_SECONDS);



    // Add query args to redirect URL with transient key

    $redirect_to = add_query_arg([

        'hz_sync_transient' => $transient_key,

        'hz_sync_count' => count($post_ids),

    ], $redirect_to);



    return $redirect_to;

}

add_filter('handle_bulk_actions-edit-accommodation', 'hz_handle_accommodation_bulk_sync', 10, 3);



/**

 * Display admin notice when bulk sync is in progress

 * Shows a notice with JavaScript that triggers AJAX sync

 * 

 * @return void

 */

function hz_display_accommodation_sync_notice() {

    if (!isset($_GET['hz_sync_transient']) || !current_user_can('edit_posts')) {

        return;

    }



    $transient_key = sanitize_text_field($_GET['hz_sync_transient']);

    $count = isset($_GET['hz_sync_count']) ? intval($_GET['hz_sync_count']) : 0;



    if (!$count || !$transient_key) {

        return;

    }



    ?>

    <div class="notice notice-info" id="hz-sync-notice">

        <p>

            <strong><?php echo sprintf(__('Syncing %d accommodation(s) with Booking System...', 'kv_theme'), $count); ?></strong>

            <br><small id="hz-sync-progress">Starting sync...</small>

        </p>

    </div>

    <script type="text/javascript">

        document.addEventListener('DOMContentLoaded', function() {

            hz_start_accommodation_sync('<?php echo esc_js($transient_key); ?>');

        });



        function hz_start_accommodation_sync(transientKey) {

            var nonce = '<?php echo wp_create_nonce('hz_sync_acc_nonce'); ?>';

            var offset = 0;

            var total = <?php echo intval($count); ?>;



            function sendBatch() {

                jQuery.ajax({

                    url: ajaxurl,

                    type: 'POST',

                    timeout: 30000,

                    data: {

                        action: 'hz_sync_selected_accommodations',

                        transient_key: transientKey,

                        nonce: nonce,

                        offset: offset

                    },

                    success: function(response) {

                        if (response.success) {

                            document.getElementById('hz-sync-progress').textContent = response.data.message;



                            if (response.data.has_more) {

                                offset = response.data.offset;

                                setTimeout(sendBatch, 500);

                            } else {

                                document.getElementById('hz-sync-notice').className = 'notice notice-success';

                                setTimeout(function() {

                                    location.href = location.href.split('?')[0] + '?post_type=accommodation';

                                }, 2000);

                            }

                        } else {

                            document.getElementById('hz-sync-progress').textContent = 'Error: ' + response.data.message;

                            document.getElementById('hz-sync-notice').className = 'notice notice-error';

                        }

                    },

                    error: function() {

                        document.getElementById('hz-sync-progress').textContent = 'Network error — retrying in 3s...';

                        setTimeout(sendBatch, 3000);

                    }

                });

            }



            sendBatch();

        }

    </script>

    <?php

}

add_action('admin_notices', 'hz_display_accommodation_sync_notice');



/**

 * AJAX handler for syncing selected accommodations

 * Retrieves accommodation IDs from transient and processes each one

 * 

 * @return void Sends JSON response with sync results

 */

// function hz_ajax_sync_selected_accommodations() {

//     set_time_limit(0); // Ahtisham

//     if (!current_user_can('edit_posts')) {

//         wp_send_json_error(['message' => 'Unauthorized'], 403);

//     }



//     $transient_key = isset($_POST['transient_key']) ? sanitize_text_field($_POST['transient_key']) : '';

    

//     if (empty($transient_key)) {

//         wp_send_json_error(['message' => 'Missing transient key']);

//     }



//     // Verify nonce

//     check_ajax_referer('hz_sync_acc_nonce', 'nonce');



//     // Retrieve post IDs from transient

//     $post_ids = get_transient($transient_key);

//     // $post_ids = [336571, 14761];

//     // $post_ids = [40217];

//     // pre($post_ids, 1);

    

//     if (empty($post_ids) || !is_array($post_ids)) {

//         wp_send_json_error(['message' => 'No accommodations found to sync']);

//     }



//     // Delete the transient to prevent duplicate processing

//     delete_transient($transient_key);



//     $synced_count = 0;

//     $failed_count = 0;

//     $errors = [];



//     // Sync each selected accommodation

//     foreach ($post_ids as $post_id) {

//         $post_id = intval($post_id);

//         if ($post_id < 1) {

//             continue;

//         }



//         $result = hz_sync_single_accommodation($post_id);



//         if ($result['success']) {

//             $synced_count++;

//         } else {

//             $failed_count++;

//             $errors[] = "Post #{$post_id}: " . $result['message'];

//         }

//     }



//     // Prepare response message

//     $message = sprintf(

//         __('Successfully synced %d accommodation(s)', 'kv_theme'),

//         $synced_count

//     );



//     if ($failed_count > 0) {

//         $message .= sprintf(

//             __(' with %d failure(s)', 'kv_theme'),

//             $failed_count

//         );

//     }



//     wp_send_json_success([

//         'message' => $message,

//         'synced' => $synced_count,

//         'failed' => $failed_count,

//         'errors' => $errors,

//     ]);

// }

function hz_ajax_sync_selected_accommodations() {

    @set_time_limit(0);

    try {

    if (!current_user_can('edit_posts')) {

        wp_send_json_error(['message' => 'Unauthorized'], 403);

    }



    $transient_key = isset($_POST['transient_key']) ? sanitize_text_field($_POST['transient_key']) : '';

    

    if (empty($transient_key)) {

        wp_send_json_error(['message' => 'Missing transient key']);

    }



    // Verify nonce

    check_ajax_referer('hz_sync_acc_nonce', 'nonce');



    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    $batch_size = 5;



    // Retrieve post IDs from transient

    $post_ids = get_transient($transient_key);

    

    if (empty($post_ids) || !is_array($post_ids)) {

        wp_send_json_error(['message' => 'No accommodations found to sync']);

    }



    $total = count($post_ids);

    $batch = array_slice($post_ids, $offset, $batch_size);

    $has_more = ($offset + $batch_size) < $total;



    // Only delete transient on last batch

    if (!$has_more) {

        delete_transient($transient_key);

    }



    $synced_count = 0;

    $failed_count = 0;

    $errors = [];



    foreach ($batch as $post_id) {

        $post_id = intval($post_id);

        if ($post_id < 1) {

            continue;

        }



        $result = hz_sync_single_accommodation($post_id);



        if ($result['success']) {

            $synced_count++;

        } else {

            $failed_count++;

            $errors[] = "Post #{$post_id}: " . $result['message'];

        }

    }



    $next_offset = $offset + count($batch);

    $message = "Synced {$next_offset}/{$total} accommodation(s)";

    if ($has_more) {

        $message .= " — continuing...";

    } else {

        $message .= " — Complete!";

    }



    wp_send_json_success([

        'message' => $message,

        'synced' => $synced_count,

        'failed' => $failed_count,

        'errors' => $errors,

        'offset' => $next_offset,

        'total' => $total,

        'has_more' => $has_more,

    ]);



    } catch (\Throwable $e) {

        error_log('[hz_sync] Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        wp_send_json_error(['message' => 'Fatal error: ' . $e->getMessage()]);

    }

}

add_action('wp_ajax_hz_sync_selected_accommodations', 'hz_ajax_sync_selected_accommodations');

add_action('wp_ajax_nopriv_hz_sync_selected_accommodations', 'hz_ajax_sync_selected_accommodations');


// Ahtisham start code
function hz_background_sq_mapping($property_data) {
    try {
        sq_mapping_properties([$property_data]);
    } catch (\Throwable $e) {
        error_log('[hz_sync] background sq_mapping failed: ' . $e->getMessage());
    }
}
add_action('hz_background_sq_mapping', 'hz_background_sq_mapping', 10, 1);
// Ahtisham end code


/**

 * Sync a single accommodation with Booking System

 * Fetches property data from API and updates the WordPress post

 * Reuses the existing kv_sync_accommodation REST endpoint logic

 * 

 * @param int $post_id WordPress post ID of the accommodation

 * @return array Array with 'success' (bool) and 'message' (string) keys

 */

function hz_sync_single_accommodation($post_id) {

    try {

        $post_id = intval($post_id);



        // Validate post exists and is an accommodation

        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'accommodation') {

            return [

                'success' => false,

                'message' => 'Invalid accommodation post',

            ];

        }



        // Get hotel ID from post meta (supports both RoomBoss and BedBank)

        // $hotel_id = get_post_meta($post_id, 'acc_hotel_id', true);

        // if (empty($hotel_id)) {

        $hotel_id = get_post_meta($post_id, 'property_id', true);

        // }

        if (empty($hotel_id)) {

            return [

                'success' => false,

                'message' => 'No hotel ID found in post metadata',

            ];

        }

        // Fetch property data from Booking System API

        $property_data = hz_fetch_property_from_api($hotel_id);

        // pre($hotel_id, 0);

        if (!$property_data || is_wp_error($property_data)) {

            $error_msg = is_wp_error($property_data) ? $property_data->get_error_message() : 'Unknown API error';

            return [

                'success' => false,

                'message' => 'Failed to fetch from API: ' . $error_msg,

            ];

        }

        // Sync the accommodation using existing REST endpoint logic

        $sync_result = hz_process_accommodation_sync($post_id, $property_data);

        // pre([$property_data], 1);

        // sq_mapping_properties([$property_data]); // Hamza       
        // hz_sync_selected_accommodations();
        // pre($sync_result, 1);

        // Queue sq_mapping_properties for background processing via WP-Cron
        // (image downloads are too heavy for the 60s proxy timeout)
        wp_schedule_single_event(time() + 5, 'hz_background_sq_mapping', [$property_data]); // Ahtisham

        if (!$sync_result['success']) {

            return [

                'success' => false,

                'message' => $sync_result['message'] ?? 'Unknown sync error',

            ];

        }

        return [

            'success' => true,

            'message' => 'Accommodation synced successfully',

            'post_id' => $post_id,

        ];



    } catch (Exception $e) {

        return [

            'success' => false,

            'message' => 'Exception: ' . $e->getMessage(),

        ];

    }

}



/**

 * Fetch property data from Booking System API by hotel ID

 * Uses the existing API endpoint with propertyIds parameter

 * 

 * @param string|int $hotel_id The hotel/property ID to fetch

 * @return array|WP_Error Array of property data or WP_Error on failure

 */

function hz_fetch_property_from_api($hotel_id) {

    try {

        $hotel_id = sanitize_text_field($hotel_id);



        if (empty($hotel_id)) {

            return new WP_Error('invalid_id', 'Hotel ID cannot be empty');

        }



        // Build API URL with propertyIds parameter

        $apiUrl = KV_BOOKING_SYSTEM_BASE . '/api/get-properties-by-ids';



        // Get API request arguments (includes auth token)

        $args = booking_sys_api_args();

        $args = array_merge($args, [

            'timeout' => 10, // Ahtisham

            'body' => json_encode([

                'propertyIds' => [$hotel_id],

            ]),

            'headers' => array_merge($args['headers'], [

                'Content-Type' => 'application/json',

            ]),

        ]);

        

        // pre($args, 0);



        if (empty($args)) {

            return new WP_Error('api_args_failed', 'Failed to build API request arguments');

        }

        // Make API request

        $response = wp_remote_post($apiUrl, $args);

        // Validate response

        if (is_wp_error($response)) {

            return new WP_Error('api_error', 'API request failed: ' . $response->get_error_message());

        }

        // Check HTTP status code

        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200) {

            return new WP_Error('http_error', 'API returned HTTP ' . $http_code);

        }

        // Parse JSON response

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {

            return new WP_Error('empty_response', 'API returned empty response');

        }

        $result = json_decode($body, true);

        if (!is_array($result)) {

            return new WP_Error('invalid_json', 'API returned invalid JSON');

        }

        // pre($result, 1);



        // Extract first property from response

        if (empty($result['properties']) || !is_array($result['properties'])) {

            return new WP_Error('no_properties', 'No properties found in API response');

        }



        // Return first property (should be the only one)

        return $result['properties'][0] ?? null;



    } catch (Exception $e) {

        return new WP_Error('exception', 'Exception: ' . $e->getMessage());

    }

}



/**

 * Process accommodation sync with fetched property data

 * Updates WordPress post with property information

 * Based on the kv_sync_accommodation REST endpoint logic

 * 

 * @param int $post_id WordPress post ID

 * @param array $property_data Property data from API

 * @return array Array with 'success' (bool) and optional 'message' (string)

 */

function hz_process_accommodation_sync($post_id, $property_data) {

    try {

        $post_id = intval($post_id);

        if ($post_id < 1 || !$property_data || !is_array($property_data)) {

            return [

                'success' => false,

                'message' => 'Invalid post ID or property data',

            ];

        }

        // Extract property location and details

        $property_location = isset($property_data) ? $property_data : [];

        if (empty($property_location)) {

            return [

                'success' => false,

                'message' => 'Invalid property location data',

            ];

        }

        // Determine property source (RoomBoss vs BedBank)

        $is_bedbank = empty($property_location['room_boss_hotel_id']);

        // Resolve hotel ID

        if ($is_bedbank) {

            $hotel_id = sanitize_text_field($property_location['id'] ?? '');

        } else {

            $hotel_id = sanitize_text_field($property_location['room_boss_hotel_id'] ?? '');

        }

        if (empty($hotel_id)) {

            return [

                'success' => false,

                'message' => 'Unable to resolve hotel ID from property data',

            ];

        }

        // Determine post status

        $is_enabled = (bool) ($property_location['is_enabled'] ?? false);

        $status = $is_enabled ? 'publish' : 'draft';

        // Prepare post data for update

        $title = $property_location['name'] ?? 'Accommodation';

        $content = $property_location['long_description'] ?? '';

        $post_args = [

            'ID' => $post_id,

            'post_title' => wp_strip_all_tags($title),

            'post_content' => wp_kses_post($content),

            'post_status' => $status,

        ];



        // Update the post

        $updated = wp_update_post($post_args, true);

        if (is_wp_error($updated)) {

            return [

                'success' => false,

                'message' => 'Failed to update post: ' . $updated->get_error_message(),

            ];

        }

        // Update post metadata

        if ($is_bedbank) {

            update_post_meta($post_id, 'property_id', $hotel_id);

        } else {

            update_post_meta($post_id, 'acc_hotel_id', $hotel_id);

        }

        // Update source flag

        update_post_meta($post_id, 'is_roomboss', $is_bedbank ? 0 : 1);

        return [

            'success' => true,

            'message' => 'Accommodation updated successfully',

            'post_id' => $post_id,

        ];



    } catch (Exception $e) {

        return [

            'success' => false,

            'message' => 'Exception: ' . $e->getMessage(),

        ];

    }

}



function filter_search_by_cpt($query) {

    // Only modify the query if it's the main search query on the front-end

    if (!is_admin() && $query->is_main_query() && $query->is_search()) {

        // Define an array of the CPT slugs you want to include



        $query->set('post_type', array('accommodation', 'post'));

    }

}

add_action('pre_get_posts', 'filter_search_by_cpt');





/* ──────────────────────────────────────────────────────────────

 * Admin: Filter japan_rooms list by parent accommodation

 * Uses post_parent (set during sync via sq_mapping_properties)

 * ────────────────────────────────────────────────────────────── */



/**

 * Enqueue Select2 on the japan_rooms list screen.

 */

add_action( 'admin_enqueue_scripts', 'kv_rooms_enqueue_select2' );

function kv_rooms_enqueue_select2( $hook ) {

    if ( $hook !== 'edit.php' ) {

        return;

    }

    $screen = get_current_screen();

    if ( ! $screen || $screen->post_type !== 'japan_rooms' ) {

        return;

    }



    wp_enqueue_style(

        'select2',

        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',

        [],

        '4.1.0'

    );

    wp_enqueue_script(

        'select2',

        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',

        [ 'jquery' ],

        '4.1.0',

        true

    );



    /* Inline script: initialise Select2 on the accommodation dropdown */

    wp_add_inline_script( 'select2', "

        jQuery(function($){

            $('#filter_accommodation_parent').select2({

                width: '250px',

                allowClear: true,

                placeholder: 'All Accommodations'

            });

        });

    " );

}



/**

 * Render the "Filter by Accommodation" dropdown above the japan_rooms list table.

 */

add_action( 'restrict_manage_posts', 'kv_rooms_filter_by_accommodation' );

function kv_rooms_filter_by_accommodation( $post_type ) {

    if ( $post_type !== 'japan_rooms' ) {

        return;

    }



    /* Get all published accommodations that actually have child rooms */

    $accommodations = get_posts( [

        'post_type'      => 'accommodation',

        'post_status'    => [ 'publish' ],

        'posts_per_page' => -1,

        'orderby'        => 'title',

        'order'          => 'ASC',

        'fields'         => 'ids',

    ] );



    if ( empty( $accommodations ) ) {

        return;

    }



    $selected = isset( $_GET['filter_accommodation_parent'] )

        ? intval( $_GET['filter_accommodation_parent'] )

        : 0;



    echo '<select name="filter_accommodation_parent" id="filter_accommodation_parent">';

    echo '<option value="0">' . esc_html__( 'All Accommodations', 'kv' ) . '</option>';



    foreach ( $accommodations as $acc_id ) {

        printf(

            '<option value="%d"%s>%s</option>',

            esc_attr( $acc_id ),

            selected( $selected, $acc_id, false ),

            esc_html( get_the_title( $acc_id ) )

        );

    }



    echo '</select>';

}



/**

 * Apply the accommodation filter to the WP_Query for japan_rooms.

 */

add_filter( 'parse_query', 'kv_rooms_apply_accommodation_filter' );

function kv_rooms_apply_accommodation_filter( $query ) {

    global $pagenow;



    if (

        ! is_admin() ||

        $pagenow !== 'edit.php' ||

        ! $query->is_main_query() ||

        ( $query->get( 'post_type' ) !== 'japan_rooms' )

    ) {

        return;

    }



    $acc_id = isset( $_GET['filter_accommodation_parent'] )

        ? intval( $_GET['filter_accommodation_parent'] )

        : 0;



    if ( $acc_id > 0 ) {

        $query->set( 'post_parent', $acc_id );

    }

}



add_shortcode('SingleResortFilters','singleResortFilter_func');



function singleResortFilter_func($atts){

    $atts = shortcode_atts([

        'post_id' => get_the_ID(),

    ], $atts, 'SingleResortFilters');



    $post_id = $atts['post_id'];

    ob_start();

    $property_id = get_post_meta($post_id, 'property_id', true);

    ?>

        <!-- Search Filter Form -->

        <div class="room-search">

            <!-- ✅ STEP 5: Add proper escaping for form attributes -->

            <form id="room-filter-form" method="POST" property-id="<?php echo esc_attr($property_id); ?>" acc-id="<?php echo esc_attr($post_id); ?>" room-id="<?php echo esc_attr($post_id); ?>">

                <div class="search-fields">

                    <div class="field">

                        <label for="sc-check-in">Check In</label>

                        <input type="text" id="sc-check-in" class="sv-check-in" name="checkin" autocomplete="off" placeholder="Enter your check in dates" required>

                    </div>



                    <div class="field">

                        <label for="sc-check-out">Check Out</label>

                        <input type="text" id="sc-check-out" class="sv-check-out" name="checkout" autocomplete="off" placeholder="Enter your check out dates" required>

                    </div>

                    

                    <div class="field guest-field">

                        <label for="sc-guests">Guests</label>

                        <input type="text" id="sc-guests-popup" class="sv-guests" name="guets" autocomplete="off" placeholder="Click to add guests" readonly>

                        <div class="room-filter-guests-popover" id="room-filter-guests-popover" role="dialog">

                            <div class="g-row">

                            <div><span class="g-label">Adults</span><span class="g-sub">Age 16+</span></div>

                            <div class="g-counter">

                                <button type="button" class="g-btn js-btn-adults-minus" disabled>−</button>

                                    <span class="g-val js-v-adults">2</span>

                                <button type="button" class="g-btn js-btn-adults-plus">+</button>

                            </div>

                            </div>

                            <div class="g-row">

                            <div><span class="g-label">Children</span><span class="g-sub">Ages 3–15</span></div>

                            <div class="g-counter">

                                <button type="button" class="g-btn js-btn-children-minus" disabled>−</button>

                                    <span class="g-val js-v-children">0</span>

                                <button type="button" class="g-btn js-btn-children-plus">+</button>

                            </div>

                            </div>

                            <div class="g-row">

                                <div><span class="g-label">Infants</span><span class="g-sub">Ages 0–2</span></div>

                                <div class="g-counter">

                                    <button type="button" class="g-btn js-btn-infants-minus" disabled>−</button>

                                        <span class="g-val js-v-infants">0</span>

                                    <button type="button" class="g-btn js-btn-infants-plus">+</button>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <button type="submit" class="rooms-sc-btn">Check Rates</button>

            </form>

        </div>

    <?php

    return ob_get_clean();

}



function hz_get_parent_category( $post_id){

    $terms = get_the_terms( $post_id, 'accommodation-cat' );



    if ( is_wp_error( $terms ) || empty( $terms ) ) {

        return null;

    }



    foreach ( $terms as $term ) {

        if ( $term->parent === 0 ) {

            return $term->name;

        }

    }



    return null;

}



add_shortcode('RoomsSection', 'render_rooms_section');



function render_rooms_section($atts) {



    // ✅ Define attributes - defaults to current page ID if not provided

    $atts = shortcode_atts([

        'post_id' => get_the_ID(),

    ], $atts, 'RoomsSection');



    $post_id = $atts['post_id'];

    ob_start();



    // ✅ STEP 1: Fetch fields once and cache results (avoid duplicate get_field calls)

    $property_id = get_field('property_id', $post_id);

    $property_id = !empty($property_id) ? $property_id : false;



    // ✅ STEP 2: Get room data via helper function

    $data = get_hotel_rooms($property_id);

    // ✅ STEP 3: Validate array structure before accessing keys

    if (!is_array($data)) {

        $data = ['rooms' => [], 'bedroom_types' => []];

    }

    $rooms = $data['rooms']; // Rooms data

    if(!is_array($rooms) ) {

        $rooms = [];

    }

    // pre('rooms: ' . $rooms);

    $available_bedroom_types = is_array($data['bedroom_types']) ? $data['bedroom_types'] : []; // Available bedroom types



    // ✅ STEP 4: Fetch and validate roomboss field once

    $is_roomboss = !empty(get_field('is_roomboss', $post_id)) ? true : false;

    ?>

    <section class="room-listing">

        <div class="container">



            <div class="main-title">

                <h2>Rooms at <?php echo get_the_title($post_id); ?></h2>

            </div>

            <?php 

            if ( get_post_meta( $post_id, 'is_price_excluded', true ) !== '1' ) {

                echo do_shortcode('[SingleResortFilters post_id="' . $post_id . '"]'); 

            }

            ?>

            <div class="rooms-wrap">

                <!-- Bedroom Tabs -->

                <div class="bedroom-tabs">



                    <button type="button" class="bedroom-tab active" data-bedroom="all">All Bedrooms</button>

                    <?php foreach ($available_bedroom_types as $bedroom_type) : ?>

                        <button type="button" class="btn bedroom-tab" data-bedroom="<?php echo $bedroom_type; ?>">

                            <?php echo $bedroom_type; ?> Bedroom<?php echo $bedroom_type > 1 ? 's' : ''; ?>

                        </button>

                    <?php endforeach; ?>

                </div>

                <?php if ($is_roomboss): ?>

                    <div class="available_units">

                        <span class="units_avl">

                            <?php echo count( $rooms ) ?>    

                        </span>

                        unit type<?php echo count( $rooms ) > 1 ? 's' : ''; ?> available out of 

                        <span class="total_units">

                            <?php echo count( $rooms ) ?> 

                        </span>

                    </div>

                <?php

                else: ?>

                    <div class="available_units">

                        <!-- This property has <span class="total_units"><?php //echo count( $rooms ) ?></span> unit type<?php //echo count( $rooms ) > 1 ? 's' : ''; ?> - enquire for availability. -->

                        The results below are not based on live availability. Please submit a booking request and, if your selected dates can be confirmed, our team will place the property on hold and send you a booking link.

                    </div>

                <?php endif ?>



                <?php $abc = false; 

                    //if ( $abc ): ?>

                    

                    <!-- Room Listings -->

                    <div class="room-list">

                        <div class="row" id="room-results">

                            <?php

                            if (!empty($rooms)) :

                                foreach ($rooms as $room) :

                                    get_template_part('partials/room-box', null, ['room' => $room, 'rb' => $is_roomboss, 'property_id' => $property_id]);

                                endforeach;

                            else :

                                echo '<p>No rooms available at the moment.</p>';

                            endif;

                            ?>

                        </div>

                    </div>

                <?php //endif ?>

                <div class="booking-wrap" style="display:none; background-color: #00111e;">

                    <!-- <button class="back-to-rooms btn-outline">← Back</button> -->

                    <div class="booking-layout">



                        <!-- LEFT: Selected room + rate plans -->

                        <div class="booking-left">

                            <div class="booking-room-card">

                                <h3 class="booking-room-title">Deluxe Studio</h3>

                                <p class="booking-dates">Dec 13 – 20, 2025</p>



                                <div class="booking-meta">

                                    <span>👤 2</span>

                                    <span>🛏 1</span>

                                    <span>🛁 1</span>

                                </div>

                            </div>



                            <!-- RATE PLANS -->

                            <div class="rate_plan_box selected">

                                <h4>Summer Stay</h4>

                                <p>Free cancellation up to 3 days before check-in</p>



                                <div class="price">

                                    <span class="old">¥235,000</span>

                                    <strong>¥211,500</strong>

                                </div>



                                <button class="btn select-rate">Selected</button>

                            </div>

                        </div>



                        <!-- RIGHT: BOOKING SUMMARY -->

                        <div class="booking-right">

                            <h3>Booking Summary</h3>



                            <div class="summary-item">

                                <span>Deluxe Studio</span>

                                <span>¥235,000</span>

                            </div>



                            <div class="summary-dates">

                                <div><b>Check in</b> 13/12/2025</div>

                                <div><b>Check out</b> 20/12/2025</div>

                            </div>



                            <div class="summary-total">

                                <strong>Total</strong>

                                <strong>¥235,000</strong>

                            </div>



                            <button class="btn proceed-btn">Proceed</button>

                        </div>



                    </div>

                </div>

            </div>



            <div id="room-modal" class="room-modal" style="display: none;">

                <div class="room-modal-content">

                    <button class="room-modal-close">&times;</button>

                    <div id="room-modal-body"></div>

                </div>

            </div>



        </div>

    </section>



<?php

    return ob_get_clean();

}



function is_accommodation(){



    global $wp;

    // Get current clean path

    $current_path = home_url( $wp->request ); 



    if ( strpos( strtolower( $current_path ), 'accommodation') ) {

        return true;

    }

    

    return false;

}



function is_deals_page(){



    if (strpos($_SERVER['REQUEST_URI'], '/deals/') !== false){

        return true;

    }

    

    return false;

}

function new_hz_convert_date_format( string $date, string $format ) {

    try {

        // ✅ STEP 1: Validate input parameters

        $date = trim($date);

        $format = trim($format);



        if (empty($date) || empty($format)) {

            return false;

        }



        // ✅ STEP 2: Clean commas that break PHP's natural language engine

        $clean_date = str_replace(',', '', $date);



        // ✅ STEP 3: Auto-detect format using WordPress site timezone

        $dateObject = new DateTime($clean_date, wp_timezone());



        // ✅ STEP 4: Format and return result

        return $dateObject->format($format);



    } catch (Exception $e) {

        // ❌ Catch invalid date strings or parsing errors safely

        error_log('Error converting date format: ' . $e->getMessage());

        return false;

    }

}



add_action('wp_ajax_load_property_terms', 'load_property_terms_func');

add_action('wp_ajax_nopriv_load_property_terms', 'load_property_terms_func');



function load_property_terms_func(){

    

    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;

    $acc_id = get_post_id_by_typeId( $property_id, 'accommodation');



    $terms = get_field('terms_conditions', $acc_id);



    if( $terms ){

        wp_send_json_success( $terms );

    }

    else{

        wp_send_json_error('No terms found for property');

    }

}



/**

 * Returns the global whitelist of allowed accommodation areas.

 *

 * @return array Associative array where keys are resort names and values are arrays of allowed area names.

 */

function hz_get_global_accommodation_whitelist() {

    return [

        'Niseko' => ['Hirafu Village', 'Annupuri', 'Niseko Village', 'Hanazono', 'Kabayama', 'Izumikyo'],

        'Hakuba' => ['Happo One Village', 'Wadano no Mori', 'Echoland', 'Misorano', 'Hakuba 47', 'Hakuba Station', 'Goryu', 'Iwatake', 'Tsugaike', 'Norikura', 'Cortina', 'Ochikura'],

        'Furano' => ['Kitanomine Zone', 'Furano Zone'],

        'Rusutsu' => []

    ];

}



/**

 * Get allowed area names for an accommodation post based on resort categories.

 *

 * @param int $post_id The post ID.

 * @return array List of area names starting with the parent resort name.

 */

function hz_get_allowed_accommodation_areas($post_id)

{

    $display_categories = [];

    $allowed_areas = hz_get_global_accommodation_whitelist();



    $categories = get_the_terms($post_id, 'accommodation-cat');



    if (!empty($categories) && !is_wp_error($categories)) {

        $parent_term_id = 0;

        $parent_term_name = '';

        $child_term_names = [];



        // Find the parent term (parent ID is 0)

        foreach ($categories as $category) {

            if (intval($category->parent) === 0) {

                $parent_term_name = str_replace(' Accommodation', '', sanitize_text_field($category->name));

                $parent_term_id = $category->term_id;

                break;

            }

        }



        // Collect child terms if a parent was found and check against allowed list

        if ($parent_term_id > 0 && isset($allowed_areas[$parent_term_name])) {

            foreach ($categories as $category) {

                if (intval($category->parent) === $parent_term_id) {

                    $child_term_name = sanitize_text_field($category->name);

                    if (in_array($child_term_name, $allowed_areas[$parent_term_name])) {

                        $child_term_names[] = $child_term_name;

                    }

                }

            }

        }



        if (!empty($parent_term_name)) {

            $display_categories[] = $parent_term_name;

        }

        if (!empty($child_term_names)) {

            $display_categories = array_merge($display_categories, $child_term_names);

        }

    }



    return $display_categories;

}



/**

 * Step 1: Render the custom checkbox inside the Post Attributes / Order metabox panel.

 * This hook targets the bottom of the attributes panel for the accommodation post type.

 */

function kv_add_exclude_prices_to_order_metabox( $post ) {

    $is_acc         = ( 'accommodation' === $post->post_type );

    $is_resort_page = false;



    // Check if it's a page and its slug is 'accommodation' or parent slug is 'accommodation'

    if ( 'page' === $post->post_type ) {

        if ( 'accommodation' === $post->post_name ) {

            $is_resort_page = true;

        } elseif ( $post->post_parent > 0 ) {

            $parent = get_post( $post->post_parent );

            if ( $parent && 'accommodation' === $parent->post_name ) {

                $is_resort_page = true;

            }

        }

    }



    if ( ! $is_acc && ! $is_resort_page ) {

        return;

    }



    // Secure the form with a security nonce check

    wp_nonce_field( 'kv_exclude_prices_nonce', 'kv_exclude_prices_nonce_field' );



    ?>

    

    <?php if ( $is_acc ) : 

        // Only show "Exclude prices" for the main accommodation post type

        $is_checked = get_post_meta( $post->ID, 'is_price_excluded', true );

    ?>

    <div class="misc-pub-section custom-checkbox-attribute" style="padding: 10px 0 0 0;">

        <label for="is_price_excluded">

            Exclude prices

        </label>

        <input type="checkbox" 

        name="is_price_excluded" 

        id="is_price_excluded" 

        value="1" 

        <?php checked( $is_checked, '1' ); ?> />

    </div>

    <?php endif; ?>



    <?php if ( $is_resort_page ) : 

        $num_posts = get_post_meta( $post->ID, 'kv_number_of_posts', true );

        ?>

        <div class="misc-pub-section custom-number-attribute" style="padding: 10px 0 0 0;">

            <label for="kv_number_of_posts">

                Number of posts

            </label>

            <input type="number" 

            name="kv_number_of_posts" 

            id="kv_number_of_posts" 

            value="<?php echo esc_attr( $num_posts ); ?>" 

            style="width: 60px;" />

        </div>

    <?php endif; ?>

    

    <?php

}

add_action( 'page_attributes_misc_attributes', 'kv_add_exclude_prices_to_order_metabox' );



/**

 * Step 2: Save or clear the checkbox state when the post is updated.

 */

function kv_save_exclude_prices_meta( $post_id ) {

    // Verify nonce security

    if ( ! isset( $_POST['kv_exclude_prices_nonce_field'] ) || 

         ! wp_verify_nonce( $_POST['kv_exclude_prices_nonce_field'], 'kv_exclude_prices_nonce' ) ) {

        return;

    }



    // Skip auto-saves

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {

        return;

    }



    // Check user permission

    if ( ! current_user_can( 'edit_post', $post_id ) ) {

        return;

    }



    // Checkboxes do not pass data via $_POST if they are unchecked

    if ( isset( $_POST['is_price_excluded'] ) ) {

        update_post_meta( $post_id, 'is_price_excluded', '1' );

    } else {

        delete_post_meta( $post_id, 'is_price_excluded' );

    }



    // Save Number of posts

    if ( isset( $_POST['kv_number_of_posts'] ) ) {

        update_post_meta( $post_id, 'kv_number_of_posts', absint( $_POST['kv_number_of_posts'] ) );

    }

}

add_action( 'save_post_accommodation', 'kv_save_exclude_prices_meta' );

add_action( 'save_post_page', 'kv_save_exclude_prices_meta' );



function my_acf_populate_post_types( $field ) {

    // Clear any existing default choices

    $field['choices'] = array();

    

    // Set arguments to get public post types

    $args = array(

        'public' => true,

    );

    

    // Retrieve all post types matching the arguments

    $post_types = get_post_types( $args, 'objects' );

    

    // Loop through each post type and populate the select options

    foreach ( $post_types as $post_type ) {

        // Exclude attachment post type if it is not needed

        if ( $post_type->name === 'attachment' ) {

            continue;

        }

        

        // Use the post type slug as key and label as the value

        $field['choices'][ $post_type->name ] = $post_type->label;

    }

    

    return $field;

}

// Target the exact subfield name inside your repeater

add_filter( 'acf/load_field/name=post_type', 'my_acf_populate_post_types' );



/**

 * Force Yoast breadcrumb schema on accommodation detail pages.

 */

add_filter('wpseo_schema_needs_breadcrumb', 'jse_force_yoast_breadcrumb_schema_for_accommodation');



function jse_force_yoast_breadcrumb_schema_for_accommodation($is_needed) {

    if (jse_is_accommodation_detail_page()) {

        return true;

    }



    return $is_needed;

}





/**

 * Fix Yoast breadcrumb links for accommodation CPT.

 */

add_filter('wpseo_breadcrumb_links', 'jse_fix_accommodation_yoast_breadcrumb_links', 20);



function jse_fix_accommodation_yoast_breadcrumb_links($links) {



    if (!jse_is_accommodation_detail_page() || is_page( 'accommodation' )) {

        $parent_page = wp_get_post_parent_id( get_the_ID() ) ?? [];

        $parent_page_name = get_the_title( $parent_page ) ?? '';



        if ( $parent_page > 0 ){

            $last_key = array_key_last($links);

            $text = $parent_page_name .' '.$links[ $last_key ]['text'];

            $links[ $last_key ]['text'] = $text;

        }



        return $links;

    }



    $post_id = get_queried_object_id();



    if (!$post_id) {

        return $links;

    }



    $permalink = trailingslashit(get_permalink($post_id));



    /**

     * Example URL:

     * /niseko/accommodation/aya-niseko/

     */

    $path  = trim(parse_url($permalink, PHP_URL_PATH), '/');

    $parts = array_values(array_filter(explode('/', $path)));

    $resort_slug  = $parts[0] ?? '';

    $section_slug = $parts[1] ?? 'accommodation';

    

    $resort_name = $resort_slug

    ? ucwords(str_replace('-', ' ', $resort_slug))

    : '';

    

    $section_name = ucwords(str_replace('-', ' ', $section_slug));

    

    $new_links = [];



    $new_links[] = [

        'url'  => trailingslashit(home_url('/')),

        'text' => 'Home',

    ];



    if( in_array( $resort_slug, ['tokyo', 'kiroro'] ) ) {

        $new_links[] = [

            'url'  => trailingslashit(home_url('/accommodation/')),

            'text' => 'Accommodation',

        ];

    }

    else{



        if ( $resort_name ) {

            $new_links[] = [

                'url'  => trailingslashit(home_url('/' . $resort_slug . '/')),

                'text' => $resort_name,

            ];

        }

    

        if ($resort_slug && $section_slug ) {

    

            if( $section_name === 'Accommodation'){

                $section_name = $resort_name .' '. $section_name;        

            }

            $new_links[] = [

                'url'  => trailingslashit(home_url('/' . $resort_slug . '/' . $section_slug . '/')),

                'text' => $section_name,

            ];

        }

    }



    $new_links[] = [

        'url'  => $permalink,

        'text' => wp_strip_all_tags(get_the_title($post_id)),

    ];

    

    return $new_links;

}





/**

 * Helper: accommodation detail page check.

 */

function jse_is_accommodation_detail_page() {

    if (!is_singular()) {

        return false;

    }



    $post_id = get_queried_object_id();



    if (!$post_id) {

        return false;

    }



    $permalink = get_permalink($post_id);



    if (!$permalink) {

        return false;

    }



    // If your CPT slug is exactly accommodation, this will catch it.

    if (is_singular('accommodation')) {

        return true;

    }



    // Fallback for URL-based accommodation pages.

    return strpos($permalink, '/accommodation/') !== false;

}



// add_filter('yoast_seo_development_mode', '__return_true');





/* ──────────────────────────────────────────────────────────────

 * Admin: Filter accommodation list by Permission Type (acc_booking_permission)

 * ────────────────────────────────────────────────────────────── */



/**

 * Render the "Permission Type" filter dropdown above the accommodation list table.

 * Options are pulled dynamically from distinct acc_booking_permission meta values

 * already stored on accommodation posts, so the dropdown stays in sync with the

 * data — no hard-coded list to maintain.

 */

add_action( 'restrict_manage_posts', 'kv_filter_accommodation_by_permission_type' );

function kv_filter_accommodation_by_permission_type( $post_type ) {

    if ( $post_type !== 'accommodation' ) {

        return;

    }



    // Pull distinct, non-empty permission values from postmeta.

    $permission_values = kv_get_distinct_meta_values( 'acc_booking_permission', 'accommodation' );



    if ( empty( $permission_values ) ) {

        return;

    }



    $selected = isset( $_GET['filter_permission_type'] )

        ? sanitize_text_field( wp_unslash( $_GET['filter_permission_type'] ) )

        : '';



    echo '<select name="filter_permission_type" id="filter_permission_type">';

    echo '<option value="">' . esc_html__( 'All Permission Types', 'kv_theme' ) . '</option>';



    foreach ( $permission_values as $value ) {

        printf(

            '<option value="%s"%s>%s</option>',

            esc_attr( $value ),

            selected( $selected, $value, false ),

            esc_html( $value )

        );

    }



    echo '</select>';

}



/**

 * Apply the Permission Type filter to the accommodation WP_Query.

 */

add_filter( 'parse_query', 'kv_apply_accommodation_permission_filter' );

function kv_apply_accommodation_permission_filter( $query ) {

    global $pagenow;



    if (

        ! is_admin() ||

        $pagenow !== 'edit.php' ||

        ! $query->is_main_query() ||

        $query->get( 'post_type' ) !== 'accommodation'

    ) {

        return;

    }



    $permission = isset( $_GET['filter_permission_type'] )

        ? sanitize_text_field( wp_unslash( $_GET['filter_permission_type'] ) )

        : '';



    if ( $permission === '' ) {

        return;

    }



    $meta_query = (array) $query->get( 'meta_query' );

    $meta_query[] = [

        'key'     => 'acc_booking_permission',

        'value'   => $permission,

        'compare' => '=',

    ];

    $query->set( 'meta_query', $meta_query );

}



/**

 * Helper: return distinct, non-empty meta values for a given meta_key + post_type,

 * ordered alphabetically. Used to populate admin filter dropdowns dynamically.

 *

 * @param string $meta_key

 * @param string $post_type

 * @return string[]

 */

function kv_get_distinct_meta_values( $meta_key, $post_type = 'accommodation' ) {

    global $wpdb;



    $meta_key   = sanitize_key( $meta_key );

    $post_type  = sanitize_key( $post_type );

    $cache_key  = 'kv_distinct_meta_' . $post_type . '_' . $meta_key;

    $cache      = wp_cache_get( $cache_key, 'kv_admin_filters' );



    if ( is_array( $cache ) ) {

        return $cache;

    }



    $rows = $wpdb->get_col( $wpdb->prepare(

        "SELECT DISTINCT pm.meta_value

         FROM {$wpdb->postmeta} pm

         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id

         WHERE pm.meta_key = %s

           AND pm.meta_value <> ''

           AND p.post_type = %s

         ORDER BY pm.meta_value ASC",

        $meta_key,

        $post_type

    ) );



    $values = array_values( array_filter( array_map( 'strval', (array) $rows ) ) );



    wp_cache_set( $cache_key, $values, 'kv_admin_filters', MINUTE_IN_SECONDS * 5 );



    return $values;

}

function kv_update_accommodation_menu_order() {

    $accommodation_list = [

        'Setsu Niseko', 'Starry Residence Suite', 'AYA Niseko', 'The Ridge Hotel and Apartments', 'The Maples Niseko', 'Landmark View', 'The Vale Niseko', 'The Vale Rusutsu', 'ROKA', 'Hakuba Grand Apartments', 'Niseko Kyo', 'Chatrium Niseko', 'La Vigne', 'Summit Views Hakuba', 'Nikko Style Hanazono', 'Koharu Resort', 'Amber Echoland', 'Gateway Hotel', 'Amber Mizuho', 'Mountain Side Hakuba', 'Wadano Woods', 'Always Niseko', 'Intuition', 'Muwa Niseko', 'Hakuba Amber Resort', 'Gondola Chalets', 'Mizuho Chalets', 'Kizuna', 'The Setsumon', 'Happo View Apartments', 'The Happo', 'Sansui Niseko', 'Alpen Ridge', 'evo Hotel', 'Phoenix Chalets', 'Grand Phenix Hakuba', 'Hakuba Hifumi', 'Winterstorm Apartments', 'M Hotel', 'Akazora', 'Yukimi', 'The Castle', 'Mountainside Palace', 'Slopeside Chalet', 'Fenix Furano','Greystone', 'Hakuba Momiji', 'Hatsuyuki Townhouse', 'Country Resort', 'The Orchards Niseko', 'Shinka Niseko', 'Amo54', 'Forest Estate', 'Ora D\'oro', 'Happo Slopeside Apartments', 'Hangetsu', 'Soseki', 'Chalet Ivy', 'Shinsetsu Apartments', 'Wadano Onsen Hotel','Alpine Azumi Apartments', 'Kamakura Apartments', 'Hakuba Mountain Apartments', 'Gakuto Villas', 'Starry Denim', 'Hida Peaks Chalet', 'Villa Rochalie', 'Starry 8', 'Muse Niseko', 'Kaku Place', 'Hotel Villa Hakuba', 'Hakuba Powder Mountain', 'Credence Chalets', 'Roku', 'Mitsuki Chalet', 'Rusutsu Resort Hotel & Convention', 'LOFT Niseko', 'Hotel Taigakukan', 'Hotel La Neige', 'Snow Crystal', 'Terrazze', 'TRIPLE EIGHT 888', 'First Tracks', 'Yuki Yama', 'Solar Chalets', 'Phoenix Hotel', 'Phoenix Cocoon Chalet', 'Origami Twins', 'Kiseki', 'Kyla', 'Powdersuites', 'Sunnsnow Kallin Cottage', 'Kira Kira', 'SnowDog Village', 'Meikeikyo Hanazono', 'Fenix West', 'Kitsune Cottages', 'SUKH', 'Wadano Forest Hotel', 'Genji & Musashi', 'Villa Rusutsu', 'Firefly Apartments', 'Villa Lily', 'Happo View Chalet', 'Hupni', 'Momo Chalet', 'Sunnsnow Tall House', 'Escarpment House', 'The Freshwater', 'Big Valley', 'Latitude 42', 'Sanzan', 'Chalet Opus', 'Aerial Chalet', 'Yasuragi Niseko', 'Chalet Hérisson', 'MK House', 'Vader House', 'Kozue', 'Hakuba MAHOROBA', 'Happo Apartments', 'Zai-On', 'Shizuku Villas', 'La Chalet', 'Hotel La Neige', 'Yotsuba', 'Yuzuki', 'Chalet Infinity', 'Fubuki', 'Bluebird Apartments', 'Koa Niseko', 'Green Leaf Hotel', 'Le Sauna Villa', 'Yotei Dream One', 'Kisetsu', 'Hinzan', 'Ginsetsu', 'Grey Wolf', 'Aurora Chalet', 'Cherrywoods Place', 'Blue Heron', 'Old Man Creek', 'Powderhouse', 'Hibari', 'Aspect Niseko', 'Rusutsu Chalet', 'Marillen Hotel', 'Creekside', 'Akatsuki Furano', 'Snow Fox'

    ];

    // Prepare a lowercase version for case-insensitive searching

    $normalized_list = array_map('strtolower', $accommodation_list);

    $args = [

        'post_type'      => 'accommodation',

        'posts_per_page' => -1,

        'post_status'    => 'any',

    ];

    $posts = get_posts($args);

    if (empty($posts)) {

        return;

    }
    $total_posts = count($posts);

    foreach ($posts as $post) {

        $title = strtolower(trim($post->post_title));

        $found_index = array_search($title, $normalized_list);

        // If title is in the list, use its position (1-based index).

        // Otherwise, set it to the total count to push it to the end.

        $target_order = ($found_index !== false) ? ($found_index + 1) : $total_posts;

        // Only update if the order is actually different to save resources

        if ($post->menu_order != $target_order) {

            wp_update_post([

                'ID'         => $post->ID,

                'menu_order' => $target_order,

            ]);

        }

    }
    
}

function get_enquiry_form_html( $form_shortcode = '[gravityform id="1" title="true" ajax="true"]' ) {
    return '
        <div class="mob_quote_form1">
            <div class="mob_quote_inner">
                '. do_shortcode( $form_shortcode ) .'
            </div>
        </div>';
}

function get_acc_enquiry_form(){
    return '<div class="acc_enquiry_form">
        <div class="acc_enquiry_head">
            <h3>Skip the searching</h3>
            <p class="acc_enquiry_desc">Tell us a little more and our local experts will recommend the best available options.</p>
        </div>
        <div class="acc_enquiry_form_slot">
            ' . get_enquiry_form_html('[gravityform id="1" title="false" ajax="true"]') . '
        </div>
    </div>';
}