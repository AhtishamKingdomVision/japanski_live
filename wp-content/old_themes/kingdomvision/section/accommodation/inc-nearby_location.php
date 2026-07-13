<?php

$locations = $section['location'] ?? [];
$main_lat = get_field('accomodation_details_acc_latitude') ?? 42.858377;
$main_lng = get_field('accomodation_details_acc_longitude') ?? 140.705364;
$main_title = get_the_title() ?? '';
$main_address = get_field('accomodation_details_address') ?? '4-chōme-3-1-7 Niseko Hirafu 1 Jō, Kutchan, Hokkaido, Japan';

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
                $km = $loc['km'] ?? '';
                $lat = $loc['latitude'] ?? '';
                $lng = $loc['longitude'] ?? '';

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
    </script>';
}

echo '</div>';
echo '</section>';
?>

<!-- =========================
     LEAFLET FILES
========================= -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
