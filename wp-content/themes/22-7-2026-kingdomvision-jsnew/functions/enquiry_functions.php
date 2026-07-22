<?php

//** Saad Work: 02-Mar-2026 **/

// Define field mappings as constants for easier maintenance

define( 'FORM_1_FIELD_MAP', [

    'first_name' => '40',

    'last_name' => '41',

    'email' => '2',

    'phone' => '3',

    //'resort_name' => '4',

    'resort_name' => '66',

    'check_in' => '5',

    'check_out' => '6',

    'adults' => '7',

    'bedrooms' => '9',

    'children' => '10',

    'infants' => '42',

    'property_name' => '39',

    'room_name' => '44',

    'preferences' => '11',

    //'type' => '8',

    'type' => '71',

    'country' => '33',  

    'zipcode' => '13',

    'utm_source' => '45',

    'utm_medium' => '46',

    'utm_campaign' => '47',

    'enquiry_type' => '50',

] );
define( 'FORM_1_CHILD_AGE_FIELDS', range( 51, 65 ) );

add_action( 'gform_after_submission_1', 'post_to_third_party', 10, 2 );

function post_to_third_party( $entry, $form ) {

    // Extract query parameters safely

    $query_param = get_utm_query_string();



    $is_stay_user = !is_user_logged_in() || get_current_user_id() != 14 ? true : false;

    $api_url = KV_BOOKING_SYSTEM_BASE . '/api/v1/third-party-enquiry';

    // pre( $api_url );

    $api_url .= $query_param;

    // $endpoint_urls = [

    //     $api_url

    // ];

        // 'https://trip.japanskiexperience.com/api/v1/third-party-enquiry' . $query_param,;

    // Build request body

    $body = build_third_party_body( $entry );

    if ( ! $body ) {

        error_log( 'Failed to build third-party enquiry body for form submission.' );

        return;

    }



    // Check if check-in date is >= 2026-05-01

    // $check_in_raw = rgar( $entry, FORM_1_FIELD_MAP['check_in'] );

    $check_in_raw = @$body['check_in'];

    if ( ! is_check_in_date_valid( $check_in_raw ) ) {

        error_log( 'Check-in date (' . $check_in_raw . ') is before 2026-05-01. API request not sent.' );

        return;

    }



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

    // cf_log( $endpoint_urls, 'api_request_args' );

    cf_log( $args, 'api_request_args' );

    // Send to each endpoint

    // foreach ( $endpoint_urls as $url ) {

        $response = wp_remote_post( $api_url, $args );

        // Retry on 429 (Too Many Attempts) with backoff, honoring Retry-After if the API sends one
        $max_retries = 2;
        $attempt = 0;

        while ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 429 && $attempt < $max_retries ) {

            $attempt++;
            $retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
            $wait = $retry_after > 0 ? min( $retry_after, 10 ) : ( 2 * $attempt );

            cf_log( 'API rate limited (429) for ' . $api_url . '. Retrying in ' . $wait . 's (attempt ' . $attempt . ' of ' . $max_retries . ').', 'err__enquiry_api.txt', false, true );

            sleep( $wait );
            $response = wp_remote_post( $api_url, $args );

        }

        // Check for errors

        if ( is_wp_error( $response ) ) {

            cf_log( 'API Error for ' . $api_url . ': ' . $response->get_error_message(), 'err__enquiry_api.txt', false, true );

            // continue;

        }



        $status_code = wp_remote_retrieve_response_code( $response );

        $body_response = wp_remote_retrieve_body( $response );



        if ( $status_code < 200 || $status_code >= 300 ) {

            cf_log( 'API Request failed for ' . $api_url . ' (Status: ' . $status_code . '): ' . $body_response, 'err__enquiry_api.txt', false, true );

            /* send email to humza,kingdomvision@gmail.com & ahtisham.kv@gmail.com */

            wp_mail( 'humza.kingdomvision@gmail.com, ahtisham.kv@gmail.com', 'API Request Failed', 'API Request failed for ' . $api_url . ' (Status: ' . $status_code . '): ' . $body_response );

            // continue;

        }



        // Decode and log successful response

        $api_response = json_decode( $body_response, true );



        cf_log( $api_response, 'api_response.txt', true, true);

    // }

}



/**

 * Build the third-party enquiry body from form entry

 */

function build_third_party_body( $entry ) {

    $field_map = FORM_1_FIELD_MAP;
    $child_age_fields = FORM_1_CHILD_AGE_FIELDS;

    // Extract and format dates safely

    $check_in = rgar( $entry, $field_map['check_in'] );

    $check_out = rgar( $entry, $field_map['check_out'] );



    if ( ! $check_in || ! $check_out ) {

        error_log( 'Missing check-in or check-out date in form submission.' );

        return false;

    }



    $check_in = str_replace( '/', '-', $check_in );

    $check_out = str_replace( '/', '-', $check_out );

    $child_ages = [];
    foreach ( $child_age_fields as $field_id ) {
        $age = rgar( $entry, $field_id );
        if ( ! empty( $age ) ) {
            $child_ages[] = $age;
        }
    }

    $enquiry_type = rgar( $entry, $field_map['enquiry_type'] ) == 'product' ? 'property_enquiry' : 'generate_enquiry';

    cf_log( $entry, 'entry' );

    return [

        'full_name'      => rgar( $entry, $field_map['first_name'] ) .' '. rgar( $entry, $field_map['last_name'] ),

        'first_name'      => rgar( $entry, $field_map['first_name'] ),

        'last_name'      => rgar( $entry, $field_map['last_name'] ),

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

        'room_name'      => rgar( $entry, $field_map['room_name'] ),

        'assignees'      => [],

        'platform'       => 'wordpress',

        'utm_source'     => rgar( $entry, $field_map['utm_source'] ),

        'utm_medium'     => rgar( $entry, $field_map['utm_medium'] ),

        'utm_campaign'   => rgar( $entry, $field_map['utm_campaign'] ),

        'enquiry_type'   => $enquiry_type,

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



add_action( 'gform_pre_submission_1', 'hz_set_field_values' );

function hz_set_field_values( $form ) {



    $field_map = FORM_1_FIELD_MAP;

    

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

        $_POST['input_13'] = $customer['is_sent'];

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

    // $is_stay_user = !is_user_logged_in() || get_current_user_id() == 14;

    $is_stay_user = false;

 

    $api_url = KV_BOOKING_SYSTEM_BASE . '/api/v1/customers';

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

