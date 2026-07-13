<?php
$link = $section['link'];
$link_url = $link['url'];
$link_title = $link['title'];
$link_target = $link['target'] ? $link['target'] : '_self';
$manual = $section['reviews_info'];
$count = $section['rating_count'];
$title = $section['title'];
if (!$manual) {
    $average = get_option('kv_company_average_rating') ?: '4.81';
    $total = get_option('kv_company_total_reviews') ?: '632';
    $count = round($average, 1) . ' Average ' . $total . ' Reviews';
    $title = get_option('kv_company_review_title') ?: 'Excellent';
}
echo '<section class="full-section client_reviews">';
echo '<div class="container">';
echo '<div class="cr_cover">';
echo '<div class="cr_left">';
if ($title) {
    printf('<h2>%s</h2>', $title);
}
echo '<span class="five-star"></span>';
if ($count) {
    printf('<h3>%s</h3>', $count);
}
if ($link):
    printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_target, $link_title);
endif;
echo '</div>'; //cr_left
echo '<div class="cr_right">';
$category = $section['reviews_category'] ?: '';
$perpage = $section['reviews_per_page'] ?: '';
echo do_shortcode('[reviews category="' . $category . '" perpage="' . $perpage . '"]');
echo '</div>'; //cr_right
echo '</div>'; //cr_cover
echo '</div>';
echo '</section>';
?>