<?php

$main_title = $section['main_title'];

$cat = $section['select_category'];
// print_r($section);
$product_count = $section['product_count'];

$by_default = get_field('by_default', 'option');



echo '<section class="full-section packages_listing">';

echo '<div class="container">';

echo '<div class="pl__inn s2">';

if ($main_title) {

    printf('<h2>%s</h2>', $main_title);

}

if (!empty($cat)) :

    // print_r ($cat->term_id);

    // die;

    // echo do_shortcode('[cat_listing cat_id="' . $cat->term_id . '"]');

    echo do_shortcode('[cat_listing_1 cat_id="' . $cat->term_id . '"]');


    // echo do_shortcode('[cat_listing]');

    echo '<div class="pl_listing new_listing">';

        $loadID = 'l-' . uniqid();

        echo '<div class="pl_list ' . $loadID . '">';



        $args = array(

            'post_type' => 'accommodation',

            'posts_per_page' => 12,

            'paged' => !empty($_POST['current_page']) ? $_POST['current_page'] : 1,

            'tax_query' => array(

                array(

                    'taxonomy' => 'accommodation-cat',

                    'field' => 'slug',

                    'terms' => $cat->slug,

                ),

            ),

            'meta_query' => array(

                'relation' => 'AND',

                'post_order_yes' => array(

                    'key' => 'post_order',

                    'value' => 0,

                    'compare' => '>',

                    'type' => 'numeric'

                ),

            ),

            'orderby' => 'post_order_yes',

            'order' => 'ASC'
        );  

        if (isset($_POST['searches'])) {

            $searchBedroom = $_POST['rooms_field'];

            $price = $_POST['price'];

            $area  = $_POST['area'];

            $type  = $_POST['type'];

            $searches = $_POST['searches'];


            if (!empty($searches)) {

                $args['s'] = $searches;

            }


            if (!empty($searchBedroom)
             || !empty($price)
             // || !empty($area) 
             || !empty($type)) {

                $args['meta_query'] = array(

                    'relation' => 'AND'

                );

                $args['meta_query'][] = array(

                    'key'     => 'price',

                    // 'value'   => (int) $price, // From price value

                    // 'compare' => '<=',

                    // 'type'    => 'NUMERIC'
                    'value'    => array(0, (int) $price),
                    'type'     => 'numeric',
                    'compare'  => 'BETWEEN',

                );



                if (!empty($searchBedroom)) {

                    foreach ($searchBedroom as $value) {

                        // array_push(

                        $args['meta_query'][] =

                            array(

                                'key' => 'bedroom_number',

                                'value' => sprintf('"%s";', $value),

                                'compare' => 'LIKE',

                            );

                        // );

                    }

                }

                //area
                // if (!empty($area)) {

                //     $hk_area_value = get_post_meta( get_the_ID(), 'page_builder_6_area', true );

                //     $area_val = array(
                //                     'key' => 'area',
                //                     'value' => $hk_area_value,
                //                     'compare' => 'LIKE'
                //                 );
                //     $args['meta_query'][] = $area_val;
                // }

                //type
                // if (!empty($type)) {
                //     foreach ($type as $value) {

                //         $args['meta_query'][] =

                //             array(

                //                 'key' => 'type',

                //                 'value' => $value,

                //                 'compare' => 'LIKE',

                //             );

                //     }
                // }

            }
        }

        //area
        $hk_area_value = get_post_meta( get_the_ID(), 'page_builder_6_area', true );

        $area_val = array(
                        'key' => 'area',
                        'value' => $hk_area_value,
                        'compare' => 'LIKE'
                    );
        $args['meta_query'][] = $area_val;

        $hk_type_value = get_post_meta( get_the_ID(), 'page_builder_6_type', true );
        if( !empty( $hk_type_value ) ){
            // type
            $hk_type = explode(',', $hk_type_value);
            $type_val = array('relation' => 'OR');
            // pre($hk_type);
            foreach ( $hk_type as $key => $value ) {
                if(!empty($value)){
                    // $type_val[$key]['key'] = 'type';
                    // $type_val[$key]['value'] = $value;
                    $type_val[] = array(
                        'key' => 'type',
                        'value' => $value,
                        'compare' => 'LIKE',
                    );
                }

            }

            $args['meta_query'][] = $type_val;
        }
        // echo ' array here ';
        // pre( $args );
        // $args = array(
        //     'post_type' => 'accommodation',
        //     'posts_per_page' => 12,
        //     'paged' => 1,
        //     'tax_query' => array(
        //         array(
        //             'taxonomy' => 'accommodation-cat',
        //             'field' => 'slug',
        //             'terms' => 'niseko-accommodation',
        //         ),
        //     ),
        //     'meta_query' => array(
        //         'relation' => 'AND',
        //         'post_order_yes' => array(
        //             'key' => 'post_order',
        //             'value' => 0,
        //             'compare' => '>',
        //             'type' => 'numeric',
        //         ),
        //         array(
        //             'key' => 'price',
        //             'value' => 200001,
        //             'compare' => '<=',
        //             'type' => 'NUMERIC',
        //         ),
        //         array(
        //             'key' => 'area',
        //             'value' => 'Hirafu Village',
        //             'compare' => 'LIKE',
        //         ),
        //     ),
        //     'orderby' => 'post_order_yes',
        // //     'order' => 'ASC',
        // // );
        $loop = new WP_Query($args);

        if ($loop->have_posts()) {

            while ($loop->have_posts()) : $loop->the_post();

                $lists_img = get_field('accommodation_list_image', get_the_ID());

                $bedroom = get_field('bedroom_detail', get_the_ID());

                $price = get_field('price', get_the_ID());

                $type = get_field('type', get_the_ID());

                $area = get_field('area', get_the_ID());



                echo '<div class="listing_box test">';

                echo '  <a href="' . get_the_permalink() . '">';

                echo '  <div class="listing_box_img">';

                echo '      <img src="' . $lists_img . '" alt="' . get_the_title() . '"/>';

               
                if ($price && is_numeric($price))

                    $price = number_format($price, 0);

                if ($price) {

                    echo '  <span class="simple_price" href="javascript:;">From: ¥' . $price . ' Per Night</span>';

                } else {

                    echo '  <span class="simple_price" href="javascript:;">' . $by_default . '</span>';

                }

                echo '</div>'; //listing_box_img

                echo '<div class="listing_cont">';

                echo '<h3>' . get_the_title() . '</h3>';

                echo '<p>' . $bedroom . '</p>';



                //echo '<a href="' . get_the_permalink() . '">View More</a>';

                echo '</div>'; //listing_cont
                echo '<span>View More</span>';
                echo '</a>';

                echo '</div>'; //listing_box

            endwhile;

            wp_reset_postdata();

        }
        else {

            echo '<h3>No Data Found</h3>';

        }

        echo '</div>'; //pl_list

    echo '</div>'; //pl_listing

endif;

echo '<div class="loadmore-button-wrapper">'; //pl__inn

if ($loop->max_num_pages > 1) {

    echo '<button data-max_page="' . $loop->max_num_pages . '" type="button" class="btn light_green kv_loadmore" data-load="new_listing">Load More</button>';

}

echo '</div>';



echo '</div>'; //pl__inn

echo '</div>';

echo '</section>';

