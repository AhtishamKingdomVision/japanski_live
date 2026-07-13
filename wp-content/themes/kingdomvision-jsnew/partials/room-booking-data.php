<?php
/**
 * Room Booking Data Template
 * Displays available rooms with rate plans for booking system
 * 
 * Required $args keys:
 *   - grouped_rooms: Array of room data from API
 *   - rate_plans: Array of rate plan data (optional)
 *   - room_descriptions: Array of room descriptions keyed by room ID
 *   - dates: Array with 'check_in' and 'check_out' date strings
 *   - property_id: Numeric property/hotel ID
 */

// ✅ STEP 1: Parse and validate arguments with defaults
$args = wp_parse_args($args ?? [], [
    'grouped_rooms'      => [],
    'rate_plans'         => [],
    'room_descriptions'  => [],
    'dates'              => ['check_in' => '', 'check_out' => ''],
    'property_id'        => 0,
]);

// ✅ STEP 2: Extract and validate room data
$property = is_array($args['property']) ? $args['property'] : [];
$rooms = is_array($args['grouped_rooms']) ? $args['grouped_rooms'] : [];
$ratePlans = is_array($args['rate_plans']) ? $args['rate_plans'] : [];
$desc = is_array($args['room_descriptions']) ? $args['room_descriptions'] : [];
$propertyId = intval($args['property_id']);
$wp_property_id = get_post_id_by_typeId($propertyId, 'accommodation');
$property_permission = get_field('acc_booking_permission', $wp_property_id) ?: 'MyReservation';

// ✅ STEP 3: Extract and validate date information
$dates = is_array($args['dates']) ? $args['dates'] : [];
$startDisplay = sanitize_text_field($dates['check_in'] ?? '');
$endDisplay = sanitize_text_field($dates['check_out'] ?? '');
// $nights = isset($dates['nights']) ? intval($dates['nights']) : 0; nights was nopt there in dates

// getting dates difference from days

    // Create DateTime objects from a specific format (d/m/Y assumed)
    $dateStart = DateTime::createFromFormat('d/m/Y', $startDisplay);
    $dateEnd   = DateTime::createFromFormat('d/m/Y', $endDisplay);

    // Calculate the difference
    $interval = $dateStart->diff($dateEnd);

// Output the duration in days
$nights = $interval->days; // Output: 4 days

// ✅ STEP 4: Check if property uses roomboss or bedbank
$startDisplay = date_format_readable($startDisplay, 'Y-m-d', 'd/m/Y');
$endDisplay = date_format_readable($endDisplay, 'Y-m-d', 'd/m/Y');
$is_roomboss = !empty($propertyId) ? get_resort_id_by_property_id($propertyId) : false;
$accommodationSetting = @$property['AccommodationSetting'] ?? [];
$accommodationSetting = @$accommodationSetting[0] ?? [];
$supplierTerm = @$property['SupplierPaymentTerms'] ?? [];
$isHybridProperty = @$property['PropertySubType'] === 'hybrid';
// "SupplierPaymentTerms": {
    // "isDeposit": true,
    // "isPercentage": true,
    // "depositPercentage": 25,
    // "depositType": "deposit",
    // "depositAmount": 0,
    // "balanceDueAmount": 0,
    // "daysInAdv": 45,
    // "daysInAdvDate": "2026-10-17",
    // "isFixedDate": false,
    // "date_rules": []
// }

// var_dump($accommodationSetting['deposits_enabled']);
// pre($property, 0);
// pre($accommodationSetting, 0);
// var_dump($accommodationSetting);
if( !empty($accommodationSetting) ) {
    $supplierData = [
        'conditionType' => 'supplier discount',
        'isDeposit' => @$accommodationSetting['deposits_enabled'] == true, // true or false
        'isPercentage' => @$accommodationSetting['percentage_value'] == true || !empty(@$accommodationSetting['deposit_amount_per_room']), // true if deposit is percentage, false if fixed amount
        'depositPercentage' => floatval(@$accommodationSetting['deposit_amount_per_room'] ?? 0), // percentage value if isPercentage is true
        'depositType' => 'deposit', // default to 'deposit', can be 'full' if no deposit
        'isNightFee' => @$accommodationSetting['first_night_fee'] === 'yes', // yes or no
        'noOfNights' => intval(@$accommodationSetting['night_fee_value'] ?? 0), // number of nights to charge if first night fee is yes
        'depositAmount' => 0, // will be calculated based on percentage or fixed amount
        'balanceDueAmount' => 0, // will be calculated based on total price minus deposit
        'daysInAdv' => (int) @$accommodationSetting['days_in_advance'], // eg: 70
        'daysInAdvDate' => '', // will be calculated based on check-in date minus days in advance
        'isFixedDate' => @$accommodationSetting['is_fixed_date_enabled'] == true,
        'fixedDueDate' => @$accommodationSetting['fixed_deadline_date'] ?? '', // used if isFixedDate is true then balance due date is fixed to this date
        'balanceDueDate' => '', // will be calculated based on check-in date and days in advance,
        'date_rules' => is_array(@$accommodationSetting['date_rules']) ? @$accommodationSetting['date_rules'] : [],
    ];
}
else {
    $supplierData = [
        'conditionType' => 'no supplier discount',
        'isDeposit' => $supplierTerm['isDeposit'] == true, // true or false
        'isPercentage' => $supplierTerm['isPercentage'] == true,
        'depositPercentage' => floatval($supplierTerm['depositPercentage'] ?? 0), // percentage value if isPercentage is true
        'depositType' => !empty($supplierTerm['depositType']) ? sanitize_text_field($supplierTerm['depositType']) : 'deposit', // default to 'deposit', can be 'full' if no deposit
        'isNightFee' => false, // yes or no
        'noOfNights' => 0, // number of nights to charge if first night fee is yes
        'depositAmount' => 0,
        'balanceDueAmount' => 0,
        'daysInAdv' => (int) ($supplierTerm['daysInAdv'] ?? 0), // eg: 45
        'daysInAdvDate' => $supplierTerm['daysInAdvDate'] ?? '', // will be calculated based on check-in date minus days in advance
        'isFixedDate' => false,
        'fixedDueDate' => '',
        'balanceDueDate' => $supplierTerm['daysInAdvDate'] ?? '', // default to daysInAdvDate if not fixed date, will be recalculated if isFixedDate is true
        'date_rules' => [],
    ];
}

if($supplierData['depositPercentage'] == 100) {
    $supplierData = array_merge($supplierData, [
        'isDeposit' => false,
        'depositType' => 'full',
    ]);
}

// pre($supplierData, 0);

// ✅ Property context data for cart widget
$property_title = !empty($wp_property_id) ? get_the_title($wp_property_id) : sanitize_text_field($property['name'] ?? '');
$property_image = !empty($wp_property_id) ? (get_the_post_thumbnail_url($wp_property_id, 'medium') ?: '') : '';
$resort_name_cart = '';
if (!empty($wp_property_id)) {
    $resort_terms = get_the_terms($wp_property_id, 'accommodation-cat');
    if ($resort_terms && !is_wp_error($resort_terms)) {
        $resort_name_cart = str_replace(' Accommodation', '', sanitize_text_field($resort_terms[0]->name ?? ''));
    }
}
?>

<a href="javascript:;" class="btn back-to-rooms btn-outline">← Back</a>
<div class="rb-booking-layout">
    <div class="rb-left">

        <?php if (empty($rooms)) : ?>
            <p class="rb-empty">No rooms available for selected dates.</p>
        <?php else :
            // pre($rooms, 0);
            foreach ($rooms as $roomName => $room) :
                // ✅ Extract room identifiers with defaults
                $RoomId = ($room['RoomId'] ?? 0);
                $roomTypeId = $room['ActualRoomId'];
                
                // Skip rooms without ID
                if (empty($RoomId)) {
                    continue;
                }
                else if( $isHybridProperty && empty($room['ratePlanId']) ) {
                    // Skip bedbank rooms for hybrid properties
                    continue;
                }

                // ✅ Process and validate room image URL
                $room_gallery = [];
                if (!empty($room['RoomImages']) && is_array($room['RoomImages'])) {
                    foreach ($room['RoomImages'] as $imageData) {
                        if (!empty($imageData['path']) && filter_var($imageData['path'], FILTER_VALIDATE_URL)) {
                            $room_gallery[] = esc_url_raw($imageData['path']);
                        } elseif (!empty($imageData['path']) && !empty($imageData['file_name'])) {
                            $room_gallery[] = esc_url_raw(KV_BOOKING_SYSTEM_BASE . '/storage' . $imageData['path'] . $imageData['file_name']);
                        }
                    }
                }

                $img = !empty($room_gallery) ? $room_gallery[0] : '';

                // Fallback to placeholder if no image found
                if (empty($img)) {
                    $img = get_template_directory_uri() . '/images/placeholder-accomo.jpg';
                }

                // ✅ Extract room details with validation and defaults
                $maxGuests = intval($room['MaximumAdults'] ?? 0);
                $bedrooms = intval($room['numberBedrooms'] ?? 0);
                $baths = intval($room['numberBathrooms'] ?? 0);
                $maxInfants = intval($room['maxNumberInfants'] ?? 0);
                $accomPayTerm = $supplierData;
                ?>
                <div class="rb-room-card room-card"
                    data-bedroom="<?php echo esc_attr($bedrooms); ?>"
                    data-room-id="<?php echo esc_attr($RoomId); ?>"
                    >
                    <div class="rb-room-top">
                        <div class="rb-room-img room-booking-data">
                            <a href="<?php echo esc_url($img); ?>" data-fancybox="room-gallery-<?php echo $RoomId; ?>">
                                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($room['RoomName'] ?? 'Room'); ?>">
                                <div class="detail_btn">
                                    <img src="<?php echo get_template_directory_uri() . '/images/search-icon.png'?>" alt="Search">
                                </div>
                            </a>
                        </div>
                        <?php if (count($room_gallery) > 1) : ?>
                            <div class="hidden-gallery" style="display:none;">
                                <?php foreach ($room_gallery as $gal_key => $gal_url) :
                                    if ($gal_key === 0) continue; // Skip first image as it's the main trigger
                                    $gal_url = esc_url((string)$gal_url);
                                    if (empty($gal_url)) continue; ?>
                                    <a href="<?php echo $gal_url; ?>" data-fancybox="room-gallery-<?php echo $RoomId; ?>"></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="rb-room-meta">
                            <div class="tit_date">
                                <h3 class="rb-room-title"><?php echo esc_html($room['RoomName'] ?? ''); ?></h3>


                                <div class="rb-room-dates"></div>
                            </div>

                            <div class="rb-icons">
                                <span class="rb-icon guest"><?php echo esc_html($maxGuests); ?></span>
                                <span class="rb-icon bad"><?php echo esc_html($bedrooms); ?></span>
                                <span class="rb-icon bath"><?php echo esc_html($baths); ?></span>
                                <?php if (!empty($maxInfants) && $maxInfants > 0) : ?>
                                    <span class="rb-icon infant">
                                        <?php echo esc_html($maxInfants); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="rb-room-desc">
                                <!-- Std Guests: 2, Max Guests: 2; 1 Bedroom (King or Twin), 1 Bathroom; Size: 33-39 m²; Levels 2-5 -->
                                <?php echo esc_html($desc[$RoomId] ?? ''); ?>
                            </div>

                        </div>
                        <div class="rb-guests-filter">
                            <form class="rb-guests-form">

                                <!-- Adults -->
                                <div class="rb-guest-field">
                                    <label>Adults</label>
                                    <!-- <select class="rb-guest-input rb-adults" name="adults">
                                        <option value="">Adults</option>
                                        <?php //for ($i = 1; $i <= $maxGuests; $i++): ?>
                                            <option value="<?php //echo esc_attr($i); ?>"><?php //echo esc_html($i); ?></option>
                                        <?php //endfor; ?>
                                    </select> -->
                                    <input type="text" class="rb-guest-input rb-adults" name="adults" readonly>
                                </div>

                                <!-- Children -->
                                <div class="rb-guest-field">
                                    <label>Children</label>
                                    <!-- <select class="rb-guest-input rb-children" name="children">
                                        <option value="">Children</option>
                                        <?php //for ($i = 0; $i <= $maxGuests; $i++): ?>
                                            <option value="<?php //echo esc_attr($i); ?>"><?php //echo esc_html($i); ?></option>
                                        <?php //endfor; ?>
                                    </select> -->
                                    <input type="text" class="rb-guest-input rb-children" name="children" readonly>
                                </div>

                                <!-- Infants -->
                                <div class="rb-guest-field">
                                    <label>Infants</label>
                                    <!-- <select class="rb-guest-input rb-infants" name="infants">
                                        <option value="">Infants</option>
                                        <?php //for ($i = 0; $i <= $maxGuests; $i++): ?>
                                            <option value="<?php //echo esc_attr($i); ?>"><?php //echo esc_html($i); ?></option>
                                        <?php //endfor; ?>
                                    </select> -->
                                    <input type="text" class="rb-guest-input rb-infants" name="infants" readonly>
                                </div>

                            </form>
                            <a href="javascript:void(0)" class="open-room-search-popup">Change Guests</a>
                        </div>
                    </div>

                    <div class="rb-rateplans">
                        <?php
                            $allRooms = [$room];
                            foreach($room['children'] ?? [] as $childRoom) {
                                $allRooms[] = $childRoom;
                            }

                            // pre($allRooms, 0);
                            // pre(wp_json_encode($allRooms), 0);

                            foreach ($allRooms as $rateplanSeq => $room) : 
                                $isBedbank = empty(@$room['ratePlanId']);
                                // var_dump($isBedbank);
                                // ✅ Calculate prices with safe division
                                $priceRetail = (float) ($room['ActualPrice'] ?? 0);
                                // pre(wp_json_encode($room), 0);
                                $perStay = ($maxGuests > 0) ? ceil($priceRetail / $maxGuests) : $priceRetail;
                                $ratePlanName = wp_strip_all_tags($room['ratePlanName'] ?? 'Standard Rate');
                                
                                if( $isBedbank ) {
                                    $ratePlanId = null; // kept null so data-room-type stays 'bedbank'
                                    $ratePlanDesc = wp_strip_all_tags($room['ratePlanDescription'] ?? '');

                                    $globalDiscount = $room['globalDiscount'] ?? null;
                                    $accommodationDiscount = @$room['accommodationDiscount'] ?? [];
                                    if( !empty($accommodationDiscount) ) {
                                        // "accommodationDiscount": {
                                            // "override_invoice": false,
                                            // "booking_approval_percentage": "{\"days\": \"40\", \"type\": \"days\", \"percentage\": 30, \"remaining_amount_date\": \"01-Apr-2026\"}",
                                        // }

                                        // var_dump($accommodationDiscount['override_invoice']);
                                        $isOverride = $accommodationDiscount['override_invoice'] == true;
                                        if( $isOverride ) {
                                            $accomCondition = json_decode($accommodationDiscount['booking_approval_percentage'] ?? [], true);

                                            $daysInAdv = max(0, (int) $accomCondition['days']);
                                            $accomPayTerm = array_merge($accomPayTerm, [
                                                    'roomName' => esc_html($room['RoomName'] ?? ''),
                                                    'ratePlanName' => $ratePlanName,
                                                    'conditionType' => 'accommodation discount', // for override we can set a generic rate plan name as it's not coming from the supplier

                                                    'isDeposit' => true,
                                                    'depositType' => 'deposit', // default to 'deposit', can be 'full' if no deposit
                                                    'isPercentage' => true, // always percentage value for accommodation discount
                                                    'depositPercentage' => floatval( $accomCondition['percentage'] ?? $accomPayTerm['depositPercentage'] ), // percentage value if isPercentage is true
                                                    'daysInAdv' => $daysInAdv,
                                                    'isFixedDate' => $accomCondition['type'] == 'date', // if type is days and days is 0 then it's a fixed date
                                                    'fixedDueDate' => $accomCondition['remaining_amount_date'] ?? null, // used if isFixedDate is true then balance due date is fixed to this date
                                                    'balanceDueDate' => '', // will be calculated based on check-in date and days in advance,
                                                    'date_rules' => [],
                                                ]
                                            );
                                        }
                                        
                                    }
                                    else if( !empty($globalDiscount) ) {

                                        $isOverride = $globalDiscount['override_invoice'] == true;
                                        if( $isOverride ) {
                                            $daysInAdv = max(0, (int) $globalDiscount['days_before_departure']);
                                            $apply_only_before = !empty($globalDiscount['apply_only_before']) ? date('Y-m-d', strtotime($globalDiscount['apply_only_before'])) : null;
                                            $current_date = date('Y-m-d');
                                            if( !empty($apply_only_before) && $apply_only_before > $current_date ) {
                                                // if apply_only_before date is set and is in the future then we can apply the global discount and override the accommodation setting for this room and rate plan
                                                $accomPayTerm = array_merge($accomPayTerm, [
                                                        'roomName' => esc_html($room['RoomName'] ?? ''),
                                                        'ratePlanName' => $ratePlanName,
                                                        'conditionType' => 'global discount',
                                                        'isDeposit' => true,
                                                        'depositType' => 'deposit', // default to 'deposit', can be 'full' if no deposit
                                                        'isPercentage' => @$globalDiscount['discount_type'] == 'percentage', // percentage value if isPercentage is true
                                                        'depositPercentage' => floatval( $globalDiscount['condition_percent_2'] ?? $accomPayTerm['depositPercentage'] ), // percentage value if isPercentage is true
                                                        'daysInAdv' => $daysInAdv,
                                                        'isFixedDate' => $daysInAdv === 0,
                                                        'fixedDueDate' => $globalDiscount['remaining_date'] ?? null, // used if isFixedDate is true then balance due date is fixed to this date
                                                        'balanceDueDate' => '', // will be calculated based on check-in date and days in advance,
                                                        'date_rules' => [],
                                                    ]
                                                );
                                            }
                                        }
                                    }

                                    if( $accomPayTerm['depositPercentage'] == 100 ) {
                                        $accomPayTerm = array_merge($accomPayTerm, [
                                            'depositType' => 'full',
                                            'isDeposit' => false,
                                        ]);
                                    }
                                    
                                }
                                else {
                                    $ratePlanId = $room['ratePlanId'] ?? null;
                                    $ratePlanDesc = wp_strip_all_tags($room['ratePlanDescription'] ?? '');
                                    $ratePlanLongDesc = wp_strip_all_tags($room['ratePlanLongDescription'] ?? '');

                                    $wp_rate_plan = wp_get_rate_plan_by_id($ratePlanId, $wp_property_id);
                                    if( !empty($wp_rate_plan) ) {
                                        $ratePlanId = $ratePlanId;
                                        $ratePlanName = wp_strip_all_tags($wp_rate_plan['rate_plan_name']);
                                        $ratePlanDesc = wp_strip_all_tags($wp_rate_plan['rate_plan_description']);
                                        $ratePlanLongDesc = wp_strip_all_tags($wp_rate_plan['rate_plan_long_descriptions']);
                                    }
                                }

                                // var_dump($room['ratePlanId']);
                                // var_dump($wp_rate_plan);
                                // pre($accomPayTerm, 0);

                                $isAccomDeposit = get_post_meta($wp_property_id, 'acc_deposit_amount', true) == '1';
                                $accomDepositPercentage = floatval( get_post_meta($wp_property_id, 'acc_supplier_deposit', true) );
                                // $isAccomDeposit = true;
                                // $accomDepositPercentage = 10;
                                if( empty($accomDepositPercentage) || $accomDepositPercentage < 1 ) {
                                    $isAccomDeposit = false;
                                }

                                if( $isAccomDeposit ) {
                                    // if override_invoice is false but accommodation has deposit enabled then we can apply the accommodation setting for this room
                                    $accomPayTerm = array_merge($accomPayTerm, [
                                        'roomName' => esc_html($room['RoomName'] ?? ''),
                                        'ratePlanName' => $ratePlanName,
                                        'conditionType' => 'accommodation setting', // for non-override we can set a generic rate plan name as it's not coming from the supplier
                                        'isDeposit' => true,
                                        'depositType' => 'deposit', // default to 'deposit', can be 'full' if no deposit
                                        'isPercentage' => true, // always percentage value for accommodation discount
                                        'depositPercentage' => $accomDepositPercentage, // percentage value if isPercentage is true
                                    ]);
                                }

                                $accomPayTerm['totalAmount'] = $priceRetail;
                                if( ! $accomPayTerm['isFixedDate'] ) {
                                    $accomPayTerm['daysInAdvDate'] = $accomPayTerm['balanceDueDate'] = date('Y-m-d', strtotime($startDisplay . ' - ' . $accomPayTerm['daysInAdv'] . ' days'));
                                }
                                else {
                                    // get fixed due date from accommodation setting and format it to Y-m-d
                                    if( $accomPayTerm['fixedDueDate'] ) {
                                        $accomPayTerm['fixedDueDate'] = date('Y-m-d', strtotime($accomPayTerm['fixedDueDate']));
                                    }

                                    $accomPayTerm['balanceDueDate'] = $accomPayTerm['fixedDueDate'];

                                    // get fixed due date from date_rules if exists
                                    if( !empty($accomPayTerm['date_rules']) ) {
                                        // var_dump($startDisplay);
                                        foreach( $accomPayTerm['date_rules'] as $date_rule ) {
                                            $date_rule['check_in'] = date_format_readable($date_rule['check_in'], 'Y-m-d', 'd-M-Y');
                                            $date_rule['check_out'] = date_format_readable($date_rule['check_out'], 'Y-m-d', 'd-M-Y');
                                            $date_rule['fixed_date'] = date_format_readable($date_rule['fixed_date'], 'Y-m-d', 'd-M-Y');
                                            // pre($date_rule, 0);
                                            if( strtotime($startDisplay) >= strtotime($date_rule['check_in']) && strtotime($startDisplay) <= strtotime($date_rule['check_out']) ) {
                                                // var_dump($date_rule);
                                                if( $date_rule['selected_type'] === 'fixed_date' && !empty($date_rule['fixed_date']) ) {
                                                    $accomPayTerm['balanceDueDate'] = date('Y-m-d', strtotime($date_rule['fixed_date']));
                                                }
                                                elseif( $date_rule['selected_type'] === 'balance_due' && !empty($date_rule['balance_due_days']) ) {
                                                    $accomPayTerm['balanceDueDate'] = date('Y-m-d', strtotime($startDisplay . ' - ' . intval($date_rule['balance_due_days']) . ' days'));
                                                }
                                                // var_dump($accomPayTerm['balanceDueDate']);
                                            }
                                        }
                                    }
                                }

                                if( empty($accomPayTerm['balanceDueDate']) || strtotime($accomPayTerm['balanceDueDate']) === strtotime($startDisplay) ) {
                                    // set today date as balance due date if not set by accommodation setting or global discount rules to avoid confusion for users. This means that the full amount will be due on booking if no balance due date is set.
                                    $accomPayTerm['balanceDueDate'] = date('Y-m-d');
                                    // $accomPayTerm = $supplierData;
                                    // continue;
                                }
                            ?>
                            <div class="rb-rateplan-box"
                                data-room-type-id="<?php echo esc_attr($roomTypeId); ?>"
                                data-rateplan-id="<?php echo esc_attr($ratePlanId ?? ''); ?>"
                                data-item-unique-id="<?php echo esc_attr($ratePlanId ?? ('bb_' . $rateplanSeq)); ?>"
                            >

                                <div class="rb-rateplan-left">

                                    <!-- TITLE + INFO ICON -->
                                    <div class="rb-rateplan-title-wrap">
                                        <div class="rb-rateplan-title">
                                            <?php echo esc_html($ratePlanName ?? ''); ?>
                                        </div>

                                        <?php if (!empty($ratePlanLongDesc)) : ?>
                                            <i class="rb-toggle-long-desc fa-solid fa-circle-info"
                                                title="More information"></i>
                                        <?php endif; ?>
                                    </div>

                                    <!-- SHORT DESCRIPTION -->
                                    <?php if (!empty($ratePlanDesc)) :?>
                                        <div class="rb-rateplan-desc">
                                            <?php echo esc_html($ratePlanDesc); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- LONG DESCRIPTION (HIDDEN) -->
                                    <?php if (!empty($ratePlanLongDesc)) : ?>
                                        <div class="rb-long-desc" style="display: none;">
                                            <?php
                                                // ✅ Security: Sanitize HTML content to allow safe formatting
                                                $long_desc = $ratePlanLongDesc;
                                                echo wp_kses_post($long_desc);
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                </div>

                                <div class="rb-rateplan-right">

                                    <div class="rb-price-container">

                                        <!-- per stay -->
                                        <div class="rb-per-stay">
                                            <!-- <?php // echo wp_kses_post(_currency_format_new($perStay, true)); ?>
                                            <span class="label">per stay</span> -->
                                        </div>

                                        <!-- prices -->
                                        <div class="rb-room-price">
                                            <div class="rb-final-price"
                                                data-price="<?php echo esc_attr($priceRetail); ?>">
                                                <?php echo wp_kses_post(_currency_format_new($priceRetail, true)); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Supplier terms - Deposit, Due and Full -->
                                    <div class="rb-price-container">

                                        <div class="rb-per-stay"></div>

                                        <!-- Supplier Condition -->
                                        <div class="rb-room-terms">
                                            <span class="rb-deposit-info" style="text-align: right;">
                                                <?php
                                                    if ( $accomPayTerm['isDeposit'] && $accomPayTerm['isNightFee'] && $accomPayTerm['noOfNights'] > 0 ) {

                                                        if( $accomPayTerm['noOfNights'] >= $nights ) {
                                                            $accomPayTerm = array_merge($accomPayTerm, [
                                                                'depositType' => 'full',
                                                                'isDeposit' => false,
                                                            ]);
                                                        }
                                                        else {
                                                            $nightFeeAmount = ceil( ($priceRetail / $nights) * $accomPayTerm['noOfNights'] );
                                                            $accomPayTerm['depositAmount'] = $nightFeeAmount;
                                                        }
                                                            
                                                    }

                                                    if ( $accomPayTerm['isDeposit'] ) {
                                                        if ( $accomPayTerm['isPercentage'] && $accomPayTerm['depositPercentage'] > 0 ) {
                                                            $accomPayTerm['depositAmount'] = ceil($priceRetail * ($accomPayTerm['depositPercentage'] / 100));
                                                        } elseif (!$accomPayTerm['isPercentage'] && $accomPayTerm['depositAmount'] > 0) {
                                                            $accomPayTerm['depositAmount'] = ceil($accomPayTerm['depositAmount']);
                                                        }
                                                        printf(
                                                            '<div>Deposit due on booking: <strong>%s</strong></div>',
                                                            wp_kses_post(_currency_format_new($accomPayTerm['depositAmount'], true))
                                                        );
                                                    }
                                                    
                                                    // pre($accomPayTerm, 0);
                                                    if( $accomPayTerm['depositAmount'] > 0 ) {

                                                        // Calculate balance due date
                                                        $accomPayTerm['balanceDueAmount'] = $priceRetail - $accomPayTerm['depositAmount'];
                                                        printf(
                                                            '<div>Balance due on (%s): <strong>%s</strong></div>',
                                                            esc_html(date('d M, Y', strtotime($accomPayTerm['balanceDueDate']))),
                                                            wp_kses_post(_currency_format_new($accomPayTerm['balanceDueAmount'], true))
                                                        );
                                                    }
                                                    else {
                                                        $accomPayTerm['balanceDueAmount'] = $priceRetail;
                                                        printf(
                                                            '<div>Full amount due on booking (%s): <strong>%s</strong></div>',
                                                            esc_html(date('d M, Y', strtotime($accomPayTerm['balanceDueDate']))),
                                                            wp_kses_post(_currency_format_new($priceRetail, true))
                                                        );
                                                    }
                                                    
                                                ?>
                                            </span>
                                        </div>
                                    </div>

                                </div>
                                <?php 
                                    $roomJson = [
                                        'checkIn' => $startDisplay,
                                        'checkOut' => $endDisplay,
                                        'checkinDisplay' => date_format_readable($startDisplay, 'd M, Y'),
                                        'checkoutDisplay' => date_format_readable($endDisplay, 'd M, Y'),
                                        'nights' => $nights,
                                        'isBedbank' => $isBedbank,
                                        'propertyId' => $propertyId,
                                        'propertyName' => $property_title,
                                        'propertyImage' => $property_image,
                                        'propertyPermission' => ( $isBedbank ? '' : $property_permission ),
                                        'resortName' => $resort_name_cart,
                                        'roomId' => $RoomId,
                                        'roomTypeId' => $roomTypeId,
                                        'roomName' => $room['RoomName'] ?? '',
                                        'roomImage' => $img,
                                        'roomDescription' => esc_html($desc[$RoomId] ?? ''),
                                        'itemUniqueId' => $ratePlanId ?? ('bb_' . $rateplanSeq),
                                        'ratePlanId' => $ratePlanId,
                                        'ratePlanName' => $ratePlanName ?? '',
                                        'priceRetail' => $priceRetail,
                                        'perStay' => $perStay,
                                        'ratePlanDesc' => $ratePlanDesc,
                                        'roomPayTerm' => [
                                            'isDeposit' => $accomPayTerm['isDeposit'],
                                            'depositPercentage' => $accomPayTerm['depositPercentage'],
                                            'depositAmount' => $accomPayTerm['depositAmount'],
                                            'balanceDueAmount' => $accomPayTerm['balanceDueAmount'],
                                            'balanceDueDate' => $accomPayTerm['balanceDueDate'],
                                            'totalAmount' => $accomPayTerm['totalAmount'],
                                        ],
                                        'discountType' => ( !empty($room['accommodationDiscount']) ? 'accommodation' : (!empty($room['globalDiscount']) ? 'globalDiscount' : null) ),
                                        'discountId' => ( !empty($room['accommodationDiscount']) ? ($room['accommodationDiscount']['id'] ?? null) : (!empty($room['globalDiscount']) ? ($room['globalDiscount']['id'] ?? null) : null) ),
                                    ];
                                ?>
                                <input type="hidden" class="rb-room-data" value="<?php echo esc_attr(wp_json_encode($roomJson)); ?>">
                                <button type="button" class="rb-select-btn">Select</button>
                            </div>
                            <?php $accomPayTerm = $supplierData; ?>
                        <?php endforeach; ?>
                    </div>


                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <!-- CART -->
    <aside class="rb-cart">
        <div class="rb-cart-wrap">
            <div class="rb-wrap-body">
                <div class="rb-cart-head">
                    <h3>Booking Summary</h3>

                    <div class="close_cart is_mobile"><a class="close"><i class="fa-solid fa-xmark"></i></a></div>
                </div>

                <div class="rb-cart-body" id="rbCartBody">
                    <p class="rb-cart-empty">No rooms selected yet.</p>
                </div>

                <div class="rb-cart-footer" id="rbCartFooter" style="display:none;">
                    <div class="rb-total-row">
                        <strong>Total</strong>
                        <span id="rbCartTotal">0</span>
                    </div>
                </div>
            </div>
            <div class="cart_submit">
                <button type="button" class="rb-proceed-btn" style="display:none;">Proceed</button>
            </div>
        </div>
    </aside>
</div>