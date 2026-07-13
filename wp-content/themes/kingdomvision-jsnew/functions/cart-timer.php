<?php
/**
 * Cart Timer Settings
 *
 * Provides admin configuration for the booking cart expiration timer
 * per accommodation, and exposes dynamic timer config to the frontend.
 *
 * Defaults:
 *   - RoomBoss (or hybrid with roomboss flag) : 10 minutes
 *   - BedBank                                 : 6 hours
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('kv_register_cart_timer_acf_fields')) {
    /**
     * Programmatically register cart timer fields on the accommodation CPT.
     *
     * ACF will only register these locally when no JSON field group with the
     * same key exists. The bundled acf-json/group_6a1b2c3d4e5f6.json provides
     * the same fields via ACF's JSON sync, so this acts as a safety net.
     */
    function kv_register_cart_timer_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Skip if the JSON version is already registered.
        if (function_exists('acf_get_field_group')) {
            $existing = acf_get_field_group('group_6a1b2c3d4e5f6');
            if ($existing) {
                return;
            }
        }

        acf_add_local_field_group(array(
            'key'      => 'group_6a1b2c3d4e5f6',
            'title'    => 'Cart Timer Settings',
            'fields'   => array(
                array(
                    'key'   => 'field_6a1b2c3d4e5f7',
                    'label' => 'Cart Timer Duration (Minutes)',
                    'name'  => 'acc_cart_timer_minutes',
                    'type'  => 'number',
                    'instructions' => 'How long (in minutes) should the cart be held before expiring? Used for RoomBoss and hybrid properties. Leave empty to use the default of 10 minutes.',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field'   => 'field_69724c24a61dc',
                                'operator'=> '==',
                                'value'   => '1',
                            ),
                        ),
                    ),
                    'wrapper' => array('width' => '50'),
                    'default_value' => 10,
                    'min'  => 1,
                    'max'  => 1440,
                    'step' => 1,
                    'append' => 'min',
                ),
                array(
                    'key'   => 'field_6a1b2c3d4e5f8',
                    'label' => 'Cart Timer Duration (Hours) - BedBank',
                    'name'  => 'acc_cart_timer_bedbank_hours',
                    'type'  => 'number',
                    'instructions' => 'How long (in hours) should the cart be held for BedBank properties? Leave empty to use the default of 6 hours.',
                    'wrapper' => array('width' => '50'),
                    'default_value' => 6,
                    'min'  => 1,
                    'max'  => 168,
                    'step' => 1,
                    'append' => 'hrs',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'accommodation',
                    ),
                ),
            ),
            'menu_order' => 5,
            'position'   => 'side',
            'active'     => true,
        ));
    }
    add_action('acf/init', 'kv_register_cart_timer_acf_fields', 20);
}

if (!function_exists('kv_get_cart_timer_config')) {
    /**
     * Resolve timer configuration (in seconds) for a given accommodation.
     *
     * @param int|null $accommodation_post_id WordPress accommodation post ID.
     * @return array{duration:int, is_bedbank:bool, is_roomboss:bool, expires_at:int}
     */
    function kv_get_cart_timer_config($accommodation_post_id = null) {
        $defaults = array(
            'roomboss_minutes' => 10,
            'bedbank_hours'    => 12,
        );

        $is_roomboss = false;
        if (!empty($accommodation_post_id)) {
            $is_roomboss = (bool) get_field('is_roomboss', $accommodation_post_id);
        }

        $duration = 0;

        if ($is_roomboss) {
            $minutes = get_field('acc_cart_timer_minutes', $accommodation_post_id);
            // $minutes = 1;
            if ($minutes === null || $minutes === '' || $minutes === false) {
                $minutes = $defaults['roomboss_minutes'];
            }
            $duration = max(1, (int) $minutes) * MINUTE_IN_SECONDS;
        } else {
            // $hours = get_field('acc_cart_timer_bedbank_hours', $accommodation_post_id);
            $hours = 12;
            if ($hours === null || $hours === '' || $hours === false) {
                $hours = $defaults['bedbank_hours'];
            }
            $duration = max(1, (int) $hours) * HOUR_IN_SECONDS;
        }

        return array(
            'duration'    => $duration,
            'is_roomboss' => $is_roomboss,
            'is_bedbank'  => !$is_roomboss,
        );
    }
}

if (!function_exists('kv_localize_cart_timer_defaults')) {
    /**
     * Expose the default timer configuration to the frontend.
     *
     * Hooked into the theme's localized data so the JS module can fall back
     * to safe defaults when a per-item timer is not yet present (or when the
     * accommodation post ID cannot be resolved client-side).
     */
    function kv_localize_cart_timer_defaults() {
        // Only emit defaults; the per-accommodation duration is fetched via
        // an AJAX call from the JS once it knows the property ID.
        $defaults = array(
            'roomboss_seconds' => 10 * MINUTE_IN_SECONDS,
            'bedbank_seconds'  => 6  * HOUR_IN_SECONDS,
        );

        wp_localize_script('kv-script', 'kvCartTimer', $defaults);
    }
    add_action('wp_enqueue_scripts', 'kv_localize_cart_timer_defaults', 25);
}

if (!function_exists('kv_ajax_get_cart_timer_config')) {
    /**
     * AJAX: return timer config (in seconds) for a given accommodation.
     *
     * Expects `property_id` (booking system property ID).
     */
    function kv_ajax_get_cart_timer_config() {
        try {
            $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';

            if ($property_id === '') {
                wp_send_json_error(array(
                    'message' => 'property_id is required',
                    'code'    => 'missing_property_id',
                ), 400);
            }

            $wp_post_id = function_exists('get_post_id_by_typeId')
                ? get_post_id_by_typeId($property_id, 'accommodation')
                : 0;

            $config = kv_get_cart_timer_config($wp_post_id);

            wp_send_json_success(array(
                'duration'    => $config['duration'],
                'is_roomboss' => $config['is_roomboss'],
                'is_bedbank'  => $config['is_bedbank'],
                'property_id' => $property_id,
            ));
        } catch (Exception $e) {
            error_log('kv_ajax_get_cart_timer_config error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Unable to resolve timer config',
                'code'    => 'unexpected_error',
            ), 500);
        }
    }
    add_action('wp_ajax_kv_get_cart_timer_config', 'kv_ajax_get_cart_timer_config');
    add_action('wp_ajax_nopriv_kv_get_cart_timer_config', 'kv_ajax_get_cart_timer_config');
}

if (!function_exists('kv_ajax_refresh_cart_availability')) {
    /**
     * AJAX: re-check availability for a RoomBoss cart item.
     *
     * Receives an array of items with check-in / check-out / room / rate plan
     * and returns whether each is still bookable on the booking system.
     */
    function kv_ajax_refresh_cart_availability() {
        try {
            $items_raw = isset($_POST['items']) ? wp_unslash($_POST['items']) : '';
            $items     = json_decode($items_raw, true);

            if (!is_array($items) || empty($items)) {
                wp_send_json_error(array(
                    'message' => 'items is required and must be a non-empty array',
                    'code'    => 'missing_items',
                ), 400);
            }

            $available = array();
            $unavailable_items = array();

            foreach ($items as $idx => $item) {
                $property_id   = isset($item['property_id']) ? (int) $item['property_id'] : 0;
                $room_id       = isset($item['room_id']) ? (int) $item['room_id'] : 0;
                $rate_plan_id  = isset($item['rate_plan_id']) ? sanitize_text_field($item['rate_plan_id']) : '';
                $check_in_raw  = isset($item['check_in']) ? sanitize_text_field($item['check_in']) : '';
                $check_out_raw = isset($item['check_out']) ? sanitize_text_field($item['check_out']) : '';
                $adults        = isset($item['adults']) ? (int) $item['adults'] : 1;
                $children      = isset($item['children']) ? (int) $item['children'] : 0;
                $infants       = isset($item['infants']) ? (int) $item['infants'] : 0;

                if (!$property_id || !$room_id || $check_in_raw === '' || $check_out_raw === '') {
                    $unavailable_items[] = array(
                        'index'   => $idx,
                        'reason'  => 'invalid_request',
                        'message' => 'Missing required fields for availability check.',
                    );
                    continue;
                }

                // Normalize dates for the booking system. The JS sends YYYY-MM-DD;
                // the booking system API expects d-M-Y (e.g. 02-Apr-2026).
                $check_in  = kv_normalize_roomboss_date($check_in_raw);
                $check_out = kv_normalize_roomboss_date($check_out_raw);

                if (!$check_in || !$check_out) {
                    $unavailable_items[] = array(
                        'index'   => $idx,
                        'reason'  => 'invalid_dates',
                        'message' => 'Invalid check-in or check-out date.',
                    );
                    continue;
                }

                $duration = kv_calculate_nights($check_in_raw, $check_out_raw);
                if ($duration <= 0) {
                    $unavailable_items[] = array(
                        'index'   => $idx,
                        'reason'  => 'invalid_date_range',
                        'message' => 'Check-out must be after check-in.',
                    );
                    continue;
                }

                $args = array(
                    'start_date'    => $check_in,
                    'end_date'      => $check_out,
                    'duration'      => $duration,
                    'propertyIds'   => array($property_id),
                    'maxPersons'    => max(1, $adults + $children + $infants),
                    'totalAdults'   => $adults,
                    'totalChildren' => $children,
                    'totalInfants'  => $infants,
                    'adults'        => array($adults),
                    'children'      => array($children),
                    'infants'       => array($infants),
                    'offset'        => 0,
                    'limit'         => 1,
                );

                $bs_args = function_exists('kv_booking_system_filter_args')
                    ? kv_booking_system_filter_args(KV_BS_authToken, $args)
                    : array();

                if (empty($bs_args)) {
                    $unavailable_items[] = array(
                        'index'   => $idx,
                        'reason'  => 'bs_args_failed',
                        'message' => 'Could not build booking system request.',
                    );
                    continue;
                }

                $url = KV_BOOKING_SYSTEM_BASE . '/api/quotation-filteration';
                $response = wp_remote_post($url, $bs_args);

                if (is_wp_error($response)) {
                    $unavailable_items[] = array(
                        'index'   => $idx,
                        'reason'  => 'request_failed',
                        'message' => $response->get_error_message(),
                    );
                    continue;
                }

                $http_code = wp_remote_retrieve_response_code($response);
                $body      = json_decode(wp_remote_retrieve_body($response), true);

                if ($http_code !== 200 || !is_array($body)) {
                    $unavailable_items[] = array(
                        'index'   => $idx,
                        'reason'  => 'api_error',
                        'message' => 'Booking system returned HTTP ' . $http_code,
                    );
                    continue;
                }
                
                $still_available = kv_roomboss_unit_available($body, $property_id, $room_id, $rate_plan_id);
                // pre($still_available, 0);
                // pre($body, 1);

                if ($still_available) {
                    $available[] = array(
                        'index'  => $idx,
                        'room_id' => $room_id,
                    );
                } else {
                    $unavailable_items[] = array(
                        'index'   => $idx,
                        'reason'  => 'no_availability',
                        'room_id' => $room_id,
                        'message' => 'The room or rate plan is no longer available for the selected dates.',
                    );
                }
            }

            wp_send_json_success(array(
                'available'        => $available,
                'unavailable'      => $unavailable_items,
                'all_available'    => empty($unavailable_items),
            ));
        } catch (Exception $e) {
            error_log('kv_ajax_refresh_cart_availability error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Unable to refresh availability',
                'code'    => 'unexpected_error',
            ), 500);
        }
    }
    add_action('wp_ajax_kv_refresh_cart_availability', 'kv_ajax_refresh_cart_availability');
    add_action('wp_ajax_nopriv_kv_refresh_cart_availability', 'kv_ajax_refresh_cart_availability');
}

if (!function_exists('kv_normalize_roomboss_date')) {
    /**
     * Convert a date string into the format RoomBoss expects (d-M-Y).
     *
     * Accepts Y-m-d, d/m/Y, d-M-Y, m/d/Y.
     */
    function kv_normalize_roomboss_date($date_str) {
        $date_str = trim((string) $date_str);
        if ($date_str === '') {
            return '';
        }

        $formats = array('Y-m-d', 'd/m/Y', 'd-M-Y', 'm/d/Y');
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $date_str);
            if ($dt) {
                return $dt->format('d-M-Y');
            }
        }

        $ts = strtotime($date_str);
        if ($ts) {
            return date('d-M-Y', $ts);
        }
        return '';
    }
}

if (!function_exists('kv_calculate_nights')) {
    /**
     * Calculate number of nights between two Y-m-d or d/m/Y date strings.
     */
    function kv_calculate_nights($check_in, $check_out) {
        $formats = array('Y-m-d', 'd/m/Y', 'd-M-Y');
        $in = null;
        $out = null;
        foreach ($formats as $fmt) {
            if (!$in) {
                $tmp = DateTime::createFromFormat($fmt, $check_in);
                if ($tmp) { $in = $tmp; }
            }
            if (!$out) {
                $tmp = DateTime::createFromFormat($fmt, $check_out);
                if ($tmp) { $out = $tmp; }
            }
        }
        if (!$in || !$out) {
            return 0;
        }
        $diff = $in->diff($out);
        return (int) $diff->days;
    }
}

if (!function_exists('kv_roomboss_unit_available')) {
    /**
     * Inspect the booking system response to check whether a given room/rate
     * plan combination is still available.
     *
     * @param array  $body          Booking system response body.
     * @param int    $property_id   Property ID.
     * @param int    $room_id       Room ID (RoomId).
     * @param string $rate_plan_id  Rate plan ID (optional).
     */
    function kv_roomboss_unit_available($body, $property_id, $room_id, $rate_plan_id = '') {
        if (empty($body['properties']) || !is_array($body['properties'])) {
            return false;
        }

        foreach ($body['properties'] as $property) {
            if (empty($property['PropertyId']) || (int) $property['PropertyId'] !== (int) $property_id) {
                continue;
            }
            if (empty($property['Units']) || !is_array($property['Units'])) {
                return false;
            }

            foreach ($property['Units'] as $unit) {
                if (empty($unit['Rooms']) || !is_array($unit['Rooms'])) {
                    continue;
                }
                foreach ($unit['Rooms'] as $room) {
                    if (empty($room['ActualRoomId'])) {
                        continue;
                    }
                    if ((int) $room['ActualRoomId'] !== (int) $room_id) {
                        continue;
                    }
                    if ($rate_plan_id !== '' && isset($room['ratePlanId']) && (string) $room['ratePlanId'] !== (string) $rate_plan_id) {
                        continue;
                    }
                    return true;
                }
            }
            return false;
        }
        return false;
    }
}

if (!function_exists('kv_ajax_get_property_url')) {
    /**
     * AJAX: return the WordPress permalink for the accommodation that
     * corresponds to a given booking-system property ID (hotel_type_id).
     *
     * Used by the floating cart icon to redirect customers on desktop.
     */
    function kv_ajax_get_property_url() {
        try {
            $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';

            if ($property_id === '') {
                wp_send_json_error(array(
                    'message' => 'property_id is required',
                    'code'    => 'missing_property_id',
                ), 400);
            }

            if (!function_exists('get_post_id_by_typeId')) {
                wp_send_json_error(array(
                    'message' => 'Helper function get_post_id_by_typeId is unavailable',
                    'code'    => 'helper_unavailable',
                ), 500);
            }

            $wp_post_id = get_post_id_by_typeId($property_id, 'accommodation');
            if (empty($wp_post_id)) {
                wp_send_json_error(array(
                    'message' => 'No accommodation found for the given property_id',
                    'code'    => 'not_found',
                ), 404);
            }

            $permalink = get_permalink($wp_post_id);

            wp_send_json_success(array(
                'property_id' => $property_id,
                'wp_post_id'  => (int) $wp_post_id,
                'url'         => $permalink,
            ));
        } catch (Exception $e) {
            error_log('kv_ajax_get_property_url error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Unable to resolve property URL',
                'code'    => 'unexpected_error',
            ), 500);
        }
    }
    add_action('wp_ajax_kv_get_property_url', 'kv_ajax_get_property_url');
    add_action('wp_ajax_nopriv_kv_get_property_url', 'kv_ajax_get_property_url');
}
