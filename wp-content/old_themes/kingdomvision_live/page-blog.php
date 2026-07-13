<?php get_header();
/* 
* Template Name: Blogs
*/

function sort_by_date($a, $b)
{
    $aDate = get_field('post_date');
    $bDate = get_field('post_date');
    if ($aDate) {
        $aDate = $a->post_date;
    }
    if ($bDate) {
        $bDate = $b->post_date;
    }
    return strtotime($bDate) - strtotime($aDate);
}

 $award_image = get_field('award_image', 'option');
 $award_opt   = get_field('opt', 'option');

echo '<section class="full-section simple_page_banner_with_title" style="background: url(' . get_the_post_thumbnail_url() . ') no-repeat center/cover;">';
if($award_opt === true){
        echo '<div class="award_badge">';
            echo wp_get_attachment_image($award_image, 'full');
        echo '</div>'; //award_badge
}
echo '<div class="container">';
echo '<h2>' . get_the_title() . '</h2>';
echo '</div>';
echo '</section>';

echo '<section class="full-section cs_breadcrumbs">';
echo '<div class="container">';
echo do_shortcode('[cst-breadcrumbs]');
echo '</div>';
echo '</section>';

if (get_field('page_builder')) {
    $page_builder = get_field('page_builder');
    foreach ($page_builder as $key => $section) {
        include('inc/inc-' . $section['acf_fc_layout'] . '.php');
    }
} else {
    echo '<div class="container" style="text-align:center;">';
    echo '<h2 style="margin:20px 0 20px;display:inline-block;">';
    echo 'Fields Not Founds';
    echo '</h2>';
    echo '</div>';
}

?>

    <section class="full-section blogs_section">
        <div class="container">
            <div class="category-filter-wrapper">
                <select name="post_cat" id="post_cat">
                    <option value="">All Categories</option>
                    <?php
                    $cats = get_terms(array('exclude' => [44], 'taxonomy' => 'category'));
                    if (!empty($cats)) {
                        foreach ($cats as $cat) {
                            echo '<option value="' . $cat->term_id . '">' . $cat->name . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="blog-wrapper">
                <?php
                $all = get_posts(array(
                    'posts_per_page' => -1,
                    'category__not_in' => array(44)
                ));

                // print_r();

                if (empty($_POST['post_per']) && empty($_POST['post_less'])) {
                    $ppp = 9;
                }

                if (!empty($_POST['post_per'])) {
                    if ($_POST['post_per'] >= count($all)) {
                        $ppp = count($all);
                    } else {
                        $ppp = $_POST['post_per'];
                    }

                }
                if (!empty($_POST['post_less']) && $_POST['post_less'] > 0) {
                    $ppp = $_POST['post_less'];
                }
                $args = array(
                    'posts_type' => 'posts',
                    'posts_per_page' => $ppp,
                    'posts_status' => 'publish',
                );
                if (!empty($_POST['term_id'])) {
                    $term_id = $_POST['term_id'];
                    $args['cat'] = $term_id;
                }
                $parent_slug = get_queried_object()->post_name;
                $default = new WP_Query ($args);
                $posts = $default->get_posts();
                usort($posts, "sort_by_date");
                foreach ($posts as $d) {
                    echo '<div class="post-box v3">';
                    echo '<a href="' . home_url() . '/' . $parent_slug . '/' . $d->post_name . '/">';
                    // $thumb = get_the_post_thumbnail_url($d);
                    $thumb = get_post_thumbnail_id($d);
                    $alt = get_post_meta($thumb, '_wp_attachment_image_alt', true);
                    // $demo = wp_get_attachment_image_url(17233);
                    // echo '<div class="image" style="background: url(' . ($thumb ? $thumb : $demo) . ')no-repeat center/cover;"></div>';
                    echo '<div class="image">';
                        if($thumb){
                            echo wp_get_attachment_image($thumb, 'blog_size', array( 'alt' => $alt ));
                        } else {
                            echo wp_get_attachment_image(17233, 'blog_size', array( 'alt' => $alt ));
                        }
                    echo '</div>'; //image
                    echo '</a>';
                    echo '<div class="cont">';
                    $date = get_field('post_date', $d);
                    if ($date) {
                        echo '<p class="date">' . $date . '</p>';
                    } else {
                        echo '<p class="date">' . get_the_time('d, F, Y', $d) . '</p>';
                    }
                    $cate = get_the_category($d->ID);
                    echo '<p class="cat">';
                    echo '<strong>Categories: </strong>';
                    foreach ($cate as $key => $value) {
                        echo '<a href="' . esc_attr(esc_url(get_category_link($value->term_id))) . '" class="cat">' . $value->name . '</a>';
                    }
                    echo '<h2><a href="' . get_the_permalink($d) . '">' . get_the_title($d) . '</a></h2>';
                    $author = get_field('post_auther', $d);
                    if ($author) {
                        echo '<p class="author">By ' . $author . '</p>';
                    } else {
                        echo '<p class="author">By ' . get_the_author_meta('user_email', get_the_author_ID($d)) . '</p>';
                    }
                    echo '<a class="btn light_green" href="' . home_url() . '/' . $parent_slug . '/' . $d->post_name . '/">Read More</a>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
                <?php
                echo '<div class="more-posts">';
                if ($default->max_num_pages > 1) {
                    echo '<a id="showmore" data="' . $ppp . '" class="btn light_green" href="javascript:;">' . ($ppp >= count($all) ? 'Show Less' : 'Show More') . '</a>';
                }
                echo '</div>';
                ?>


            </div>
        </div> <!-- container -->
    </section><!-- content-wrapper -->

    <script type="text/javascript">
        jQuery(document).ready(function ($) {

            $(document).on('change', '#post_cat', function (e) {
                e.preventDefault();
                let term_id = $(this).val();
                let test = 9;
                let less = 0;
                $.ajax({
                    method: 'POST',
                    url: window.location.href,
                    data: {
                        term_id: term_id,
                        post_per: test,
                        post_less: less,
                    },
                    success: function (data) {
                        $('.blog-wrapper').html($('.blog-wrapper', data).html());
                        $('.more-posts').html($('.more-posts', data).html());
                    },
                    error: function (data) {
                        console.log('Sorry Not Found Data');
                    }
                });

            })

            $(document).on('click', '#showmore', function () {
                var test = 9;
                var less = 0;
                let term_id = $('#post_cat').val();
                var show = $(this).text();
                var value = $(this).attr('data');
                if ($('#showmore').text() != 'Show More') {
                    if (value > 9) {
                        value = 9;
                        test = 0;
                    }
                    less = value;
                } else if ($('#showmore').text() == 'Show More') {
                    test = 9 + Number(value);
                    value = 0;
                }
                $.ajax({
                    method: 'POST',
                    url: window.location.href,
                    data: {
                        term_id: term_id,
                        post_per: test,
                        post_less: less,
                    },
                    success: function (data) {
                        $('.blog-wrapper').html($('.blog-wrapper', data).html());
                        $('.more-posts').html($('.more-posts', data).html());
                    },
                    error: function (data) {
                        console.log('Sorry Not Found Data');
                    }
                });
            });
        });
    </script>

<?php get_footer(); ?>