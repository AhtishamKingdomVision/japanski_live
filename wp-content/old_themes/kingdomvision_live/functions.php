<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$debug = $_GET['debug'] ?? false;
if ($debug) {
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
}

define('THEME_PATH', get_template_directory());
define('THEME_URL', get_template_directory_uri());

$template_directory = get_template_directory();

require_once $template_directory . '/inc/listing-shortcode.php';
require_once $template_directory . '/inc/listing-shortcode_1.php';

function theme_files() {
    // Theme Files
    wp_register_style('theme-style', get_stylesheet_uri(), false, null);
    wp_enqueue_style('theme-style');

    wp_register_style('theme-styler', THEME_URL . '/css/responsive.css', false, null);
    wp_enqueue_style('theme-styler');

    wp_register_style('font-css', THEME_URL . '/css/fonts.css', false, null);
    wp_enqueue_style('font-css');

    wp_register_style('jquery-ui', '//code.jquery.com/ui/1.11.3/themes/smoothness/jquery-ui.css', false, '1.11.3');

    wp_register_style('css-select2', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', false);

    // BG lazy load
    wp_register_script('lazy-load-bg-image', get_stylesheet_directory_uri() . '/js/lazy-load-bg-image.js', array('jquery'), false, true);
    wp_enqueue_script('lazy-load-bg-image');

    // slick-carousel
    wp_register_style('slick-carousel', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css', false);
    wp_enqueue_style('slick-carousel');
    wp_register_script('slick-carousel', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), true);
    wp_enqueue_script('slick-carousel');

    // Fancybox
    wp_register_style('fancybox', THEME_URL . '/fancybox/jquery.fancybox.min.css', false);
    wp_enqueue_style('fancybox');
    wp_register_script('fancybox', THEME_URL . '/fancybox/jquery.fancybox.min.js', array('jquery', 'kv-script',), true);
    wp_enqueue_script('fancybox');

    // Date Dropper
    wp_register_script('datedropper_js', THEME_URL . '/js/datedropper.min.js', array('jquery', 'kv-script',), '1.0', true);
    wp_enqueue_script('datedropper_js');

    wp_register_script('select2_js', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', true);

    wp_register_script('kv-script', THEME_URL . '/kv-script.js?v=' . filemtime(__DIR__ . '/kv-script.js'), array('jquery'), false, true);
    wp_enqueue_script('kv-script');

    $weekStartDate = get_field('check_start_date', 'option');
    $weekEndDate = get_field('check_end_date', 'option');
    $minDaysOption = get_field('check_min_days_option', 'option');
    $dateDropperContent = get_field('date_dropper_content', 'option');
    $minDays = get_field('check_min_days', 'option');
    wp_localize_script('kv-script', 'kv_object',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'check_start_date' => $weekStartDate,
            'check_end_date' => $weekEndDate,
            'check_min_days_option' => $minDaysOption,
            'check_min_days' => $minDays,
            'date_dropper_content' => !empty($dateDropperContent) ? $dateDropperContent : false,
        )
    );

    if (!is_front_page()) {
        wp_enqueue_script('select2_js');
        wp_enqueue_style('css-select2');
        wp_enqueue_style('jquery-ui');
    }
}

add_action('wp_enqueue_scripts', 'theme_files');

//add_action('wp_head', 'cf_preload_fonts', 0);
function cf_preload_fonts() { ?>
    <link rel="preload" as="font"
          href="//use.fontawesome.com/releases/v5.8.1/webfonts/fa-solid-900.woff2" crossorigin>
    <?php
}

// Enable Classic Editor
add_filter('use_block_editor_for_post', '__return_false', 10);

// Theme Options
if (function_exists('acf_add_options_page')) {
    acf_add_options_page(array(
        'page_title' => 'Theme Options',
        'menu_title' => 'Theme Options',
        'menu_slug' => 'theme-pptions',
        'capability' => 'edit_posts',
        'redirect' => false
    ));
}

// Register Sidebar
add_action('widgets_init', 'kv_widgets_init');
function kv_widgets_init()
{
    $sidebar_attr = array(
        'name' => '',
        'before_widget' => '<li id="%1$s" class="widget %2$s">',
        'after_widget' => '</li>',
        'before_title' => '<h2 class="widgettitle">',
        'after_title' => '</h2>',
    );
    $sidebar_id = 0;
    $gdl_sidebar = array("Blog");
    foreach ($gdl_sidebar as $sidebar_name) {
        $sidebar_attr['name'] = $sidebar_name;
        $sidebar_attr['id'] = 'custom-sidebar' . $sidebar_id++;
        register_sidebar($sidebar_attr);
    }
}

// Register Navigation
function register_menu()
{
    register_nav_menu('main-menu', __('Main Menu'));
    register_nav_menu('second-menu', __('Second Menu'));
    register_nav_menu('mobile-menu', __('Mobile Menu'));
}

add_action('init', 'register_menu');

// Image Crop
function codex_post_size_crop()
{
    add_image_size("packages_image", 300, 200, true);
}

add_action("init", "codex_post_size_crop");

// Featured Image Function
add_theme_support('post-thumbnails');

// remove wp version param from any enqueued scripts
function vc_remove_wp_ver_css_js($src)
{
    if (strpos($src, 'ver='))
        $src = remove_query_arg('ver', $src);
    return $src;
}

add_filter('style_loader_src', 'vc_remove_wp_ver_css_js', 9999);
add_filter('script_loader_src', 'vc_remove_wp_ver_css_js', 9999);


// Disables the block editor from managing widgets in the Gutenberg plugin.
add_filter('gutenberg_use_widgets_block_editor', '__return_false');

// Disables the block editor from managing widgets.
add_filter('use_widgets_block_editor', '__return_false');

// Prevent Form Submission for bots
// add_filter('gform_validation', 'custom_validation');
function custom_validation($validation_result)
{
    $form = $validation_result['form'];
    //finding Field with ID of 1 and marking it as failed validation
    foreach ($form['fields'] as &$field) {
        //NOTE: replace 1 with the field you would like to validate
        if ($field->cssClass == 'no-bots') {
            $zip_val = rgpost("input_" . $field->id);
            if (!empty($zip_val)) {
                $validation_result['is_valid'] = false;
                $field->failed_validation = true;
                $field->validation_message = 'This field is invalid!';
                break;
            }
        }
    }

    //Assign modified $form object back to the validation result
    $validation_result['form'] = $form;
    return $validation_result;
}

// SVG Image Code
function my_theme_custom_upload_mimes($existing_mimes)
{
    $existing_mimes['svg'] = 'image/svg+xml';
// Return the array back to the function with our added mime type.
    return $existing_mimes;
}

add_filter('mime_types', 'my_theme_custom_upload_mimes');

function my_custom_mime_types($mimes)
{

// New allowed mime types.
    $mimes['svg'] = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    $mimes['doc'] = 'application/msword';

// Optional. Remove a mime type.
    unset($mimes['exe']);

    return $mimes;
}

add_filter('upload_mimes', 'my_custom_mime_types');

// Review Post type
add_action('init', 'reviews_post_type');
function reviews_post_type()
{
    register_post_type('reviews', array(
        'labels' => array(
            'name' => __('Reviews'),
            'singular_name' => __('Reviews')
        ),
        'supports' => array(
            'title',
            'editor',
            //'thumbnail'
        ),
        'public' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'query_var' => true,
        'menu_icon' => 'dashicons-testimonial'
    ));
    register_taxonomy('reviews_catgory', ['reviews'], [
        'label' => __('Reviews Category', 'txtdomain'),
        'hierarchical' => true,
        'rewrite' => ['slug' => 'reviews-catgory'],
        'show_admin_column' => true,
        'show_in_rest' => true,
        'labels' => [
            'singular_name' => 'Review Category',
            'all_items' => 'All Category',
            'edit_item' => 'Edit Category',
            'view_item' => 'View Category',
            'update_item' => 'Update Category',
            'add_new_item' => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'search_items' => 'Search Categories',
            'parent_item' => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
            'not_found' => 'No Category found',
        ]
    ]);
    register_taxonomy_for_object_type('reviews_catgory', 'reviews');

    register_post_type('japan_booking', array(
        'labels' => array(
            'name' => __('Japan Bookings'),
            'singular_name' => __('Japan Booking'),
        ),
        'supports' => array(
            'title',
            'editor',
            'author',
            'thumbnail',
            'revisions',
        ),
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-calendar-alt',
        'taxonomies' => array('categories'),
    ));

}

// Review Shortcode
add_shortcode('reviews', 'codex_reviews');
function codex_reviews($atts = [])
{
    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);

    // override default attributes with user attributes
    $_atts = shortcode_atts(
        array(
            'category' => '',
            'perpage' => '20'
        ), $atts
    );
    ob_start();
    ?>
    <div class="reviews-carousel">
        <?php
        $args = array('post_type' => 'reviews', 'posts_per_page' => $_atts['perpage']);
        if (!empty($_atts['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'reviews_catgory',
                    'field' => 'slug',
                    'terms' => $_atts['category'],
                )
            );
        }
        $loop = new WP_Query($args);
        while ($loop->have_posts()) : $loop->the_post();
            $date = get_field('posted_date', get_the_ID());
            $c_title = get_field('custom_title', get_the_ID());
            ?>
            <div class="item">
                <div class="title home_rev_title">
                    <?php
                        if($c_title){
                            echo $c_title;
                        } else {
                            the_title();
                        }
                    ?>
                    &nbsp;&nbsp;
                    <?php
                    $rating = get_post_meta(get_the_ID(), 'review_rating', 1) ?: 5;
                    for ($i = 1; $i <= $rating; $i++) {
                        echo '<i class="fa fa-star"></i>';
                    }
                    ?>
                </div>
                <div class="excerpt more"><?php echo get_the_content(); ?></div>
            </div>
        <?php endwhile; ?>
    </div>
    <?php
    wp_reset_postdata();
    return '' . ob_get_clean();
}

// Review Shortcode
add_shortcode('product_reviews', 'codex_product_reviews');
function codex_product_reviews($atts = [])
{
    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);

    // override default attributes with user attributes
    $_atts = shortcode_atts(
        array(
            'sku' => '',
        ), $atts
    );
    $sku = $_atts['sku'];
    if (empty($sku)) {
        return 'proudct sku not found';
    }
    ob_start();
    ?>
    <div class="reviews-carousel">
        <?php
        $store = 'japanskiexperience.com1';

        $apiUrl = 'https://api.reviews.io/product/review?store=' . $store . '&sku=' . $sku;
        $response = wp_remote_get($apiUrl);
        $responseBody = wp_remote_retrieve_body($response);
        $reviews = json_decode($responseBody);
        if (!empty($reviews->reviews->data) && !is_wp_error($reviews)) {
            $data = $reviews->reviews->data;
            foreach ($data as $d) {
                $reviewDate = date("F Y", strtotime($d->date_created));
                $postedDate = 'Posted ' . $reviewDate;
                ?>
                <div class="item">
                    <div class="title rev_title"><?= str_replace( "&quot;", "", html_entity_decode( $d->reviewer->first_name . ' ' . $d->reviewer->last_name ) ); ?></div>
                    <div class="excerpt more"><?php echo $d->review; ?></div>
                </div>
            <?php }
        } ?>
    </div>
    <?php
    wp_reset_postdata();
    return '' . ob_get_clean();
}

// ADD Image in menu Items
function kv_menu_image_icon($item_id, $item)
{
    $menu_image = get_post_meta($item_id, '_menu_image_icon', true);
    $menu_image_size = get_post_meta($item_id, '_menu_image_icon_size', true);
    ?>
    <div style="clear: both;">
        <label for="menu-image-icon-<?php echo $item_id; ?>" style="font-size: 13px; color: #646970;">
            <?php _e("Image Icon Url", 'menu-image-icon'); ?>
        </label>
        <input type="hidden" class="nav-menu-id" value="<?php echo $item_id; ?>"/>
        <div class="logged-input-holder">
            <input type="text" name="menu_image_icon[<?php echo $item_id; ?>]"
                   id="menu_image_icon-<?php echo $item_id; ?>" value="<?php echo esc_attr($menu_image); ?>"
                   style="width: 100%;">
        </div>
    </div>

    <div style="clear: both;">
        <label for="menu_image_icon_size-<?php echo $item_id; ?>" style="font-size: 13px; color: #646970;">
            <?php _e("Image Icon Size", 'menu_image_icon_size'); ?>
        </label>
        <input type="hidden" class="nav-menu-id" value="<?php echo $item_id; ?>"/>
        <div class="logged-input-holder">
            <input type="text" name="menu_image_icon_size[<?php echo $item_id; ?>]"
                   id="menu_image_icon_size-<?php echo $item_id; ?>" value="<?php echo esc_attr($menu_image_size); ?>"
                   style="width: 100%;">
        </div>
    </div>
    <?php
}

add_action('wp_nav_menu_item_custom_fields', 'kv_menu_image_icon', 10, 2);

// Save Image Field in Data Base
function kv_save_menu_item_meta($menu_id, $menu_item_db_id)
{
    if (isset($_POST['menu_image_icon'][$menu_item_db_id])) {
        $sanitized_data = sanitize_text_field($_POST['menu_image_icon'][$menu_item_db_id]);
        update_post_meta($menu_item_db_id, '_menu_image_icon', $sanitized_data);
    } else {
        delete_post_meta($menu_item_db_id, '_menu_image_icon');
    }

    if (isset($_POST['menu_image_icon_size'][$menu_item_db_id])) {
        $sanitized_data = sanitize_text_field($_POST['menu_image_icon_size'][$menu_item_db_id]);
        update_post_meta($menu_item_db_id, '_menu_image_icon_size', $sanitized_data);
    } else {
        delete_post_meta($menu_item_db_id, '_menu_image_icon_size');
    }
}

add_action('wp_update_nav_menu_item', 'kv_save_menu_item_meta', 10, 2);

// Image Output in menu Items
function kv_image_menu_output($title, $item)
{
    if (is_object($item) && isset($item->ID)) {
        $menu_img = get_post_meta($item->ID, '_menu_image_icon', true);
        $menu_size = get_post_meta($item->ID, '_menu_image_icon_size', true);
        $menu_size_array = explode('x', $menu_size);

        if (!empty($menu_img)) {
            $menu_img_size = (!empty($menu_size)) ? 'width="' . $menu_size_array[0] . '" height="' . $menu_size_array[1] . '"' : '';

            $title .= '<img src="' . $menu_img . '" ' . $menu_img_size . ' width="200" height="52" alt="image-icon" >';
        }
    }
    return $title;
}

add_filter('nav_menu_item_title', 'kv_image_menu_output', 10, 2);


// Packages CPT
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
        'not_found' => __('No Events found.', 'kv_theme'),
        'not_found_in_trash' => __('No Events found in Trash.', 'kv_theme'),
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
        'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt'),
        'rewrite' => array('slug' => 'stay/%cat_accom%'),
    );

    register_post_type('accommodation', $args);

    register_taxonomy(
        'accommodation-cat',
        'accommodation',
        array(
            'label' => __('Categories'),
            'show_admin_column' => true,
            'rewrite' => array('slug' => 'accommodation-cat'),
            'hierarchical' => true,
        )
    );
}

/*
 * ALI RAZA CRON REVIEW WORK
 */

/*
 * Debug Helper
 */
if (!function_exists('pre')) {
    function pre($data, $die = 0) {
        if( !empty( $data ) ){
            echo '<pre>' . print_r($data, 1) . '</pre>';
            if ($die)
                die;
        }
    }
}

// add_action('init', function () {
//     $terms = get_terms([
//         'taxonomy' => 'accommodation-cat',
//         'hide_empty' => false,
//     ]);
//     if (!empty($terms)) {
//         foreach ($terms as $term) {
//             add_rewrite_rule('stay/' . $term->slug . '/([a-z0-9-]+)[/]?$', 'index.php?accommodation=$matches[1]', 'top');
//         }
//     }
//     // add_rewrite_rule('news-and-articles/([a-z0-9-]+)[/]?$', 'single.php?post_type=post&post=$matches[1]', 'top');
// });

function sitemap_post_url($url, $post)
{
    if ($post->post_type === 'post') {
        return home_url() . '/news-and-articles/' . $post->post_name;
    }

    return $url;
}

// add_filter('wpseo_xml_sitemap_post_url', 'sitemap_post_url', 10, 2);


function append_query_string($url, $post, $leavename = false)
{
    if ($post->post_type == 'post') {
        return home_url() . '/news-and-articles/' . $post->post_name;
    }
    return $url;
}

// add_filter('post_link', 'append_query_string', 10, 3);


function wpa_accomdation_post_link($post_link, $id = 0)
{
    $post = get_post($id);
    if ($post->post_type == 'accommodation' && 'publish' == $post->post_status && is_object($post)) {
        $terms = wp_get_object_terms($post->ID, 'accommodation-cat');
        if ($terms) {
            return str_replace('%cat_accom%', $terms[0]->slug, $post_link);
        }
    } elseif ($post->post_type == 'post' && 'publish' == $post->post_status && is_object($post)) {
        return $post_link;
    }
    return $post_link;
}

add_filter('post_type_link', 'wpa_accomdation_post_link', 1, 3);

/*
* Create Custom Intervals
*/
add_filter('cron_schedules', 'kv_cron_schedules_fn');
function kv_cron_schedules_fn($schedules)
{
    $schedules['kv_daily'] = array(
        'interval' => 1 * 24 * 60 * 60, //7 days * 24 hours * 60 minutes * 60 seconds
        'display' => 'KV Once Daily Cron'
    );
    return $schedules;
}

/*
*  Fetch Reviews Daily
*/

add_action('kv_cron_fetch_reviews', 'kv_cron_fetch_reviews_fn');
if (!wp_next_scheduled('kv_cron_fetch_reviews')) {
    wp_schedule_event(time(), 'kv_daily', 'kv_cron_fetch_reviews');
}
function kv_cron_fetch_reviews_fn()
{
    if (!wp_doing_cron()) {
        return;
    }

    $store = 'japanskiexperience.com1';
    $perPage = 100;
    for ($page = 0; ; $page++) {
        $apiUrl = 'https://api.reviews.io/merchant/reviews?store=' . $store . '&per_page=' . $perPage . '&page=' . $page;
        $response = wp_remote_get($apiUrl);
        $responseBody = wp_remote_retrieve_body($response);
        $reviews = json_decode($responseBody);
        if ($page >= $reviews->total_pages || empty($reviews->reviews))
            break;

        /*
         * Fetch Company Reviews Details
         */
        if ($page == 0) {
            update_option('kv_company_review_title', $reviews->word, 1);
            update_option('kv_company_total_reviews', $reviews->stats->total_reviews, 1);
            update_option('kv_company_average_rating', $reviews->stats->average_rating, 1);
        }

        /*
         * Fetch Reviews
         */
        if (isset($reviews->reviews))
            $reviews = $reviews->reviews;
        if (is_array($reviews) && !is_wp_error($reviews) && !empty($reviews)) {
            global $wpdb;
            foreach ($reviews as $review) {

                if (!empty($wpdb->get_results("select * from $wpdb->postmeta where meta_key = 'store_review_id' AND meta_value = $review->store_review_id"))) {
                    continue;
                }

                $data = array(
                    'post_title' => str_replace( "&quot;", "", html_entity_decode( $review->reviewer->first_name . ' ' . $review->reviewer->last_name ) ),
                    'post_type' => 'reviews',
                    'post_status' => 'publish',
                );
                if (!empty($review->comments))
                    $data['post_content'] = $review->comments;
                $post_id = wp_insert_post($data, true, false);
                if ($post_id) {
                    $reviewDate = date("F Y", strtotime($review->date_created));
                    $postedDate = 'Posted ' . $reviewDate;
                    if (function_exists('update_field')) {
                        update_field('posted_date', $postedDate, $post_id);
                    } else {
                        update_post_meta($post_id, "posted_date", $postedDate);
                    }
                    update_post_meta($post_id, "review_rating", $review->rating);
                    update_post_meta($post_id, "review_date", $review->date_created);
                    update_post_meta($post_id, "store_review_id", $review->store_review_id);
                }
            }
        }
    }
}

// breadcrumbs New Product page
add_shortcode('cst-breadcrumbs', 'codex_generate_custom_breadcrumbs');
function codex_generate_custom_breadcrumbs($atts) {
    ob_start();
    wp_reset_postdata();
    extract(shortcode_atts(array(
        'id' => get_the_ID(),
        'slug' => false,
    ), $atts));

    if (!empty($id)): ?>
        <div class="cst-breadcrumbs">
            <ul>
                <li class="home_bc"><a href="<?php echo home_url(); ?>"><i class="fa fa-home"></i></a></li>
                <?php if (have_rows('custom_breadcrumb', $id)) : ?>
                    <?php while (have_rows('custom_breadcrumb', $id)) : the_row(); ?>

                        <?php
                        $value = get_sub_field('custom_breadcrumb_link');
                        $post_id = $value->ID;
                        $breadcrumb_title = $value->post_title;
                        $breadcrumb_slug = ucfirst(str_replace('-', ' ', $value->post_name));
                        $page_link = get_permalink($post_id);
                        ?>
                        <?php if ($slug == false): ?>
                            <li><a href="<?php echo $page_link; ?>"><?php echo $breadcrumb_title; ?></a></li>
                        <?php else: ?>
                            <li><a href="<?php echo $page_link; ?>"><?php echo $breadcrumb_slug; ?></a></li>
                        <?php endif; ?>

                    <?php endwhile; ?>
                <?php endif; ?>
                <li><span><?php echo get_the_title($id); ?></span></li>
            </ul>
        </div>
    <?php endif;
    wp_reset_postdata();
    return '' . ob_get_clean();
}

// function cf_log($data, $filename = 'log_japanski.txt') {

//     $filePath = dirname(__FILE__) . '/'. $filename;
//     $data     = print_r($data, true);
//     $data .= '<br>';

//     $myfile = file_put_contents($filePath, (string) $data . PHP_EOL, FILE_APPEND | LOCK_EX);
// }

// Review Shortcode

function gform_after_submission_fn($entry, $form)
{

    $endpoint_url = 'https://book.japanskiexperience.com/assets/handlers/SubmitEnquiryV2.ashx';
    $name = rgar($entry, '1');
    $parts = explode(" ", $name);
    $lastname = array_pop($parts);
    $firstname = implode(" ", $parts);
    $ipLocationObj = json_decode(
        file_get_contents(
         "https://api.iplocation.net/?ip=".rgar($entry, 'ip') 
        ) 
    );
    $userCountry = $ipLocationObj->country_name ?? "";
    
    $body = array(
        'Page_URL' => rgar($entry, 'source_url'),
        'UserIP' => rgar($entry, 'ip'),
        'UserCountry' => $userCountry,
        'Resort' => rgar($entry, '4'),
        'FirstName' => $firstname,
        'LastName' => $lastname,
        'Email' => rgar($entry, '2'),
        'PhoneNumber' => rgar($entry, '3'),
        'CheckIn' => rgar($entry, '5'),
        'CheckOut' => rgar($entry, '6'),
        'AdultNo' => rgar($entry, '7'),
        'ChildNo' => rgar($entry, '10'),
        'NoBedrooms' => rgar($entry, '9'),
        'PreferredLocation' => rgar($entry, '11'),
        'PreferredProperty' => rgar($entry, '12'),
        'Child1Age' => rgar($entry, '15'),
        'Child2Age' => rgar($entry, '16'),
        'Child3Age' => rgar($entry, '17'),
        'Child4Age' => rgar($entry, '18'),
        'Child5Age' => rgar($entry, '19'),
        'Child6Age' => rgar($entry, '20'),
        'Child7Age' => rgar($entry, '21'),
        'Child8Age' => rgar($entry, '22'),
        'Child9Age' => rgar($entry, '23'),
        'Child10Age' => rgar($entry, '24'),
        'Child11Age' => rgar($entry, '25'),
        'Child12Age' => rgar($entry, '26'),
        'Child13Age' => rgar($entry, '27'),
        'Child14Age' => rgar($entry, '28'),
        'Child15Age' => rgar($entry, '29'),
    );


    $accommodationType = rgar($entry, '8');
    if (!empty($accommodationType))
        $body[$accommodationType] = $accommodationType;

    $queryString = http_build_query($body);
    $endpoint_url .= '?' . $queryString;
    GFCommon::log_debug('gform_after_submission ' . date('Y-m-d h:i') . ': body => ' . print_r($body, true));
    $response = wp_remote_get($endpoint_url);
    cf_log( $response );
    GFCommon::log_debug('gform_after_submission ' . date('Y-m-d h:i') . ': response => ' . print_r($response, true));
}


add_shortcode('weather', 'codex_weather');
function codex_weather($atts = '')
{
    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);

    // override default attributes with user attributes
    extract(shortcode_atts(
        array(
            'resort' => '',
            'data' => 'max'
        ), $atts));
    if (empty($resort))
        return 'Resort Not Found';
    ob_start();
    $url = 'https://api.snow-forecast.com/v1/resorts/' . $resort . '/6day/auth_feed.json';
    $username = 'XML66';
    $password = 'japanskiexperience';
    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        )
    );
    $response = wp_remote_get($url, $args);
    $responseBody = wp_remote_retrieve_body($response);
    $result = json_decode($responseBody);
    if (is_object($result) && !is_wp_error($result)) {
        $forecast = [];
        foreach ($result->forecast->periods as $period) {
            $forecast[$period->pdayname . ' ' . $period->pdom][$period->plcname] = $period;
        }
        ?>
        <div class="weather-cover">
            <div class="weather_titles">
                <ul>
                    <li class="day_night">Days:</li>
                    <li class="am-pm">Time:</li>
                    <li class="weather">Weather:</li>
                    <li class="snow">Snowfall:</li>
                    <li class="freezing">Freezing Level:</li>
                    <li class="wind">Wind:</li>
                    <li class="temp-min">Temp Min:</li>
                    <li class="temp-max">Temp Max:</li>
                </ul>
            </div>
            <div class="weather_report">
                <ul>
                    <?php
                    foreach ($forecast as $dayKey => $day) {
                        ?>
                        <li class="report_repeater">
                            <div class="day_night">
                                <?= $dayKey; ?>
                            </div>
                            <?php
                            foreach ($day as $timeKey => $time) {
                                if ($timeKey == 'morning')
                                    $timeKey = "am";
                                if ($timeKey == 'afternoon')
                                    $timeKey = "pm";
                                ?>
                                <ul>
                                    <li class="am-pm"><?= $timeKey; ?></li>
                                    <li class="weather"><img
                                                src="<?= get_template_directory_uri() . '/images/forecast/wxicons/' . $time->$data->psymbol . '.png'; ?>"
                                                alt=""> <span><?= $time->$data->pphrase; ?></span></li>
                                    <li class="snow"><?= empty($time->$data->psnow) ? $time->$data->psnow : '<img
                                                src="' . get_template_directory_uri() . '/images/snowflake.png" alt="">' ?></li>
                                    <li class="freezing"><?= $time->pflevel; ?>m</li>
                                    <li class="wind"><?= $time->$data->pwind; ?> km/h</li>
                                    <li class="temp-min"><?= $time->$data->pmin; ?>C</li>
                                    <li class="temp-max"><?= $time->$data->pmax; ?>C</li>
                                </ul>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
        <div class="forcast_link">
            <a href="https://www.snow-forecast.com/resorts/<?php echo $resort; ?>" target="_blank">
                <img src="<?php echo get_template_directory_uri(); ?>/images/snow-forcast-logo.jpg" alt="Snow Forcast">
            </a>
        </div>
        <?php
    } else {
        echo 'Something went wrong';
    }
    ?>
    <?php
    return '' . ob_get_clean();
}

add_shortcode('cs_reviews', 'codex_cs_reviews');
function codex_cs_reviews()
{
    ob_start();
    $average = get_option('kv_company_average_rating') ?: '4.8';
    $total = get_option('kv_company_total_reviews') ?: '632';
    $count = $average . ' Average ' . $total . ' Reviews';
    $paged = isset($_POST['ajaxpage']) ? $_POST['ajaxpage'] : 1;
    $args = array(
        'post_type' => 'reviews',
        'posts_per_page' => 20,
        'paged' => $paged,
        'orderby' => 'publish_date',
        'order' => 'DESC',
        'meta_key' => 'review_date',
        'orderby' => 'meta_value_num',
        'order' => 'DESC'
    );
    $loop = new WP_Query($args);
    ?>
    <div class="cs_reviews">
        <div class="cs__inn">
            <div class="cs_total">
                <a href="https://www.reviews.io/company-reviews/store/japanskiexperience-com1" target="_blank">
                    <span class="total_count"><?= $average; ?></span>
                    <span class="fa star-<?= round($average); ?>"></span>
                    <span class="total_reviews"><?= $count; ?></span>
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
            let page = 2;
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
                            page++;
                            if (page == max)
                                button.remove(); // if last page, remove the button
                        } else {
                            button.remove(); // if no data, remove the button as well
                        }
                    }
                });
            });
        });
    </script>
    <?php
    return '' . ob_get_clean();
}

add_filter( 'wpseo_breadcrumb_output', 'wp_kama_wpseo_breadcrumb_output_filter', 10, 2 );
function wp_kama_wpseo_breadcrumb_output_filter( $presentation, $output ){
    $presentation = str_replace('Home',"<i class='fa fa-home' aria-hidden='true'></i>",$presentation);
    return $presentation;
}

add_action("wp_ajax_my_area_change", "hz_get_area_ajax_callback");
add_action("wp_ajax_nopriv_my_area_change", "hz_get_area_ajax_callback");
function hz_get_area_ajax_callback(){
    
    $cat_id = $_POST['cat_id'];
    // area
    // get the textarea value from options page without any formatting
    $area_choices = get_term_meta($cat_id, 'area');
    // get field value to match
    $area_value = get_post_meta( $_POST['post_id'], 'page_builder_6_area', true );
    $area_data = '';
    if (@$area_choices) {
        $area_data = '<option>choose an area</option>';
        foreach ($area_choices[0] as $choice => $area_val) {
            if ($area_val == $area_value) {
                $area_data .= '<option selected value="'.$area_val.'">'.$area_val.'</option>';
            }
            else{
                $area_data .= '<option value="'.$area_val.'">'.$area_val.'</option>';
            }
        }
        // $area_data .= '</div>';
    }

    //type
    // get the textarea value from options page without any formatting
    $type_choices = get_term_meta($cat_id, 'type');
    // pre($type_choices);
    // get field value to match
    $type_values = get_post_meta( $_POST['post_id'], 'page_builder_6_type' );
    $type_array = explode(',', $type_values[0]);
    $selected = '';
    if (@$type_choices) {
        $type_data = '<option>choose a type</option>';
        foreach ($type_choices[0] as $choice => $type_val) {
            if(in_array($type_val, $type_array)) {
                $selected = 'selected';
            }
            else{
                $selected = '';
            }
            $type_data .= '<option '.$selected.' value="'.$type_val.'" >'.$type_val.'</option>';
        }
        // $type_data .= '</div>';
    }
    // $data = $area_data .' '. $type_data;
    // die($data);
    wp_send_json( [$area_data, $type_data] );
}

function my_admin_footer_function() {
    ?>
    <script>
         var adminAjaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
        jQuery(function($){
            var urlParams = new URLSearchParams(window.location.search);
            $('.hz_type').find('[type="search"]').attr('placeholder', 'choose a type');
            $.ajax({
                 type : "post",
                 dataType : "html",
                 url : adminAjaxUrl,
                 data : {action: 'my_area_change',cat_id : $('.get_area select').eq(1).val(),post_id : urlParams.get('post')},
                 success: function(response) {
                    response = JSON.parse( response );    
                    // console.log(response);
                    if( $(response).length > 0 ) {
                        var area_fields = response[0];
                        var type_fields = response[1];
                        // console.log(type_fields);
                        $('.hz_area select').eq(1).html(area_fields);
                        $('.hz_type select').eq(1).html(type_fields);
                    }

                 },
                 error: function (xhr, exception) {
                    var msg = "";
                    if (xhr.status === 0) {
                        msg = "Not connect.\n Verify Network." + xhr.responseText;
                    } else if (xhr.status == 404) {
                        msg = "Requested page not found. [404]" + xhr.responseText;
                    } else if (xhr.status == 500) {
                        msg = "Internal Server Error [500]." +  xhr.responseText;
                    } else if (exception === "parsererror") {
                        msg = "Requested JSON parse failed.";
                    } else if (exception === "timeout") {
                        msg = "Time out error." + xhr.responseText;
                    } else if (exception === "abort") {
                        msg = "Ajax request aborted.";
                    } else {
                        msg = "Error:" + xhr.status + " " + xhr.responseText;
                    }
                    console.log(msg);
                }
              });
            $(document).on("change",'.get_area select' ,function() {
                $('.hz_type').find('[type="search"]').attr('placeholder', 'choose a type');
                $.ajax({
                     type : "post",
                     dataType : "html",
                     url : adminAjaxUrl,
                     data : {action: 'my_area_change',cat_id : $(this).val()},
                     success: function(response) {

                        response = JSON.parse( response ); 
                        $('.hz_type select').eq(1).html('');
                        $('.hz_type').find('[type="search"]').attr('placeholder', 'choose a type');
                        var area_fields = $(response)[0];
                        var type_fields = $(response)[1];
                        // console.log(response);
                        $('.hz_area select').eq(1).html(area_fields);
                        $('.hz_type select').eq(1).html(type_fields);
                     },
                     error: function (xhr, exception) {
                        var msg = "";
                        if (xhr.status === 0) {
                            msg = "Not connect.\n Verify Network." + xhr.responseText;
                        } else if (xhr.status == 404) {
                            msg = "Requested page not found. [404]" + xhr.responseText;
                        } else if (xhr.status == 500) {
                            msg = "Internal Server Error [500]." +  xhr.responseText;
                        } else if (exception === "parsererror") {
                            msg = "Requested JSON parse failed.";
                        } else if (exception === "timeout") {
                            msg = "Time out error." + xhr.responseText;
                        } else if (exception === "abort") {
                            msg = "Ajax request aborted.";
                        } else {
                            msg = "Error:" + xhr.status + " " + xhr.responseText;
                        }
                        console.log(msg);
                    }
                });
            });

            $(document).on("change",'.get_area select' ,function() {

                $('.hz_type select').eq(1).html('');
                $('.hz_type').find('[type="search"]').attr('placeholder', 'choose a type');

            });
        })
    </script>
    <?php
}
add_action('admin_footer', 'my_admin_footer_function');

function findKey($array, $keySearch, $print = false)
{
    foreach ($array as $key => $item) {
        if ($key == $keySearch) {
            if ($print == true) {
                print_r($item);
            }
            else
                return true;
        } elseif (is_array($item) && findKey($item, $keySearch)) {
            if ($print == true) {
                print_r($item);
            }
            else
                return true;
        }
    }
    return false;
}

function cf_searchForKey($find, $array) {
    if (is_array($array) || is_object($array)) {
        foreach ($array as $key => $val) {
            if ($key === $find) {
                return $val;
            } elseif (is_array($val) || is_object($val)) {
                $x = cf_searchForKey($find, $val);
                if ($x !== false) {
                    return $x;
                }
            }
        }
    }
    return false;
}


function hk_save_area_value( $post_id, $post ) {
    
        if(!findKey($_POST, 'field_64e2efdab7b8c')) return;
        $area_val = array();
        $type_val = array();
        $area_val = cf_searchForKey('field_64e2efdab7b8c', $_POST['acf']);
        $type_val = cf_searchForKey('field_64e2f001b7b8d', $_POST['acf']);
        $value = '';
        if($area_val == 'choose an area'){
            return wp_die(
                'Area field cannot be empty. </br> <a href="'.$_SERVER['HTTP_REFERER'] .'"> Return</a> '
            );
        }
        foreach ($type_val as $key) {
            $value .= $key.',';
            // pre($key);
        }
        update_post_meta($_POST['post_ID'], 'page_builder_6_area', $area_val );
        update_post_meta($_POST['post_ID'], 'page_builder_6_type', $value );
}
add_action( 'save_post', 'hk_save_area_value' , 10, 2);
// add_action( 'wp_head', 'hk_get_category');

function hk_get_category(){
    $type = get_term_meta($cat_id, 'type');
    $page_builder = get_field('page_builder');
    echo '<pre> type,s';
        print_r($type);
    // echo 'page builder';
    //     foreach ($page_builder as $key => $section) {
    //         echo ' key: ';
    //         print_r($key);
    //         echo ' section: ';
    //         print_r($section);
    //     };
    echo '</pre>';
}


add_filter('cron_schedules', 'cron_add_twice_daily_fn');
function cron_add_twice_daily_fn($schedules) {
    $schedules['per_minute'] = array(
        'interval' => 3600,
        'display' => __('Per Hour')
    );
    return $schedules;
}


add_action('init', 'myprefix_custom_cron_schedule');
function myprefix_custom_cron_schedule() {
    if (!wp_next_scheduled('remove_cache_folder')) {
        wp_schedule_event(current_time('timestamp'), 'per_minute', 'remove_cache_folder');
    }
}

add_action('remove_cache_folder', 'fn_auto_remove_cache');
function fn_auto_remove_cache() {

        $file_path = WP_CONTENT_DIR. '/cache';
        $pieces = explode('/', $file_path);
        $last_word = array_pop($pieces);
        
        if($last_word == "cache"){
            deleteDirectory($file_path);
            cf_log('Cache Removed', 'cache_log.txt');
        }
}


if (!function_exists('cf_log')) {
    function cf_log($data, $filename = 'log_cronjob.txt')
    {
        $filePath = dirname(__FILE__) . '/logs/' . $filename;
        $data     = print_r($data, true);
        $data .= ' -- -- -- ' . current_time('mysql');
        $myfile = file_put_contents($filePath, (string) $data . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('deleteDirectory')) {
    function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }
}

// get current user's country value
add_filter( 'gform_pre_submission_1', 'get_country' );

function get_country( $form ){
    $ipLocationObj = json_decode(
        file_get_contents(
            "https://api.iplocation.net/?ip=". $_SERVER['REMOTE_ADDR'] 
        )
    );
    $_POST['input_33'] = $ipLocationObj->country_name ?? "";
    // pre( $_POST , 1);
}

add_filter( 'gform_pre_submission_1', 'get_ref_title' );
function get_ref_title( $form ){
    $post_id = !empty( $_POST['input_34'] ) ? url_to_postid( $_POST['input_34'] ) : url_to_postid( $_POST['input_32'] );
    $post_title = get_the_title( $post_id );
    $_POST['input_35'] = $post_title;
}
// if(@$_GET['tedt'] == 'email')

if( ! function_exists('CFCreateLogs') ) {
    function CFCreateLogs( $data = null ) {
        //Save string to log, use FILE_APPEND to append.
        $filename = 'log_' . date("j.n.Y").'.log';
        if( !empty($data) )
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . $filename, print_r($data, true), FILE_APPEND);
    }
}

add_filter( 'gform_notification_1', 'my_gform_notification_signature', 10, 3 );

function my_gform_notification_signature( $notification, $form, $entery ) {

    // Search criteria to get all entries
    $total_entries = GFAPI::count_entries( $entery['form_id'], ['status' => 'active'] );

    // Search criteria to get entries
    $search_criteria = array(
        'status'        => 'active',// get all fields with the status active
        'field_filters' => array(
            array(
                'key'   => '2', // email field meta key
                'value' => $_POST['input_2'], //email input field
                'operator' => 'LIKE',
            )
        )
    );
    $count_result    = GFAPI::count_entries( 1 , $search_criteria );
    $sorting         = array();
    $paging          = array( 'offset' => 0, 'page_size' => $count_result );
    $total_count     = 0;
    $skip_fields     = ['id', 'form_id', 'post_id', 'date_updated', 'is_starred',
                        'is_read','status','transaction_type','created_by','ip',
                        'is_fulfilled','transaction_id','payment_method', 'is_read',
                        'payment_amount','payment_date','payment_status','currency',
                        'user_agent','user_agent','source_url','is_read'];
    // Getting the entries
    $result = GFAPI::get_entries( 1 , $search_criteria, $sorting, $paging );
    $entry_num = 1;
    if(!empty( count( $result ) > 1 )){

        foreach ($result as $entry_no => $entry ) {
            if( $entry['id'] == $entery['id'] )
                continue;
            $entries .= '<strong>Previous Entry Number: '.$entry_num.'</strong> <table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF" id="'.$entry['id'].'" ><tbody> ';
            $entry_url .= $entry_no === array_key_last($result) ? 
                '<a href="'.
                    admin_url( 'admin.php?page=gf_entries&view=entry&id='.$entry['form_id'].'&lid='.$entry['id'] )
                .'">'.$entry_num.'
                </a>' : 
                '<a href="'.
                    admin_url( 'admin.php?page=gf_entries&view=entry&id='.$entry['form_id'].'&lid='.$entry['id'] )
                .'">'
                    .$entry_num.
            '</a> | ';
            foreach ($entry as $key => $value) {
                if( in_array($key, $skip_fields) )
                    continue;
                $value       = $key == 'date_created' ? date('Y-m-d', strtotime($value)) : $value;
                $label       = is_numeric ( $key ) === true ? 
                    GFAPI::get_field( $entry['form_id'], $key )['label'] : 
                    $key;
                $entries     .= '<tr bgcolor="#EAF2FA">
                    <td colspan="2">
                        <font style="font-family: sans-serif; font-size:12px;"><strong>'.$label.'</strong></font>
                    </td>
                    <td>
                        <font style="font-family: sans-serif; font-size:12px;">'.$value.'</font>
                    </td>
                </tr>';
                if( $entry_no != array_key_last($result) )
                    if( $key === array_key_last($entry) ){
                        $entry_num++;
                    } 
            }
            $entries .= '</tbody></table>';
        }
    }
    else{
        $entry_url = '0';
        $entries = "The user does not has any previous entries";
    }
    $entries = ob_get_clean().$entries;
    // $notification['message'] = str_replace( '{Previous Entries:36}', $entries , $notification['message'] );
    // $notification['message'] = str_replace( '{Entries URL:37}', $entry_url , $notification['message'] );
    $notification['message'] .= $entries;
    // $notification['message'] = '<div style="background-color:"#EAF2FA">'. $entries. '</div>';
    // $notification['message'] .= $entry_url;
    // CFCreateLogs(htmlentities( $entries ));
    // pre( $notification, 1 );
    return $notification;
}

// add_action( 'wp_head', 'hk_get_post_type' );

function hk_get_post_type(){

    $apiUrl = 'https://api.reviews.io/merchant/reviews?store=japanskiexperience.com1&per_page=100&page=1';
    $response = wp_remote_get($apiUrl);
    $responseBody = wp_remote_retrieve_body($response);
    $reviews = json_decode($responseBody);
    $first_name = html_entity_decode( $reviews->reviews[0]->reviewer->first_name);
    pre( @$_GET['_devv'] == 'hamza' ? str_replace( "&quot;", "", $first_name ) : '');
}


// if (@$_GET['kv'] == 'dev') {

// Add Popup HTML to Footer
function add_popup_html() {
$hakuba_notice = get_field('hakuba_notice', 'option');
    ?>
    <!-- Popup Markup -->
    <div class="popup-overlay" id="popupOverlay"></div>
    <div class="popup" id="popup">
		<?php echo $hakuba_notice; ?>
        <button class="close-btn" id="closePopup">X</button>
    </div>

    <style>
        .popup { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); z-index: 1000; display: none; border: solid 2px #be2029; text-align: center;}
        .popup h2 { color: #30322f; }
        .popup p { margin: 0; font-size: 18px; }
        .popup-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999;display:none;}
        .close-btn { display: inline-block; background: #be2029; color: #fff; padding: 5px 10px; border: none; cursor: pointer; border-radius: 50px; position: absolute; top: -20px; right: -20px; }
    </style>
<script>
jQuery(document).ready(function ($) {
    // const cookieName = "popupClosed";

    // // Function to get a cookie value
    // function getCookie(name) {
    //     const nameEQ = name + "=";
    //     const ca = document.cookie.split(';');
    //     for (let i = 0; i < ca.length; i++) {
    //         let c = ca[i].trim();
    //         if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    //     }
    //     return null;
    // }

    // Show popup on mouseover if cookie doesn't exist
        // $(".popopen").on("mouseover", function () {
        //     if (!getCookie(cookieName)) {
        //         $("#popup").fadeIn();
        //         $("#popupOverlay").fadeIn();
        //     }
        // });

    $("#input_1_4").on("change", function () {
        const selectedValue = $(this).val();
        // if (selectedValue === "Hakuba" && !getCookie(cookieName)) {
        if (selectedValue === "Hakuba") {
            $("#popup").fadeIn();
            $("#popupOverlay").fadeIn();
        }
    });

    // Close popup and set cookie
    $("#closePopup").on("click", function () {
        $("#popup").fadeOut();
        $("#popupOverlay").fadeOut();
        // setCookie(cookieName, true, 1); // Set cookie for 1 day
    });
});

</script>
    <?php
}
//add_action('wp_footer', 'add_popup_html');
// }

// Schedule the cron job
function kv_schedule_cron_job() {
    if (!wp_next_scheduled('kv_get_bookings_event')) {
        wp_schedule_event(time(), 'hourly', 'kv_get_bookings_event');
    }
}
add_action('wp', 'kv_schedule_cron_job');

// Cron job callback function
function kv_get_bookings_function() {
    $log_message = "cron ran at: " . date('Y-m-d H:i:s');
    cf_log($log_message, 'cron_log.txt'); // Assuming cf_log is defined in your codebase
}
add_action('kv_get_bookings_event', 'kv_get_bookings_function');


//** Saad Work: 02-Mar-2026 **/
// Define field mappings as constants for easier maintenance
define( 'FORM_QUOTE_FIELD_MAP', [
    'full_name' => '1',
    'email' => '2',
    'phone' => '3',
    'resort_name' => '4',
    'check_in' => '5',
    'check_out' => '6',
    'adults' => '7',
    'bedrooms' => '9',
    'children' => '10',
    'property_name' => '12',
    'type' => '8',
    'utm_source' => '39',
    'utm_medium' => '40',
    'utm_campaign' => '41',
] );

define( 'FORM_QUOTE_CHILD_AGE_FIELDS', range( 15, 29 ) );

add_action( 'gform_after_submission_1', 'post_to_third_party_1', 10, 2 );
function post_to_third_party_1( $entry, $form ) {

    // Extract query parameters safely
    $query_param = get_utm_query_string();
    
    $endpoint_urls = [
        'https://stay.japanskiexperience.com/api/v1/third-party-enquiry' . $query_param,
        // 'https://trip.japanskiexperience.com/api/v1/third-party-enquiry' . $query_param,
    ];

    // Build request body
    $body = build_third_party_body( $entry );
    // echo '<pre>';
    // print_r( $body );
    // echo '</pre>';
    // die;
    
    if ( ! $body ) {
        error_log( 'Failed to build third-party enquiry body for form submission.' );
        return;
    }

    // Check if check-in date is >= 2026-05-01
    // $check_in_raw = rgar( $entry, FORM_QUOTE_FIELD_MAP['check_in'] );
    $check_in_raw = @$body['check_in'];
    if ( ! is_check_in_date_valid( $check_in_raw ) ) {
        add_action('gform_after_submission_1', 'gform_after_submission_fn', 10, 2);
        error_log( 'Check-in date (' . $check_in_raw . ') is before 2026-05-01. API request not sent.' );
        return;
    }
    // die;

    // Prepare request arguments
    $args = [
        'method'      => 'POST',
        'headers'     => [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body'        => $body,
        'timeout'     => 15,
        'redirection' => 5,
    ];
    // echo '<pre>';
    // print_r( $args );
    // echo '</pre>';
    // die;

    // Send to each endpoint
    foreach ( $endpoint_urls as $url ) {
        $response = wp_remote_post( $url, $args );
        
        // Check for errors
        if ( is_wp_error( $response ) ) {
            error_log( 'API Error for ' . $url . ': ' . $response->get_error_message() );
            continue;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_response = wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            error_log( 'API Request failed for ' . $url . ' (Status: ' . $status_code . '): ' . $body_response );
            continue;
        }

        // Decode and log successful response
        $api_response = json_decode( $body_response, true );
        cf_log( $api_response, 'apibody.txt' );
    }
}

/**
 * Build the third-party enquiry body from form entry
 */
function build_third_party_body( $entry ) {
    $field_map = FORM_QUOTE_FIELD_MAP;
    $child_age_fields = FORM_QUOTE_CHILD_AGE_FIELDS;

    // Extract and format dates safely
    $check_in = rgar( $entry, $field_map['check_in'] );
    $check_out = rgar( $entry, $field_map['check_out'] );

    if ( ! $check_in || ! $check_out ) {
        error_log( 'Missing check-in or check-out date in form submission.' );
        return false;
    }

    $check_in = str_replace( '/', '-', $check_in );
    $check_out = str_replace( '/', '-', $check_out );

    // Extract child ages
    $child_ages = [];
    foreach ( $child_age_fields as $field_id ) {
        $age = rgar( $entry, $field_id );
        if ( ! empty( $age ) ) {
            $child_ages[] = $age;
        }
    }

    return [
        'full_name'      => rgar( $entry, $field_map['full_name'] ),
        'email'          => rgar( $entry, $field_map['email'] ),
        'phone_number'   => rgar( $entry, $field_map['phone'] ),
        'resort_name'    => rgar( $entry, $field_map['resort_name'] ),
        'resort_id'      => '',
        'check_in'       => $check_in,
        'check_out'      => $check_out,
        'no_of_adults'   => rgar( $entry, $field_map['adults'] ),
        'no_of_childs'   => rgar( $entry, $field_map['children'] ),
        'child_ages'     => $child_ages,
        'type'           => rgar( $entry, $field_map['type'] ),
        'bedrooms'       => rgar( $entry, $field_map['bedrooms'] ),
        'property_name'  => rgar( $entry, $field_map['property_name'] ),
        'assignees'      => [],
        'platform'       => 'wordpress',
        'utm_source'     => rgar( $entry, $field_map['utm_source'] ),
        'utm_medium'     => rgar( $entry, $field_map['utm_medium'] ),
        'utm_campaign'   => rgar( $entry, $field_map['utm_campaign'] ),
    ];
}

/**
 * Extract UTM parameters from query string safely
 */
function get_utm_query_string() {
    $utm_params = [ 'utm_source', 'utm_medium', 'utm_campaign' ];
    $query_parts = [];

    foreach ( $utm_params as $param ) {
        if ( isset( $_GET[ $param ] ) ) {
            $query_parts[] = $param . '=' . urlencode( $_GET[ $param ] );
        }
    }

    return ! empty( $query_parts ) ? '?' . implode( '&', $query_parts ) : '';
}

/**
 * Validate if check-in date is >= 2026-05-01
 * 
 * @param string $check_in_date Date string in DD/MM/YYYY format
 * @return bool True if date is >= 2026-05-01, false otherwise
 */
function is_check_in_date_valid( $check_in_date ) {
    if ( ! $check_in_date ) {
        return false;
    }

    try {
        $min_date = new DateTime( '2026-05-01' );
        $check_in = new DateTime( $check_in_date );
        // echo '<pre>';
        // print_r( $check_in );
        // echo '</pre>';
        
        return $check_in >= $min_date;
    } catch ( Exception $e ) {
        error_log( 'Date validation error: ' . $e->getMessage() );
        return false;
    }
}

add_action( 'gform_pre_submission_4', 'hz_set_field_values' );
function hz_set_field_values( $form ) {

    $field_map = FORM_QUOTE_FIELD_MAP;
    
    $check_in = rgpost( 'input_' . $field_map['check_in'] );
    $check_out = rgpost( 'input_' . $field_map['check_out'] );

    if ( ! $check_in || ! $check_out ) {
        error_log( 'Missing check-in or check-out dates in form pre-submission.' );
        return;
    }

    // Calculate number of nights with error handling
    $nights = calculate_nights( $check_in, $check_out );
    if ( $nights !== false ) {
        $_POST['input_8'] = strval( $nights );
    }

    // Get and cache customer data
    $customers = get_cached_customers();
    if ( ! $customers ) {
        error_log( 'Failed to retrieve customers data.' );
        return;
    }

    $user_email = rgpost( 'input_' . $field_map['email'] );
    if ( ! $user_email ) {
        return;
    }

    // Lookup customer and set is_sent field
    $customer = find_customer_by_email( $customers, $user_email );
    if ( $customer && isset( $customer['is_sent'] ) ) {
        $_POST['input_43'] = $customer['is_sent'];
    }
}

/**
 * Calculate nights between two dates
 * 
 * @param string $check_in Date in DD/MM/YYYY format
 * @param string $check_out Date in DD/MM/YYYY format
 * @return int|bool Number of nights or false on error
 */
function calculate_nights( $check_in, $check_out ) {
    try {
        $check_in_date = new DateTime( $check_in );
        $check_out_date = new DateTime( $check_out );
        
        if ( $check_in_date >= $check_out_date ) {
            error_log( 'Check-out date must be after check-in date.' );
            return false;
        }

        $interval = $check_in_date->diff( $check_out_date );
        return (int) $interval->days;
    } catch ( Exception $e ) {
        error_log( 'Date calculation error: ' . $e->getMessage() );
        return false;
    }
}

/**
 * Fetch customers with caching
 * 
 * @return array|bool Array of customers or false on error
 */
function get_cached_customers() {
    $cache_key = 'japanski_customers_list';
    $cache_time = HOUR_IN_SECONDS; // Cache for 1 hour

    // Check cache first
    $customers = get_transient( $cache_key );
    if ( $customers !== false ) {
        return $customers;
    }

    // Fetch from API
    $api_url = 'https://stay.japanskiexperience.com/api/v1/customers';
    $response = wp_remote_get( $api_url, [
        'timeout' => 15,
        'redirection' => 5,
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'Customer API Error: ' . $response->get_error_message() );
        return false;
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code < 200 || $status_code >= 300 ) {
        error_log( 'Customer API returned status: ' . $status_code );
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! isset( $data['customers'] ) || ! is_array( $data['customers'] ) ) {
        error_log( 'Invalid customers API response format.' );
        return false;
    }

    // Cache the result
    set_transient( $cache_key, $data['customers'], $cache_time );

    return $data['customers'];
}

/**
 * Find customer by email address
 * 
 * @param array $customers List of customer data
 * @param string $email Email to search for
 * @return array|null Customer data or null if not found
 */
function find_customer_by_email( $customers, $email ) {
    if ( ! is_array( $customers ) ) {
        return null;
    }

    $email = sanitize_email( $email );
    
    foreach ( $customers as $customer ) {
        if ( isset( $customer['email'] ) && sanitize_email( $customer['email'] ) === $email ) {
            return $customer;
        }
    }

    return null;
}
