<?php

add_shortcode('cat_listing_1', 'cat_listing_1_fk');

function cat_listing_1_fk($atts)
{

    extract(shortcode_atts(array(
        // cat_id is extracted from atts
        'cat_id' => $cat_id

    ), $atts));


    ob_start(); ?>

    <div class="filter-area hamza_filter">

        <form id="main_form" class="main_form" method="POST" action="#" data-page="1" >

            <div class="_search_1 accomodation_search">

                <input type="text" class="search_field" name="searches_1" id="searches_1" placeholder="Accommodation Name">

            </div>

            <div class="rooms_search_1 accomodation_search">

                <select class="rooms_slct accomodation-select2" name="rooms_field[]" multiple id="rooms_field_1" data-placeholder="No. of Bedrooms">

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

            <?php
               $hk_area_value = get_post_meta( get_the_ID(), 'page_builder_6_area', true );
                if($hk_area_value == 'choose an area'){
                    $area_container = '';
                }
                else{
                    $area_container = '<div class="area_search accomodation_search">
                                    <input type="text" id="area_1" readonly name="area[]" value="'.$hk_area_value.'">
                                </div>';
                } 

                echo $area_container;

                $hk_type_value = get_post_meta( get_the_ID(), 'page_builder_6_type', true );
                if( @$_GET['_devv'] == 'hamza' ){
                    pre( $hk_type_value, 1);
                }
                // echo 'hk_type_value'.$hk_type_value;
                if(empty($hk_type_value)){
                    $type_container = '';
                }
                else{ ?>
                    <div class="type_search_1 accomodation_search">
                        <select class="select_type accomodation-select2" disabled name="type[]" multiple id="type_1" data-placeholder="Type">
                            <?php
                            $type = explode(',', $hk_type_value);
                            foreach ($type as $key => $value) {
                                if(!empty($value)){
                                    echo '<option selected value="'.$value.'">'.$value.'</option>';
                                }
                            }?>
                        </select>
                    </div>
            <?php } ?>

<!--             <div class="slidecontainer accomodation_search">

                <input type="range" min="1" max="400000" class="slider" id="price_1">

                <p>Max price per unit per night: <span id="demo_1"></span></p>

            </div> -->

            <input type="submit" name="Search" value="Search" data-list='new_listing'>

        </form>

        <!-- <button class="js-programmatic-close button" id="accomo">Submit</button> -->

    </div>

<?php

    wp_reset_postdata();

    return '' . ob_get_clean();

}

