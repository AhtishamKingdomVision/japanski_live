<?php

add_shortcode('cat_listing', 'cat_listing_fk');

function cat_listing_fk($atts)

{

    extract(shortcode_atts(array(

        'cat_id' => $cat_id

    ), $atts));



    ob_start(); ?>

    <div class="filter-area">

        <form id="main_form" class="main_form" method="POST" action="#">

            <div class="_search accomodation_search">

                <input type="text" class="search_field" name="searches" id="searches" placeholder="Accommodation Name">

            </div>

            <div class="rooms_search accomodation_search">

                <select class="rooms_slct accomodation-select2" name="rooms_field[]" multiple id="rooms_field" data-placeholder="No. of Bedrooms">

                    <?php

                    $choices = get_field_object('field_6242e0b10ba10')['choices'];

                    if (!empty($choices)) {

                        foreach ($choices as $key => $value) {

                    ?>

                            <option value="<?= $key; ?>"><?= $value; ?></option>

                    <?php }

                    } ?>

                </select>

            </div>

            <div class="area_search accomodation_search">

                <select class="select_area accomodation-select2" name="area[]" multiple id="area" data-placeholder="Area">

                    <?php

                    $area = get_term_meta($cat_id, 'area');

                    if (!empty($area)) {

                        foreach ($area[0] as $key => $value) {

                    ?>

                            <option value="<?= $value; ?>"><?= $value; ?></option>

                    <?php }

                    } ?>

                </select>

            </div>

            <div class="type_search accomodation_search">

                <select class="select_type accomodation-select2" name="type[]" multiple id="type" data-placeholder="Type">

                    <?php

                    $type = get_term_meta($cat_id, 'type');

                    if (!empty($type)) {

                        foreach ($type[0] as $key => $value) {

                    ?>

                            <option value="<?= $value; ?>"><?= $value; ?></option>

                    <?php }

                    } ?>

                </select>

            </div>

            <div class="slidecontainer accomodation_search">

                <input type="range" min="1" max="300000" class="slider" value="300000" id="price">

                <p>Max Price per unit per night: <span id="demo"></span></p>

            </div>

            <input type="submit" name="Search" value="Search">

        </form>

    </div>

<?php

    wp_reset_postdata();

    return '' . ob_get_clean();

}

