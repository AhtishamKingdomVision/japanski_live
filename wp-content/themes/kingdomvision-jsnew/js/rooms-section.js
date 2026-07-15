    jQuery(function($) {

        // ✅ HELPER: Common AJAX error handler to reduce code duplication
        function handleAjaxError(xhr, exception) {
            let msg = '';
            if (xhr.status === 0) {
                msg = 'Internet not connected. Verify Network.';
                alert(msg);
            } else if (exception === 'timeout') {
                msg = 'Request timed out. Please try again.';
                alert(msg);
            } else if (exception === 'abort') {
                msg = 'Ajax request aborted.';
            } else if (xhr.status === 404) {
                msg = 'Requested page not found. [404]';
            } else if (xhr.status === 500) {
                msg = 'Internal Server Error [500].';
            } else if (exception === 'parsererror') {
                msg = 'Requested JSON parse failed.';
            } else {
                msg = 'Error: ' + xhr.status + ' ' + xhr.responseText;
            }
            console.error('AJAX Error:', msg);
            return msg;
        }

        // ✅ HELPER: Validate form input existence and get value safely
        function getFieldValue(selector) {
            const $field = $(selector);
            return $field.length > 0 ? ($field.val() || '') : '';
        }

        // ✅ HELPER: Validate and get numeric value from field
        function getNumericValue(value, defaultVal = 0) {
            const num = parseInt(value);
            return isNaN(num) ? defaultVal : num;
        }

        // ✅ HELPER: Consolidate localStorage operations
        const rb_storage = {
            get: function(key) {
                try { return localStorage.getItem(key); } catch (e) { return null; }
            },
            set: function(key, value) {
                try { localStorage.setItem(key, value); } catch (e) {}
            },
            remove: function(key) {
                try { localStorage.removeItem(key); } catch (e) {}
            },
            getJSON: function(key) {
                try {
                    const val = localStorage.getItem(key);
                    return val ? JSON.parse(val) : null;
                } catch (e) { return null; }
            },
            setJSON: function(key, obj) {
                try { localStorage.setItem(key, JSON.stringify(obj)); } catch (e) {}
            }
        };


        
        $(document)
        .off('submit.roomFilter', '#room-filter-form, #room-filter-form-popup')
        .on('submit.roomFilter', '#room-filter-form, #room-filter-form-popup', function (e) {
            room_filter_submit_func.call(this, e);
        });
        
        // $('#room-filter-form').on('submit', room_filter_submit_func);

        function room_filter_submit_func(e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();

            const $form = jQuery(this);

            const uiState = rb_storage.get('rb_ui_state') || 'list';

            const $btn = $form.find('.rooms-sc-btn');
            const originalText = $btn.text();

            const property_id = $form.attr('property-id');
            const current_acc_id = $form.attr('acc-id') || acc_id;

            const sc_checkin = $form.find('.sv-check-in').val().length > 0 ? $form.find('.sv-check-in').val().trim() : localStorage.niseko_checkin;
            const sc_checkout = $form.find('.sv-check-out').val().length > 0 ? $form.find('.sv-check-out').val().trim() : localStorage.niseko_checkout;

            const rate_checked = rb_storage.get(current_acc_id + '_rates_checked');

            if (rate_checked === 'true') {
                if (!sc_checkin || !sc_checkout) {
                    alert('Please select check-in and check-out dates');
                    return;
                }
            }

            if (sc_checkin && sc_checkout) {
                rb_storage.set('niseko_checkin', sc_checkin);
                rb_storage.set('niseko_checkout', sc_checkout);

                // Sync both original and popup form values
                $('.sv-check-in').val(sc_checkin);
                $('.sv-check-out').val(sc_checkout);
            }

            rb_storage.setJSON('rb_booking_context', {
                checkin: sc_checkin,
                checkout: sc_checkout,
                property_id: property_id,
                adults: rb_storage.get('sb_adults') || 0,
                children: rb_storage.get('sb_children') || 0,
                infants: rb_storage.get('sb_infants') || 0
            });

            $btn.prop('disabled', true).addClass('loading').text('Loading…');

            if (uiState === 'list') {
                $.ajax({
                    url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                    method: 'POST',
                    data: {
                        action: 'niseko_search_roomboss_single',
                        checkin: sc_checkin,
                        checkout: sc_checkout,
                        property_id: property_id,
                    },
                    success: function(res) {
                        if (res.success) {
                            rb_storage.set(current_acc_id + '_rates_checked', 'true');

                            $('#room-results').html(res.data.html);
                            updateBedroomTabs(res.data.available_bedroom_types);
                            $('.book-btn').prop('disabled', false).removeClass('disabled');
                            $('.units_avl').text(res.data.count);

                            // Close popup after successful rate check
                            $('.room-search-popup-modal').removeClass('active');
                            $('body').removeClass('room-search-popup-open');

                            const dateStrOut = $('#sc-check-out').val() ?? localStorage.niseko_checkout;
                            // Sync all other check-out instances
                            console.log( 'dateStrOut 432' );
                            console.log( dateStrOut );
                            $('.js-sb-checkout, #input_1_6').val(dateStrOut);
                            $('.js-sb-checkout, #input_1_6').prop('disabled', false);

                        } else {
                            $('.book-btn').prop('disabled', false).removeClass('disabled');
                            $('#room-results').html('<p>No rooms available for selected dates.</p>');
                        }
                    },
                    error: function(xhr, exception) {
                        handleAjaxError(xhr, exception);
                        $('#room-results').html('<p class="rb-error">Error fetching room availability.</p>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).removeClass('loading').text(originalText);
                    }
                });
            }

            rb_render_booking_from_storage();
        }
        
    const $popup = $('.room-search-popup-modal');

    $(document).on('click', '.open-room-search-popup', function (e) {
        e.preventDefault();
        
        let room_name = $( this ).parents('.rb-room-top'),
            meta_data = room_name.find('.rb-room-meta'),
            room_title = meta_data.find('.rb-room-title').text();

        $popup.attr( 'room_title', room_title );
        console.log( 'room_title' );
        console.log( room_title );

        $popup.addClass('active');
        $('body').addClass('room-search-popup-open');


        const $mainForm = $('#room-filter-form');
        const $popupForm = $('#room-filter-form-popup');

    });
        
    jQuery(document).on('click', '.upd-guest-btn', function (e) {
        e.preventDefault();

        let parent = $(this).parents('#room-search-popup-modal'),
            room_name = parent.attr('room_title');
        localStorage.setItem( 'sel_room', room_name );
        jQuery('#close-room-search-popup').trigger('click');
        jQuery('#room-filter-form').trigger('submit');
    });

        $(document).on('click', '#close-room-search-popup, #room-search-popup-modal .room-search-popup-overlay', function (e) {
            e.preventDefault();

            $popup.removeClass('is-active');
            $('body').removeClass('room-search-popup-open');
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $popup.removeClass('is-active');
                $('body').removeClass('room-search-popup-open');
            }
        });

        // ✅ HELPER: Check if mobile device
        function isMobile() {
            return window.matchMedia("(max-width: 767px)").matches;
        }

        const $filterForm = $('#room-filter-form');
        const acc_id = $filterForm.attr('acc-id');
        const room_id = $filterForm.attr('room-id');
        const header_height = $('header').outerHeight() || 0;
        const to_form = rb_storage.get('go_to_form');

        if (to_form === room_id) {
            $('html, body').animate({
                scrollTop: ($filterForm.offset().top - header_height)
            }, 400);
            rb_storage.remove('go_to_form');
        }

        setTimeout(() => {
            // Initial UI state check
            const uiState = rb_storage.get('rb_ui_state');
            if (uiState === 'booking') {
                if( $('#room-filter-form').length > 0 && $('#sc-check-in').val().length > 0 && $('#sc-check-out').val().length > 0 ){
                    $('#room-filter-form').trigger('submit');
                    // rb_render_booking_from_storage();
                }
            } else {
                // If no dates are pre-filled and not in booking state, show the room list.
                // This handles the case where rooms-section.php used to call show_booking_ui() unconditionally.
                if( $('#room-filter-form').length > 0 && $('#sc-check-in').val().length > 0 && $('#sc-check-out').val().length > 0 ){
                    $('#room-filter-form').trigger('submit');
                    // show_booking_ui();
                }
            }
            
        }, 500);

        // Ensure layout is handled on load (handles mobile cart positioning etc)
        handle_layout();

        jQuery(document).on('click', '.room-title, .room-info', function() {
           let parent = jQuery( this ).parents('.room-card'),
                rc_cover = parent.find('.rc_cover'),
                room_btns = rc_cover.find( '.room-btns' ),
                btn = room_btns.find('button.btn');

            btn.trigger('click');
        });

        jQuery(document).on('click', '.location-modal-close', function (e) {
            let parent = jQuery(this).parents('#location-info-modal');
            parent.removeClass('is-visible');
        });

        jQuery(document).on('click', '#open-location-info', function (e) {
            let modal = jQuery( '#location-info-modal' );
            modal.addClass('is-visible');
        });

        jQuery(document).on('click', '.book-btn', function(e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();

            const ratesChecked = rb_storage.get(acc_id + '_rates_checked') === 'true';
            const bookingContext = rb_storage.getJSON('rb_booking_context');

            if ( (!ratesChecked && !bookingContext ) || ( bookingContext.checkin === '' || bookingContext.checkout === '' ) ) {
                alert('Please enter dates.');
                return;
            }

            const $btn = $(this);
            const originalText = $btn.text();

            const payload = {
                property_id: bookingContext?.property_id || $filterForm.attr('property-id'),
                start_date: bookingContext?.checkin || getFieldValue('#sc-check-in'),
                end_date: bookingContext?.checkout || getFieldValue('#sc-check-out'),
                adults: $('#adults').val() || 0,
                children: $('#children').val() || 0,
                infants: $('#infants').val() || 0
            };

            fetchBookingUI(payload, function() {
            });
        });

        function show_booking_ui() {
            const payload = {
                property_id: $filterForm.attr('property-id'),
                start_date: $filterForm.find('#sc-check-in').val(),
                end_date: $filterForm.find('#sc-check-out').val(),
            };
            if (!payload.start_date || !payload.end_date) {
                $('.room-list').show();
                return;
            }

            fetchBookingUI(payload);
        }

        /**
         * Consolidated AJAX handler for loading the booking flow
         */
        function fetchBookingUI(params, callback) {
            $('.booking-wrap')
                .html('<div class="rb-loading">Loading rates…</div>')
                .show();
            $('.room-list').hide();

            $.ajax({
                url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                type: 'POST',
                data: {
                    action: 'load_roomboss_booking',
                    property_id: params.property_id,
                    start_date: params.checkin || params.start_date,
                    end_date: params.checkout || params.end_date,
                    adults: params.adults || 0,
                    children: params.children || 0,
                    infants: params.infants || 0
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.success) {
                        rb_storage.set('rb_ui_state', 'booking');
                        
                        // ✅ Sync context to storage so state persists correctly on page refresh
                        rb_storage.setJSON('rb_booking_context', {
                            property_id: params.property_id,
                            checkin: params.checkin || params.start_date,
                            checkout: params.checkout || params.end_date,
                            adults: params.adults || 0,
                            children: params.children || 0,
                            infants: params.infants || 0
                        });

                        $('.room-list').fadeOut(200, function() {
                            $('#room-modal').fadeOut(200);
                            $('.booking-wrap').hide().html(resp.data.html).fadeIn(200, function() {
                                if( localStorage.sel_room !== undefined ){
                                    let room_title = localStorage.sel_room,
                                        room = $('.rb-room-meta .rb-room-title').filter(function() {
                                            return $(this).text().trim().toLowerCase() === room_title.trim().toLowerCase();
                                        });

                                    if( room.length < 1){
                                        room = $('.rb-room-card').eq(0);
                                    }

                                    const header_height = $('header').outerHeight() || 0;
                                    localStorage.removeItem('sel_room');
                                    $('html, body').animate({
                                        scrollTop: (room.offset().top - header_height)
                                    }, 400);
                                }
                            });
                        });

                        $('.rb-adults').val( localStorage.sb_adults );

                        if (typeof kv_booking_init === 'function') {
                            kv_booking_init();
                        }
                        $('.units_avl').text($('.rb-room-card').length);
                        $( '.available_units' ).length > 1 ? $( '.available_units' ).eq(1).remove() : '';
                    } else {
                        $('.booking-wrap').html('<p class="rb-error">' + (resp?.data?.message || 'No data returned') + '</p>');
                    }
                },
                error: function(xhr) {
                    $('.booking-wrap').html('<p class="rb-error">Unable to load booking data.</p>');
                    console.error('Booking AJAX error:', xhr.responseText);
                },
                complete: function() {
                    if (typeof callback === 'function') callback();
                }
            });
        }

        function handle_layout(){
            var rbcart = $('aside.rb-cart');
            var body = $('body');
            if(isMobile()){
                body.addClass('mobile');

                if( rbcart.length > 0 && body.hasClass( 'mobile' ) ){

                    // Move rbcart to be the first child of the body
                    rbcart.prependTo(body);
                }
            }
            else{
                var rbcart = $('aside.rb-cart');
                var parent = $('.rb-booking-layout');

                if( rbcart.length > 0 && body.hasClass( 'mobile' ) ){

                    body.removeClass('mobile');
                    // Move rbcart to be the first child of the body
                    rbcart.appendTo(parent);
                }
            }
        }

        $(document).on('change', '#room-filter-form input, #room-filter-form select, #room-filter-form-popup input, #room-filter-form-popup select', function() {
            const $form = $(this).closest('form');
            const current_acc_id = $form.attr('acc-id') || acc_id;

            rb_storage.set(current_acc_id + '_rates_checked', 'false');
        });

        $(document).on('click', '.back-to-rooms', function() {

            // 🔁 Reset persisted UI state
            localStorage.removeItem('rb_ui_state');

            $('.booking-wrap').fadeOut(200, function() {
                jQuery(this).empty();

                $('.room-list').fadeIn(200);
            });
        });

        // Open modal
        $(document).on('click', '.details-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.text();

            const roomId = $(this).attr('room-id');
            const propertyId = $(this).attr('property-id');

            $btn.prop('disabled', true).addClass('loading').text('Loading…');

            $.ajax({
                url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                method: 'POST',
                data: {
                    action: 'niseko_load_room_details',
                    room_id: roomId,
                    property_id: propertyId,
                },
                success: function(res) {
                    if (res.success) {
                        $('#room-modal-body').html(res.data.html);
                        $('#room-modal').fadeIn(200, function() {
                            const $gallery = $('.js-room-gallery');
                            const $images = $gallery.find('img');
                            let imagesLoaded = 0;
                            $images.each(function() {
                                if (this.complete) {
                                    imagesLoaded++;
                                } else {
                                    $(this).on('load', () => {
                                        imagesLoaded++;
                                        if (imagesLoaded === $images.length) initRoomGallery();
                                    });
                                }
                            });
                            if (imagesLoaded === $images.length) initRoomGallery();
                        });
                        $('.book-btn').prop('disabled', false).removeClass('disabled');
                    } else {
                        $('#room-modal-body').html('<p>Error loading room details.</p>');
                        $('.book-btn').prop('disabled', false).removeClass('disabled');
                    }
                },
                error: function(xhr, exception) {
                    handleAjaxError(xhr, exception);
                    $('#room-modal-body').html('<p class="rb-error">Error loading room details.</p>');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading').text(originalText);
                }
            });
        });

        $(document).on('click', '#room-modal', function(e) {
            if ($(e.target).is('#room-modal')) $(this).fadeOut(200);
        });
        $(document).on('click', '.room-modal-close, .room-modal-backdrop', function() {
            $('#room-modal').fadeOut(200);
        });

        // Handle Bedroom Tab Switching
        $(document).on('click', '.bedroom-tab', function() {
            $('.bedroom-tab').removeClass('active');
            $(this).addClass('active');
            var bedroomFilter = $(this).data('bedroom');
            if (bedroomFilter === 'all') {
                $('.room-card').show();
            } else {
                $('.room-card').each(function() {
                    var roomBedroomCount = $(this).data('bedroom');
                    if (roomBedroomCount === parseInt(bedroomFilter)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });

        // Trigger a click on the "All Bedrooms" tab by default
        $('.bedroom-tab[data-bedroom="all"]').click();

        $(document).on('click', '.close_cart', function (e) {
            $(this).parents('aside.rb-cart').removeClass('active');
            $( 'body' ).removeClass('cart-active');
        });
        
        $('.filter-tab.reset').on('click', function() {
            localStorage.setItem( 'apply-filters', false );
            $('.filter-tabs .close_filter').each( function ( i,e ){
                $(this).trigger('click');
            } );
            var parent = $( this ).parent('.filter');
            parent.hide();
            localStorage.setItem( 'apply-filters', true );
            localStorage.removeItem( 'niseko_checkin' );
            localStorage.removeItem( 'niseko_checkout' );
            $('#sc-check-in, #sc-check-out').val('');
            $('#apply-filters').trigger('click');
        });

        function updateBedroomTabs(availableBedroomTypes) {
            var avl_units = $( '.available_units' )[0].outerHTML;
            $('.bedroom-tabs').empty();
            $('.bedroom-tabs').append('<button type="button" class="bedroom-tab active" data-bedroom="all">All Bedrooms</button>');
            $.each(availableBedroomTypes, function(index, type) {
                $('.bedroom-tabs').append('<button type="button" class="bedroom-tab" data-bedroom="' + type + '">' + type + ' Bedroom' + (type > 1 ? 's' : '') + '</button>');
            });
            $('.bedroom-tabs').prepend( avl_units );
            var room_count = $( '#room-results .room-card' ).length;
            $( '.units_avl' ).text( room_count );
        }


        function kv_booking_cart_get() {
            try {
                let val = localStorage.getItem('rb_cart');
                val = val ? JSON.parse(val) : { items: [] };
                if( val?.expiresAt != undefined ) {
                    val = { items: [] };
                }
                return val;
            } catch(e) { return { items: [] }; }
        }

        function kv_booking_cart_set(cart) {
            try { localStorage.setItem('rb_cart', JSON.stringify(cart)); } catch(e) {}
        }

        function kv_booking_cart_render() {

            const cart = kv_booking_cart_get();
            const $body  = jQuery('#rbCartBody');
            const $footer = jQuery('#rbCartFooter');

            const $proceedBtn = jQuery('.rb-proceed-btn');

            if (!cart.items || !cart.items.length) {
                $body.html('<p class="rb-cart-empty">No rooms selected yet.</p>');
                $body.removeClass('rb-single-item');
                $footer.hide();
                $proceedBtn.hide();
                return;
            }

            $body.toggleClass('rb-single-item', cart.items.length === 1);

            // ------ Property header (from first cart item) ------
            const first = cart.items[0];
            let propertyHtml = '';
            if (first.property_name) {
                const pImg = first.property_image
                    ? `<img src="${first.property_image}" alt="${first.property_name}" class="rb-cart-property-img">`
                    : '';
                propertyHtml = `<div class="rb-cart-property">
                    ${pImg}
                    <div class="rb-cart-property-info">
                        <div class="rb-cart-property-name">${first.property_name}</div>
                        ${first.resort_name ? `<div class="rb-cart-resort-name">${first.resort_name}</div>` : ''}
                    </div>
                </div>`;
            }

            let html = propertyHtml;
            let subtotal     = 0;
            let totalDeposit = 0;
            let totalBalance = 0;

            cart.items.forEach((it, idx) => {
                const price   = Number(it.price || 0);
                subtotal     += price;
                const deposit = Number(it.payment?.depositAmount    || 0);
                const balance = Number(it.payment?.balanceDueAmount || (price - deposit));
                totalDeposit += deposit;
                totalBalance += balance;

                const totalPax      = (it.guests?.adults || 0) + (it.guests?.children || 0) + (it.guests?.infants || 0);
                const formattedPrice = '¥' + price.toLocaleString('ja-JP');

                const roomImgHtml = it.room_image
                    ? `<div class="rb-summary-room-img-wrap"><img src="${it.room_image}" alt="${it.room_name || ''}" class="rb-summary-room-img"></div>`
                    : '';

                let guestRows = '';
                if (it.guests?.adults)   guestRows += `<div class="rb-guest-row"><span>Adults</span><strong>${it.guests.adults}</strong></div>`;
                if (it.guests?.children) guestRows += `<div class="rb-guest-row"><span>Children</span><strong>${it.guests.children}</strong></div>`;
                if (it.guests?.infants)  guestRows += `<div class="rb-guest-row"><span>Infants</span><strong>${it.guests.infants}</strong></div>`;

                let paymentHtml = '';
                if (deposit > 0) {
                    paymentHtml += `<div class="rb-payment-row rb-payment-deposit">
                        <span>Deposit on booking</span>
                        <strong>¥${deposit.toLocaleString('ja-JP')}</strong>
                    </div>`;
                    if (balance > 0) {
                        const balanceDateFmt = it.payment?.balanceDueDate
                            ? new Date(it.payment.balanceDueDate).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'})
                            : '';
                        paymentHtml += `<div class="rb-payment-row rb-payment-balance">
                            <span>Balance due${balanceDateFmt ? ' (' + balanceDateFmt + ')' : ''}</span>
                            <strong>¥${balance.toLocaleString('ja-JP')}</strong>
                        </div>`;
                    }
                } else {
                    const dueDateFmt = it.payment?.balanceDueDate
                        ? new Date(it.payment.balanceDueDate).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'})
                        : '';
                    paymentHtml += `<div class="rb-payment-row">
                        <span>Full amount${dueDateFmt ? ' due ' + dueDateFmt : ' due on booking'}</span>
                        <strong>${formattedPrice}</strong>
                    </div>`;
                }

                html += `<div class="rb-summary-card" data-idx="${idx}">
                    ${roomImgHtml}
                    <div class="rb-summary-card-body">
                        <div class="rb-summary-head">
                            <div class="rb-summary-room-info">
                                <div class="rb-summary-room">${it.room_name || ''}</div>
                                <div class="rb-summary-rate">${it.rateplan_name || ''}</div>
                            </div>
                            <div class="rb-summary-price-wrap">
                                <div class="rb-summary-price">${formattedPrice}</div>
                                <button type="button" class="rb-remove" title="Remove"></button>
                            </div>
                        </div>
                        <div class="rb-summary-guests-detail">
                            <div class="rb-summary-pax">Total ${totalPax > 1 ? 'guests' : 'guest'}: ${totalPax}</div>
                            ${guestRows}
                        </div>
                        <div class="rb-summary-dates">
                            <div class="rb-date"><span>Check in</span>${it.dates?.checkinDisplay || ''}</div>
                            <div class="rb-date-separator"></div>
                            <div class="rb-date"><span>Check out</span>${it.dates?.checkoutDisplay || ''}</div>
                        </div>
                        <div class="rb-summary-payment">${paymentHtml}</div>
                    </div>
                </div>`;
            });

            $body.html(html);

            // ------ Footer: subtotal + deposit totals + grand total ------
            let footerHtml = '';
            if (cart.items.length > 1) {
                footerHtml += `<div class="rb-total-row rb-subtotal-row">
                    <span>Subtotal</span><span>¥${subtotal.toLocaleString('ja-JP')}</span>
                </div>`;
                if (totalDeposit > 0) {
                    footerHtml += `<div class="rb-total-row rb-deposit-sum-row">
                        <span>Total deposit</span><span>¥${totalDeposit.toLocaleString('ja-JP')}</span>
                    </div>
                    <div class="rb-total-row rb-balance-sum-row">
                        <span>Total balance</span><span>¥${totalBalance.toLocaleString('ja-JP')}</span>
                    </div>`;
                }
            }
            footerHtml += `<div class="rb-total-row rb-grand-total-row">
                <strong>Total</strong>
                <strong><span id="rbCartTotal">¥${subtotal.toLocaleString('ja-JP')}</span></strong>
            </div>`;

            $footer.html(footerHtml).show();

            // Only show the Proceed button if the cart timer is not expired.
            // The kv-cart-timer module is the source of truth for visibility
            // when a timer is present.
            if (window.KVCartTimer && typeof window.KVCartTimer.isExpired === 'function') {
                try {
                    const metaRaw = localStorage.getItem('rb_cart_timer');
                    const meta = metaRaw ? JSON.parse(metaRaw) : null;
                    if (meta && window.KVCartTimer.isExpired(meta)) {
                        // Hide; kv-cart-timer will handle showing the Refresh button.
                        $proceedBtn.hide();
                    } else {
                        $proceedBtn.show();
                    }
                } catch (err) {
                    $proceedBtn.show();
                }
            } else {
                $proceedBtn.show();
            }

            $('.rb-select-btn').removeClass('is-selected').text('Book Now').prop('disabled', false);
            cart.items.forEach(it => {
                // Mark the selected rate plan
                $(`.rb-rateplan-box[data-room-type-id="${it.room_type_id}"][data-item-unique-id="${it.item_unique_id}"]`)
                    .find('.rb-select-btn').addClass('is-selected').text('Booked Now');

                // Disable all other rate plans for the same room
                $(`.rb-rateplan-box[data-room-id="${it.room_id}"]`).each(function() {
                    const $box = $(this);
                    const boxUniqueId = $box.data('item-unique-id');
                    if (String(boxUniqueId) !== String(it.item_unique_id)) {
                        $box.find('.rb-select-btn')
                            .prop('disabled', true)
                            .text('Unavailable')
                            .addClass('is-disabled');
                    }
                });
            });
        }

        function scrollToRoom($room) {
            if($room.length) {
                $('html, body').animate({ scrollTop: $room.offset().top - 140 }, 600);
            }
        }

        function kv_booking_init() {
            kv_booking_cart_render();
            handle_layout();

            // Notify the cart timer module that the cart widget has been
            // rendered. The timer may have been waiting for the container
            // to appear (e.g. on Property Details pages where the booking
            // UI loads via AJAX). Mounting is idempotent and removes any
            // duplicate timer nodes.
            document.dispatchEvent(new CustomEvent('rb:booking-ui-ready'));

            // ✅ Filter guest dropdown options based on localStorage counts
            const lsAdults = parseInt(rb_storage.get('sb_adults')) || 2;
            const lsChildren = parseInt(rb_storage.get('sb_children')) || 0;
            const lsInfants = parseInt(rb_storage.get('sb_infants')) || 0;
            const adultLimit = lsAdults + lsChildren;
            const childInfantLimit = Math.max(0, adultLimit - 1);

            // Restrict and set Adults
            $('.rb-adults').each(function() {
                const $select = $(this);

                $select.val(lsAdults).trigger('change');
            });

        // Restrict and set Children & Infants
            $('.rb-children, .rb-infants').each(function() {
                const $select = $(this);

                let limit = childInfantLimit;
                let selectedValue = 0;

                if ($select.hasClass('rb-children')) {
                    selectedValue = lsChildren;
                }

                if ($select.hasClass('rb-infants')) {
                    selectedValue = lsInfants;
                }

                // $select.find('option').each(function() {
                //     const val = $(this).val();

                //     if (val !== "" && parseInt(val, 10) > limit) {
                //         $(this).remove();
                //     }
                // });

                if (selectedValue > limit) {
                    selectedValue = limit;
                }

                $select.val(selectedValue);
                $select.trigger('change');
            });

            // Add mobile cart logic from rooms-section.php
            const wp_body = $('body');
            const hz_cart = $('aside.rb-cart');
            $(document)
                .off('click.rbSelect')
                // .on('click.rbSelect', '.rb-select-btn :not(.is-selected)', function() {
                .on('click.rbSelect', '.rb-select-btn', function() {
                    const wp_body = $('body');
                    const hz_cart = $( 'aside.rb-cart' );
                    const $btn = $(this);
                    const $box = $btn.closest('.rb-rateplan-box');
                    const $room = $btn.closest('.rb-room-card');

                    /* --------------------------------------------------
                       1️⃣ READ ROOM LIMITS & GUESTS
                    -------------------------------------------------- */
                    const maxGuests = getNumericValue($room.find('.rb-icon.guest').text());
                    const $guestForm = $room.find('.rb-guests-form');
                    const adults = getNumericValue($guestForm.find('.rb-adults').val());
                    const children = getNumericValue($guestForm.find('.rb-children').val());
                    const infants = getNumericValue($guestForm.find('.rb-infants').val());

                    // reset UI
                    $room.find('.rb-guest-input').css('border', '');

                    /* --------------------------------------------------
                       2️⃣ VALIDATION (EXACT OLD BEHAVIOUR)
                    -------------------------------------------------- */

                    if (!adults || adults < 1) {
                        console.warn('❌ validation failed: adults');
                        $room.find('.rb-adults').css('border', '2px solid red');
                        alert('Please select number of adults staying in this room.');
                        return;
                    }

                    if ((adults + children) > maxGuests) {
                        console.warn('❌ validation failed: pax exceed max');
                        $room.find('.rb-adults, .rb-children').css('border', '2px solid red');
                        alert(`Total pax cannot exceed ${maxGuests}.`);
                        return;
                    }

                    /* --------------------------------------------------
                       4️⃣ BUILD GUESTS STRING (OLD STYLE)
                    -------------------------------------------------- */
                    let parts = [];

                    if (adults > 0) {
                        parts.push(`${adults} Adult${adults > 1 ? 's' : ''}`);
                    }

                    if (children > 0) {
                        parts.push(`${children} Child${children > 1 ? 'ren' : ''}`);
                    }

                    if (infants > 0) {
                        parts.push(`${infants} Infant${infants > 1 ? 's' : ''}`);
                    }

                    let guests_staying = parts.join(', ');

                    /* --------------------------------------------------
                       5️⃣ OPTIONAL DISCOUNT SUPPORT
                    -------------------------------------------------- */

                    // const discountPrice = Number($box.data('discount-price') || 0);
                    // console.log('💸 discountPrice:', discountPrice);

                    /* --------------------------------------------------
                       5.5️⃣ READ .rb-room-data HIDDEN FIELD & PROPERTY CONTEXT
                    -------------------------------------------------- */

                    let roomData = {};
                    try {
                        const rawRoomData = $box.find('.rb-room-data').val();
                        const sanitizedData = rawRoomData.replace(/"roomDescription":"(.*?)"(?=,"itemUniqueId")/g, (match, group) => {
                            const escapedGroup = group.replace(/(?<!\\)"/g, '\\"');
                            return `"roomDescription":"${escapedGroup}"`;
                        });
                        if (rawRoomData) roomData = JSON.parse(sanitizedData);
                    } catch(e) { console.warn('Could not parse .rb-room-data', e); }

                    /* --------------------------------------------------
                       3️⃣ READ DATES & NIGHTS (SINGLE SOURCE)
                    -------------------------------------------------- */

                    const check_in = roomData.checkIn;
                    const check_out = roomData.checkOut;
                    const nights = roomData.nights;

                    const payTerm      = roomData.roomPayTerm || {};

                    /* --------------------------------------------------
                       6️⃣ BUILD CART ITEM
                    -------------------------------------------------- */

                    const item = {
                        hotel_type_id:   roomData.propertyId,
                        is_bedbank:      roomData.isBedbank,
                        room_id:         roomData.roomId,
                        room_type_id:    roomData.roomTypeId,
                        property_name:   roomData.propertyName,
                        property_image:  roomData.propertyImage,
                        property_permission:  roomData.propertyPermission || '',
                        resort_name:     roomData.resortName,
                        room_name:       roomData.roomName,
                        room_image:      roomData.roomImage,
                        room_desc:       roomData.roomDescription,
                        rateplan_id:     roomData.ratePlanId,
                        item_unique_id:  roomData.itemUniqueId,
                        rateplan_name:   roomData.ratePlanName,
                        price:           roomData.priceRetail,
                        discount_type:   roomData.discountType || null,
                        discount_id:     roomData.discountId || null,
                        duration:        roomData.nights,
                        guests: {
                            adults,
                            children,
                            infants,
                            label: guests_staying
                        },
                        dates: {
                            check_in,
                            check_out,
                            nights,
                            checkinDisplay: roomData.checkinDisplay,
                            checkoutDisplay: roomData.checkoutDisplay
                        },
                        payment: {
                            isDeposit:        !!payTerm.isDeposit,
                            depositPercentage: Number(payTerm.depositPercentage || 0),
                            depositAmount:    Number(payTerm.depositAmount    || 0),
                            balanceDueAmount: Number(payTerm.balanceDueAmount || 0),
                            balanceDueDate:   payTerm.balanceDueDate || '',
                            totalAmount:      Number(payTerm.totalAmount || roomData.priceRetail || 0),
                        }
                    };

                    /* --------------------------------------------------
                       7️⃣ UPDATE CART
                    -------------------------------------------------- */

                    let cart = kv_booking_cart_get();
                    localStorage.setItem( 'pathname', pathname );
                    // Single-property cart restriction
                    if (cart.items && cart.items.length) {
                        const existingPropertyId = String(cart.items[0].hotel_type_id || '');
                        const nextPropertyId = String(item.hotel_type_id || '');

                        if (existingPropertyId && nextPropertyId && existingPropertyId !== nextPropertyId) {
                            alert('You can only add rooms from one property at a time. Please remove existing cart items before adding a room from another property.');
                            return;
                        }

                        // One rate plan per room restriction
                        const sameRoomInCart = cart.items.some(x => x.room_type_id === item.room_type_id);
                        if (sameRoomInCart) {
                            alert('You can only add rooms from one property at a time. Please remove existing cart items before adding a room from another property.');
                            return;
                        }

                        if (item.item_unique_id) {
                            const alreadyInCart = cart.items.some(x => x.room_type_id === item.room_type_id && x.item_unique_id === item.item_unique_id);
                            if (alreadyInCart) {
                                alert('This Room is already in your cart.');
                                return;
                            }
                        }
                    }

                    cart.items.push(item);

                    kv_booking_cart_set(cart);
                    kv_booking_cart_render(); // handles all button states correctly

                    // Notify cart timer / other modules
                    document.dispatchEvent(new CustomEvent('rb:cart-updated', { detail: { cart: cart } }));

                    // Add mobile cart logic from rooms-section.php
                    if (isMobile()) {
                        wp_body.addClass('cart-active');
                        hz_cart.addClass('active');
                        
                        if( jQuery( '.rb-cart.active' ).length > 1 ){
                            jQuery( '.rb-cart.active:not(:first)').remove();
                        }
                    }
                });

            jQuery(document).on('change', '.rb-guest-input', function() {

                const $room = jQuery(this).closest('.rb-room-card');
                const $form = $room.find('.rb-guests-form');
                const $btn = $room.find('.rb-select-btn');

                const adultsField = $form.find('.rb-adults');
                const childrenField = $form.find('.rb-children');
                const infantsField = $form.find('.rb-infants');

                const adults = parseInt(adultsField.val()) || 0;
                const children = parseInt(childrenField.val()) || 0;
                const infants = parseInt(infantsField.val()) || 0;

                const maxGuests = parseInt($room.find('.rb-icon.guest').text()) || 0;
                const maxInfants = parseInt($room.find('.rb-icon.infant').text()) || 0;

                /* ---------------------------
                   ERROR CONTAINERS
                --------------------------- */
                let $guestErr = $room.find('.rb-error-guests');
                let $infantErr = $room.find('.rb-error-infants');

                if (!$guestErr.length) {
                    $guestErr = jQuery('<div class="rb-error rb-error-guests"></div>').insertAfter($form);
                }
                if (!$infantErr.length) {
                    $infantErr = jQuery('<div class="rb-error rb-error-infants"></div>').insertAfter($form);
                }

                /* ---------------------------
                   RESET
                --------------------------- */
                $guestErr.hide().text('');
                $infantErr.hide().text('');
                $form.find('.rb-guest-input').css('border', '');
                $btn.prop('disabled', false);

                let hasError = false;

                /* ---------------------------
                   1️⃣ ADULTS REQUIRED (ONLY HARD RETURN)
                --------------------------- */
                if (!adults || adults < 1) {
                    adultsField.css('border', '2px solid red');
                    $guestErr.text('Please select number of adults staying in this room.').show();
                    // scrollToRoom($room);
                    $btn.prop('disabled', true);
                    return; // ✅ staging also returns here
                }

                /* ---------------------------
                   2️⃣ NON-INFANT GUEST VALIDATION
                --------------------------- */
                const guestTotal = adults + children;

                if (guestTotal > maxGuests) {
                    adultsField.add(childrenField).css('border', '2px solid red');
                    $guestErr
                        .text(`Total pax (${guestTotal}) cannot exceed ${maxGuests}.`)
                        .show();
                    hasError = true;
                }

                /* ---------------------------
                   3️⃣ INFANT VALIDATION (RUNS REGARDLESS)
                --------------------------- */
                if (infantsField.length && infants > 0) {
                    if ((guestTotal + infants) > (maxGuests + maxInfants)) {
                        infantsField.css('border', '2px solid red');
                        $infantErr
                            .text(
                                `Total pax + infants cannot exceed ${maxGuests + maxInfants}.`
                            )
                            .show();
                        hasError = true;
                    }
                }

                /* ---------------------------
                   4️⃣ FINAL STATE
                --------------------------- */
                if (hasError) {
                    scrollToRoom($room);
                    $btn.prop('disabled', true);
                } else {
                    $guestErr.hide();
                    $infantErr.hide();
                    $form.find('.rb-guest-input').css('border', '');
                    $btn.prop('disabled', false);
                }
            });

            $(document)
                .off('click.rbRemove')
                .on('click.rbRemove', '.rb-remove', function() {
                    const $item = $(this).closest('.rb-summary-card');
                    const idx = Number($item.data('idx'));
                    let cart = kv_booking_cart_get();
                    if (!cart.items || typeof cart.items[idx] === 'undefined') return;

                    const removedItem = cart.items[idx];
                    cart.items.splice(idx, 1);
                    kv_booking_cart_set(cart);
                    kv_booking_cart_render();
                    localStorage.removeItem('pathname');

                    // Notify cart timer / other modules
                    document.dispatchEvent(new CustomEvent('rb:cart-updated', { detail: { cart: cart } }));

                    const stillInCart = cart.items.some(
                        x => x.room_type_id === removedItem.room_type_id && x.item_unique_id === removedItem.item_unique_id
                    );
                    if (!stillInCart) {
                        $(`.rb-rateplan-box[data-room-type-id="${removedItem.room_type_id}"][data-item-unique-id="${removedItem.item_unique_id}"]`)
                            .find('.rb-select-btn').removeClass('is-selected').text('Book Now');
                    }
                });
        }

        // proceed
        jQuery(document).off('click.rbProceed').on('click.rbProceed', '.rb-proceed-btn', function(e) {
            // Guard: respect cart-timer state. If the timer has expired, do not
            // allow the user to navigate to the booking page until availability
            // is refreshed.
            if (window.KVCartTimer && typeof window.KVCartTimer.isExpired === 'function') {
                try {
                    const metaRaw = localStorage.getItem('rb_cart_timer');
                    const meta = metaRaw ? JSON.parse(metaRaw) : null;
                    if (meta && window.KVCartTimer.isExpired(meta)) {
                        e.preventDefault();
                        return false;
                    }
                } catch (err) { /* ignore */ }
            }
            // redirect or open next step
            window.location.href = '/booking/';
        });

        function initRoomGallery() {
            const $gallery = $('.js-room-gallery');
            const $slides = $gallery.find('.room-slide');
            if ($gallery.hasClass('slick-initialized')) {
                $gallery.slick('unslick');
            }

            if ($slides.length >= 3) {
                $gallery.on('init', function() {
                    setTimeout(function() { $gallery.slick('setPosition'); }, 0);
                });
                $gallery.slick({
                    infinite: true,
                    slidesToShow: 3,
                    slidesToScroll: 1,
                    arrows: true,
                    dots: false,
                    adaptiveHeight: true,
                    prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',
                    nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>',
                    responsive: [{
                            breakpoint: 1024,
                            settings: { slidesToShow: 2 }
                        },
                        {
                            breakpoint: 768,
                            settings: { slidesToShow: 1 }
                        }
                    ]
                });
            }
        }

        function rb_render_booking_from_storage() {
            const bookingContext = rb_storage.getJSON('rb_booking_context');
            const currentPropertyId = $('#room-filter-form').attr('property-id');

            // 🛡️ Safety Check: If context is missing OR belongs to another property, reset UI state
            if (!bookingContext || !bookingContext.checkin || !bookingContext.checkout || 
                !bookingContext.property_id || bookingContext.property_id !== currentPropertyId) {
                rb_storage.remove('rb_ui_state');
                show_booking_ui(); 
                return;
            }

            const payload = {
                property_id: bookingContext.property_id,
                checkin: bookingContext.checkin,
                checkout: bookingContext.checkout,
                adults: getNumericValue(bookingContext.adults, 1),
                children: getNumericValue(bookingContext.children),
                infants: getNumericValue(bookingContext.infants)
            };

            fetchBookingUI(payload, function() {
                $('.rooms-sc-btn').prop('disabled', false).removeClass('loading').text('Check Rates');
                // The original rooms-section.php had a .book-btn click here, but fetchBookingUI already loads the booking UI.
                // This is removed to prevent redundant calls.
                $( '.sb-submit img' ).attr('src', themeUrl+'/images/search-icon.png');
                $( '.sb-submit' ).attr('disabled', false);
                scrollToRoom($('.booking-wrap')); // Scroll to the booking wrap
            });

            
        }

        $('.book-btn').on('click', function() {

            let room_details = $( this ).parents( '.room_details' ),
                room_title = room_details.find( '.room-title' );
            localStorage.setItem( 'sel_room', room_title.text() );
        });

    });
document.addEventListener('DOMContentLoaded', function () {
    const rbLeft = document.querySelector('div.rb-left');
    const rbCart = document.querySelector('div.rb-cart');

    if (!rbLeft || !rbCart) return;

    const observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    rbCart.classList.add('sticky');
                } else {
                    rbCart.classList.remove('sticky');
                }
            });
        },
        {
            threshold: 0.1
        }
    );

    observer.observe(rbLeft);
});