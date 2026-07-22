<?php
$section = [];

$acc_builder = get_field('accommodation_builder', get_the_ID() );
foreach ($acc_builder as $key => $builder_section) {
    if( $builder_section['acf_fc_layout'] == 'nearby_location' ){
        $section = $builder_section;
    }
}

$locations = $section['location'] ?? [];
$main_lat = get_field('accomodation_details_acc_latitude') ?? 42.858377;
$main_lng = get_field('accomodation_details_acc_longitude') ?? 140.705364;
$main_title = get_the_title() ?? '';
$main_address = get_field('accomodation_details_address') ?? '4-chōme-3-1-7 Niseko Hirafu 1 Jō, Kutchan, Hokkaido, Japan';

if ( ! function_exists( 'nearby_location_distance_km' ) ) {
    // Straight-line fallback, used when the OpenRouteService call is unavailable or fails.
    function nearby_location_distance_km( $lat1, $lng1, $lat2, $lng2 ) {
        if ( $lat1 === '' || $lng1 === '' || $lat2 === '' || $lng2 === '' ) {
            return '';
        }

        $earth_radius = 6371; // km

        $lat1 = deg2rad( floatval( $lat1 ) );
        $lng1 = deg2rad( floatval( $lng1 ) );
        $lat2 = deg2rad( floatval( $lat2 ) );
        $lng2 = deg2rad( floatval( $lng2 ) );

        $delta_lat = $lat2 - $lat1;
        $delta_lng = $lng2 - $lng1;

        $a = sin( $delta_lat / 2 ) ** 2 + cos( $lat1 ) * cos( $lat2 ) * sin( $delta_lng / 2 ) ** 2;
        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return round( $earth_radius * $c, 1 );
    }
}

if ( ! function_exists( 'nearby_location_road_distances_km' ) ) {
    /**
     * Batches all locations into a single OpenRouteService Matrix API call
     * and returns road distances (km) indexed the same as $locations.
     * Returns null on any failure so the caller can fall back per-item.
     */
    function nearby_location_road_distances_km( $main_lat, $main_lng, $locations, $api_key ) {
        if ( empty( $api_key ) || empty( $locations ) ) {
            return null;
        }

        // ORS expects coordinates as [lng, lat]. Index 0 is always the main location.
        $coordinates = [ [ (float) $main_lng, (float) $main_lat ] ];

        foreach ( $locations as $loc ) {
            $coordinates[] = [ (float) ( $loc['longitude'] ?? 0 ), (float) ( $loc['latitude'] ?? 0 ) ];
        }

        $response = wp_remote_post( 'https://api.openrouteservice.org/v2/matrix/driving-car', [
            'headers' => [
                'Authorization' => $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'locations'    => $coordinates,
                'sources'      => [ 0 ],
                'destinations' => range( 1, count( $locations ) ),
                'metrics'      => [ 'distance' ],
                'units'        => 'km',
            ] ),
            'timeout' => 8,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[ORS Matrix] wp_remote_post error: ' . $response->get_error_message() );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            error_log( '[ORS Matrix] HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        cf_log( $data, 'data_resp' );
        $row  = $data['distances'][0] ?? null;

        if ( ! is_array( $row ) ) {
            error_log( '[ORS Matrix] Unexpected response shape: ' . wp_remote_retrieve_body( $response ) );
            return null;
        }

        return array_map( function ( $km ) {
            return is_numeric( $km ) ? round( $km, 1 ) : null;
        }, $row );
    }
}

$ors_api_key = trim( get_field( 'openrouteservice_api_key', 'option' ) ?: '' );
$road_km_cache_key = 'nearby_location_road_km_' . get_the_ID();
$road_km = get_transient( $road_km_cache_key );

// if ( false === $road_km ) {
    $road_km = nearby_location_road_distances_km( $main_lat, $main_lng, $locations, $ors_api_key );

    if ( is_array( $road_km ) ) {
        set_transient( $road_km_cache_key, $road_km, WEEK_IN_SECONDS );
    }
// }

echo '<section class="full-section nearby_location" '.BackgroundFromSection($section).'>';
echo '<div class="container">';
echo TitleFromSection($section);

if (!empty($locations)) {

    echo '<div class="nearby-wrapper">';

    /* =========================
       MAP AREA
    ========================= */
    echo '<div class="nearby-map-area">';
        echo '<div id="nearby-map" class="nearby-map"></div>';

            echo '
                <div id="main-location-box" class="main-location-box">
                    <h4>' . esc_html($main_title) . '</h4>
                    <p>' . esc_html($main_address) . '</p>
                </div>
            ';
        
        echo '<button type="button" class="location-info-btn" id="open-location-info"> More Location Info </button>';
    echo '</div>';

    /* =========================
       SIDEBAR LIST
    ========================= */
    echo '<div class="nearby-list-box">';
        echo '<h3 class="nearby-title">Closest Landmarks</h3>';
        // echo '<div class="nearby-divider"></div>';
        echo '<ul class="nearby-list">';

            $mapArray = [];

            foreach ($locations as $i => $loc) {

                $title = $loc['title'] ?? '';
                $lat = $loc['latitude'] ?? '';
                $lng = $loc['longitude'] ?? '';
                $km = is_array( $road_km ) ? ( $road_km[ $i ] ?? null ) : null;

                if ( $km === null ) {
                    $km = nearby_location_distance_km( $main_lat, $main_lng, $lat, $lng );
                }

                echo '
                    <li class="nearby-item" data-index="' . $i . '">
                        <span>' . esc_html($title) . '</span>
                        <span class="distance">' . esc_html($km) . ' km</span>
                    </li>';

                $mapArray[] = [
                    'title' => $title,
                    'km'    => $km,
                    'lat'   => $lat,
                    'lng'   => $lng,
                ];
            }

        echo '</ul>';
    echo '</div>'; // list

    echo '</div>'; // wrapper

    /* =========================
       PASS DATA TO JS
    ========================= */
    echo '<script>
        var nearbyData = ' . json_encode($mapArray) . ';
        var mainLocation = {
            lat: ' . floatval($main_lat) . ',
            lng: ' . floatval($main_lng) . ',
            title: "' . esc_js($main_title) . '",
            address: "' . esc_js($main_address) . '"
        };

        if( jQuery("ul.nearby-list li.nearby-item").length < 1 ){
            jQuery("section.nearby_location").hide();
        }
        else{
            jQuery("section.nearby_location").show();
        }
    </script>';
}

echo '</div>';
echo '</section>';