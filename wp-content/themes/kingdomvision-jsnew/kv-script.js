if (base_url === undefined || pathname === undefined || host === undefined || pathArray === undefined || urlParams === undefined) {

    var base_url = window.location.origin; //https://gvelondon.com

    var pathname = window.location.pathname; //cars/bugatti-veyron-sang-noir/

    var host = window.location.host; //gvelondon.com

    var pathArray = window.location.pathname.split('/').filter(Boolean); //returns object ['', 'cars', ''] 

    var urlParams = new URLSearchParams(window.location.search);

}

var themeUrl = kv_object.themeUrl;

function triggerChildAgePopup(noOfChilds) {

    jQuery('section.child_age').addClass('active');

    jQuery('section.child_age input').val('');

    jQuery('section.child_age .ch_inn ul li').show();

    jQuery('section.child_age .ch_inn ul li').each(function (index, value) {

        if ((index + 1) > noOfChilds) {

            jQuery(value).hide();

        } else {

            var child = jQuery(value).find('select').data('child');

            var saved = localStorage.getItem('sb_' + child);

            if (saved) { jQuery(value).find('select').val(saved); }

        }

    });

}

function setCookie(e, t, n) {

    var o = new Date;

    o.setTime(o.getTime() + 24 * n * 60 * 60 * 1e3);

    var i = "expires=" + o.toUTCString();

    document.cookie = e + "=" + t + ";" + i + ";path=/"

}



function getCookie(e) {

    for (var t = e + "=", n = decodeURIComponent(document.cookie).split(";"), o = 0; o < n.length; o++) {

        for (var i = n[o];

            " " == i.charAt(0);) i = i.substring(1);

        if (0 == i.indexOf(t)) return i.substring(t.length, i.length)

    }

    return ""

}



function is_listing_page() {

    return jQuery('.accom-search-wrapper').length > 0;

}



function is_single_acc() {

    return jQuery('.room-list').length > 0;

}



function capitalizeFirstLetter([first = '', ...rest]) {

    return [first.toUpperCase(), ...rest].join('');

}



function isMobile() {



    const isMobile = /Mobi|Android|iPhone/i.test(navigator.userAgent);

    const isMobileSize = window.matchMedia("(max-width: 768px)").matches;



    if (isMobile && isMobileSize) {

        return true;

    }

    else {

        return false;

    }

}



jQuery(function ($) {



    //Mobile Accordian footer

    function footerAccordion() {

        if ($(window).width() <= 767) {

            $('.footer-accordion-title').off('click').on('click', function () {

                var content = $(this).next('.footer-accordion-content');

                $('.footer-accordion-content').not(content).slideUp().removeClass('active');

                $('.footer-accordion-title').not(this).removeClass('active');

                $(this).toggleClass('active');

                content.stop(true, true).slideToggle().toggleClass('active');

            });

        } else {

            $('.footer-accordion-content').show().removeClass('active');

            $('.footer-accordion-title').removeClass('active');

        }

    }

    footerAccordion();

    $(window).on('resize', function () {

        footerAccordion();

    });



    if (is_listing_page() && is_single_acc()) {

        var resort = pathArray[0].charAt(0).toUpperCase() + pathArray[0].slice(1);

        $('.mob_quote_inner').find('.resort_name select').val(resort);

    }



    console.log('script loaded');

    // Get Theme Path From Function



    // Check if pathname contains specific strings and add classes to header and mobPopWrapper

    const pathnamesToCheck = ['/where-to-stay/', '/things-to-do/', '/resort-services/', '/restaurants/', '/maps/', '/webcams/', '/about/', '/booking/'];

    const shouldAddClasses = pathnamesToCheck.some(path => pathname.includes(path));



    // if (shouldAddClasses) {

    // $('header, .mobPopWrapper').addClass('showHeadarFilter');

    // }



    const cart_restricted_paths = [localStorage.pathname, '/booking/'];



    var isMobile = window.matchMedia("(pointer: coarse)").matches; // Reliable mobile check

    var isRestricted = cart_restricted_paths.includes(pathname);

    var cart = kv_booking_cart_get();

    var items = cart?.data?.items;



    if (items && items?.length > 0 && !isRestricted) {

        var $cart = $('.sticky-cart-container');

        $cart.addClass('active');

        if (pathname !== localStorage.pathname) {

            $cart.find('a').attr('href', base_url + localStorage.pathname);

        }

        else {

            $cart.find('a').attr('href', 'javascript:void(0)');

        }

        localStorage.setItem('iscart', true);

    }



    if ((localStorage.pathname !== undefined && localStorage.pathname == pathname) && (isMobile && localStorage.iscart)) {

        setTimeout(() => {

            localStorage.removeItem('iscart');

            $('.rb-cart').addClass('active');

        }, 2100);

    }



    $(document).on('click', '.sticky-cart-container a', function (e) {

        e.preventDefault();

        $('.rb-cart').addClass('active');

        window.location.href = $(this).attr('href');

    });



    function closestParent(child, className) {

        if (!child || child == document) {

            return null;

        }

        if (child.classList.contains(className)) {

            return child;

        } else {

            return closestParent(child.parentNode, className);

        }

    }



    // Child Age Work

    $(document).on('change', '#input_1_10, #input_4_10 ', function (e) {

        let noOfChilds = $(this).val();

        if (noOfChilds == '')

            return;



        if (noOfChilds == '0') {

            $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {

                let val = $(value).val();

                let child = $(value).data('child');

                $('.Enquiry-modal .' + child + ' input, .acc_enquiry_form .' + child + ' input').val(val);

            });

            return;

        }



        $('section.child_age').addClass('active');

        $('section.child_age input').val('');

        $('section.child_age .ch_inn ul li').show();

        // DOM ELEMENTS

        $.each($('section.child_age .ch_inn ul li'), function (index, value) {

            if ((index + 1) > noOfChilds) {

                $(value).hide();

            }

        });

    });



    $(document).on('click', 'section.child_age .age_confirm , section.child_age .child_close', function (e) {

        e.preventDefault();

        let condition = true;

        $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {

            if ($(value).val() == '' || $(value).val() == '0') {

                condition = false;

                return;

            }

        });



        if (!condition) {

            $('section.child_age .age-error-box').show()

            return;

        }



        $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {

            let val = $(value).val();

            let child = $(value).data('child');

            $('.Enquiry-modal .' + child + ' input, .acc_enquiry_form .' + child + ' input').val(val);

            $('#gform_4 .' + child + ' input').val(val);

            localStorage.setItem('sb_' + child, val);

        });

        $('section.child_age').removeClass('active');

    });



    // Add Class on scroll

    $(window).scroll(function () {

        if ($(document).scrollTop() >= 10) {



            if (shouldAddClasses) {

                $('.main-header , header.newHeader').addClass('stickyHeader showHeadarFilter');

            }

            else {

                $('.main-header , header.newHeader').addClass('stickyHeader');

            }

            $('section.booking_page').addClass('active');

        } else {

            if (shouldAddClasses) {

                $('.main-header , header.newHeader').removeClass('stickyHeader showHeadarFilter');

            }

            else {

                $('.main-header , header.newHeader').removeClass('stickyHeader');

            }

            $('section.booking_page').removeClass('active');

        }

    });



    if ($('.gallery_carousel').length >= 1) {

        $('.gallery_carousel').slick({

            infinite: true,

            slidesToShow: 1,

            slidesToScroll: 1,

            draggable: true,

            autoplay: true,

            autoplaySpeed: 2000,

            dots: true,

            arrows: true,

            //             adaptiveHeight: true,

            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',

            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>'

        });

    }



    if ($('.fb_carousel').length >= 1) {

        $('.fb_carousel').slick({

            infinite: false,

            slidesToShow: 4,

            slidesToScroll: 1,

            draggable: true,

            // autoplay: true,

            // autoplaySpeed: 2000,

            dots: true,

            arrows: false,

            adaptiveHeight: true,

            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',

            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>'

        });

    }



    // reviews-carousel

    if ($('.reviews-carousel').length) {

        $('.reviews-carousel').slick({

            infinite: true,

            slidesToShow: 1,

            slidesToScroll: 1,

            draggable: true,

            //             adaptiveHeight: true,



            // DESKTOP DEFAULT

            arrows: true,

            dots: false,



            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',

            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>',



            responsive: [

                {

                    breakpoint: 480,

                    settings: {

                        arrows: true,

                        dots: true,

                        slidesToShow: 1,

                        slidesToScroll: 1,

                        adaptiveHeight: true,

                        dots: false,

                    }

                }

            ]

        });

    }



    // ---- DEFAULT: FIRST 2 OPEN ----

    const $triggers = $(".accor_trigger");



    // ---- DEFAULT: FIRST 2 OPEN ----

    $triggers.each(function (index) {

        const $btn = $(this);

        const panelID = $btn.attr("aria-controls");

        const $content = $("#" + panelID);



        if (index < 0) {

            $btn.attr("aria-expanded", "true")

                .addClass("active");



            $content.prop("hidden", false);

        } else {

            $btn.attr("aria-expanded", "false");

            $content.prop("hidden", true);

        }

    });



    // ---- CLICK TOGGLE ----

    $triggers.on("click", function () {



        const $btn = $(this);

        const panelID = $btn.attr("aria-controls");

        const $content = $("#" + panelID);



        const isOpen = $btn.attr("aria-expanded") === "true";



        // Toggle aria + hidden

        $btn.attr("aria-expanded", !isOpen);

        $content.prop("hidden", isOpen);



        // Toggle active class

        if (!isOpen) {

            $btn.addClass("active");

        } else {

            $btn.removeClass("active");

        }



    });



    // OFFERS SLIDER: 

    if ($('section.unbeatable_offers .offers').length) {

        $('section.unbeatable_offers .offers').slick({

            slidesToShow: 4.5,

            slidesToScroll: 1,

            infinite: false,

            arrows: true,

            dots: false,

            speed: 500,

            swipeToSlide: true,

            cssEase: 'ease',

            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',

            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>',

            responsive: [

                {

                    breakpoint: 1200,

                    settings: {

                        slidesToShow: 3.5

                    }

                },

                {

                    breakpoint: 992,

                    settings: {

                        slidesToShow: 2.2

                    }

                },

                {

                    breakpoint: 767,

                    settings: {

                        slidesToShow: 1.2

                    }

                }

            ]

        });

    }



    if ($('.activeSlider').length) {

        $('.activeSlider').slick({

            slidesToShow: 3.5,

            slidesToScroll: 1,

            infinite: false,

            arrows: true,

            dots: false,

            speed: 500,

            swipeToSlide: true,

            cssEase: 'ease',

            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',

            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>',

            responsive: [

                {

                    breakpoint: 1200,

                    settings: {

                        slidesToShow: 3.5

                    }

                },

                {

                    breakpoint: 992,

                    settings: {

                        slidesToShow: 2.2

                    }

                },

                {

                    breakpoint: 767,

                    settings: {

                        slidesToShow: 1.2

                    }

                }

            ]

        });

    }



    // Wysiwyg Read More Read Less

    $(".contentWrapper").each(function () {

        let $section = $(this);

        let $readMore = $section.find(".wysiwygReadMore");

        let $readLess = $section.find(".wysiwygReadLess");

        let $fullContent = $section.find(".wysiwygFullContent");

        let $shortContent = $section.find(".wysiwygShortContent");



        // Initially hide the "Read Less" button

        $readLess.hide();



        // READ MORE

        $readMore.on("click", function (e) {

            e.preventDefault();

            $fullContent.stop(true, true).slideDown(300); // show hidden content

            $shortContent.addClass('expend');

            $readMore.hide();

            $readLess.show();

        });



        // READ LESS

        $readLess.on("click", function (e) {

            e.preventDefault();

            $fullContent.stop(true, true).slideUp(300); // hide again

            $shortContent.removeClass('expend');

            $readMore.show();

            $readLess.hide();

        });

    });



    $(document).ready(function ($) {

        $('.kv-copy-link').on('click', function (e) {

            e.preventDefault();



            var link = $(this).data('link');

            var $btn = $(this);



            // Create temp input

            var $temp = $('<input>');

            $('body').append($temp);

            $temp.val(link).select();

            document.execCommand('copy');

            $temp.remove();



            // Visual feedback

            $btn.html('<i class="fa-solid fa-check"></i>').addClass('copied');



            setTimeout(function () {

                $btn.html('<i class="fa-solid fa-link"></i>').removeClass('copied');

            }, 1500);

        });



    });



    $(document).ready(function ($) {

        $('a[href^="#"]').on('click', function (e) {

            var id = $(this).attr('href');



            if (id === '#' || id === '') return;

            var target = $(id);

            if (target.length) {

                e.preventDefault();

                var offset = target.offset().top - 40; // sirf scroll offset

                $('html, body').stop().animate(

                    { scrollTop: offset },

                    500

                );

            }

        });



    });



    /** Blog Shortcode Script */

    if ($('.kv-posts-wrapper').length) {

        $('.kv-posts-wrapper').each(function () {



            const $wrap = $(this);

            const perPage = $wrap.data('per-page');

            const fixedCat = $wrap.data('fixed-cat');



            let page = 1;

            let busy = false;



            function loadPosts(reset = false) {



                if (busy) return;

                busy = true;



                if (reset) {

                    page = 1;

                    $wrap.find('.js-kv-posts').empty();

                }



                $.post(kv_object.ajaxurl, {

                    action: 'kv_filter_posts',

                    page: page,

                    per_page: perPage,

                    category: fixedCat || $wrap.find('.js-kv-category').val(),

                    sort: $wrap.find('.js-kv-sort').val()

                }, function (res) {



                    if (reset) {

                        $wrap.find('.js-kv-posts').html(res.html);

                    } else {

                        $wrap.find('.js-kv-posts').append(res.html);

                    }



                    if (!res.has_more) {

                        $wrap.find('.js-kv-loadmore-wrap').hide();

                    } else {

                        $wrap.find('.js-kv-loadmore-wrap').show();

                    }



                    busy = false;

                });

            }



            /* Initial load */

            loadPosts(true);



            /* Filter change */

            $wrap.on('change', '.js-kv-category, .js-kv-sort', function () {

                loadPosts(true);

            });



            /* Load more */

            $wrap.on('click', '.js-kv-loadmore', function (e) {

                e.preventDefault();

                page++;

                loadPosts();

            });



        });

    }



    /** Blog Shortcode Script */



    $(document).on('click', '.quote_toggle', function (e) {

        e.preventDefault();

        $('.mob_quote_form').addClass('active');

        $('body').addClass('quote-open');

    });



    $(document).on('click', '.close_mob_quote_form', function (e) {

        e.preventDefault();

        $('.mob_quote_form').removeClass('active');

        $('body').removeClass('quote-open');

    });



    $(document).on('click', '#close-room-search-popup', function (e) {

        e.preventDefault();

        $('.room-search-popup-modal').removeClass('active');

    });



    if ($(window).width() <= 767) {

        $('.mob_quote_form').appendTo('.content-wrapper');

    }





    // $(document).on('click', '.Enquiry-modal-close', function (e) {

    //     e.preventDefault();

    //     $('.Enquiry-modal').removeClass('active');

    //     $('body').removeClass('enquire-open');

    // });



    // $(document).on('click', '.enq_cta, .enquire_btn', function (e) {

    //     e.preventDefault();

    //     $('.Enquiry-modal').addClass('active');

    //     $('body').addClass('enquire-open');



    //     console.log( 'edecee' );



    //     var $propertyField = $('.Enquiry-modal-content .mob_quote_inner').find('.quote_form').find('#input_1_39');

    //     console.log( $propertyField );

    //     console.log( 'this' );

    //     console.log( $(this) );

    //     console.log( '$(this).hasClass' );

    //     console.log( $(this).hasClass('enquire_btn') );



    //     if ($(this).hasClass('enquire_btn')) {



    //         console.log( 'here' );

    //         var propertyName = $(this).parents('.accom-content').find('h3').text().trim();

    //         console.log( propertyName );

    //         $propertyField.val(propertyName).prop('readonly', true);

    //     } else {

    //         $propertyField.val('').prop('readonly', false);

    //     }

    // });

    // ahtisham work start
    // Two enquiry form instances can exist (listing + modal). Gravity Forms always
    // updates the first #gform_1, so we temporarily park the inactive form on submit
    // so validation messages only appear on the form the customer actually used.

    var parkedEnquiryMount = null;
    var parkedEnquiryTarget = null;

    function getModalEnquiryScope() {
        return $('.Enquiry-modal-content').first();
    }

    function getListingEnquiryScope() {
        return $('.acc_enquiry_form').first();
    }

    function clearEnquiryValidationIn($scope) {
        if (!$scope || !$scope.length) return;
        const $wrapper = $scope.find('.gform_wrapper').first();
        const $ctx = $wrapper.length ? $wrapper : $scope;
        $ctx.removeClass('gform_validation_error');
        $ctx.find('.gform_validation_errors, .validation_error, .validation_message').remove();
        $ctx.find('.gfield_error').removeClass('gfield_error');
        $ctx.find('.gfield_validation_message').remove();
        $ctx.find('[aria-invalid="true"]').attr('aria-invalid', 'false');
        $ctx.find('.gform_submission_error').remove();
    }

    function unparkEnquiryForm() {
        const $parked = $('.kv-enquiry-parked');
        if (!$parked.length && (!parkedEnquiryMount || !parkedEnquiryMount.length)) {
            parkedEnquiryMount = null;
            parkedEnquiryTarget = null;
            return;
        }

        $parked.each(function () {
            const $root = $(this);
            $root.find('[data-kv-parked-id]').each(function () {
                this.id = $(this).attr('data-kv-parked-id');
                $(this).removeAttr('data-kv-parked-id');
            });
            $root.removeClass('kv-enquiry-parked');
        });

        parkedEnquiryMount = null;
        parkedEnquiryTarget = null;
    }

    function parkEnquiryFormExcept(active) {
        unparkEnquiryForm();

        let $inactive = $();
        if (active === 'modal') {
            $inactive = getListingEnquiryScope().find('.mob_quote_form1').first();
            parkedEnquiryTarget = 'listing';
        } else if (active === 'listing') {
            $inactive = $('.Enquiry-modal-form-slot .mob_quote_form1, .Enquiry-modal .mob_quote_form1').first();
            parkedEnquiryTarget = 'modal';
        }

        if (!$inactive.length) {
            parkedEnquiryTarget = null;
            return;
        }

        // Keep the unused form visible, but rename its IDs so GF validation
        // only updates the form that was actually submitted.
        $inactive.addClass('kv-enquiry-parked');
        $inactive.find('[id]').each(function () {
            if ($(this).attr('data-kv-parked-id')) return;
            $(this).attr('data-kv-parked-id', this.id);
            this.id = 'parked_' + this.id;
        });
        parkedEnquiryMount = $inactive;
    }

    function enquiryFind(selector) {
        if ($('body').hasClass('enquire-open')) {
            const $inModal = getModalEnquiryScope().find(selector);
            if ($inModal.length) return $inModal;
        }
        const $inListing = getListingEnquiryScope().find(selector);
        if ($inListing.length) return $inListing;
        return $(selector);
    }

    function closeEnquiryModal() {
        clearEnquiryValidationIn(getModalEnquiryScope());
        unparkEnquiryForm();
        $('.Enquiry-modal').removeClass('active');
        $('body').removeClass('enquire-open');
        window.kvEnquiryModalScrollY = null;
    }

    function openEnquiryModal() {
        unparkEnquiryForm();
        clearEnquiryValidationIn(getModalEnquiryScope());
        $('.Enquiry-modal').addClass('active');
        $('body').addClass('enquire-open');
        window.kvEnquiryModalScrollY = window.scrollY || window.pageYOffset || 0;
    }

    function lockEnquiryModalPageScroll() {
        window.kvEnquiryModalScrollY = window.scrollY || window.pageYOffset || 0;
    }

    function restoreEnquiryModalPageScroll() {
        if (window.kvEnquiryModalScrollY == null) return;
        const y = window.kvEnquiryModalScrollY;
        $('html, body').stop(true);
        window.scrollTo(0, y);
    }

    function scrollEnquiryModalToValidation() {
        const $content = $('.Enquiry-modal-content');
        if (!$content.length || !$('.Enquiry-modal').hasClass('active')) return;

        // Keep the page where it was — do not jump down to the listing enquiry form.
        restoreEnquiryModalPageScroll();

        const $target = $content.find(
            '.gform_validation_errors, .validation_error, .gfield_error, .validation_message, .gfield_validation_message'
        ).first();

        if ($target.length) {
            const contentOffset = $content.offset().top;
            const targetOffset = $target.offset().top;
            const nextTop = $content.scrollTop() + (targetOffset - contentOffset) - 24;
            $content.stop(true).animate({ scrollTop: Math.max(0, nextTop) }, 250);
        } else {
            $content.stop(true).animate({ scrollTop: 0 }, 200);
        }

        // GF may animate page scroll slightly later — reinstate locked position.
        window.setTimeout(restoreEnquiryModalPageScroll, 50);
        window.setTimeout(restoreEnquiryModalPageScroll, 300);
    }

    // Before GF ajax submit: leave only the active form in the DOM.
    $(document).on('submit', 'form#gform_1', function () {
        const $form = $(this);
        if ($form.closest('.Enquiry-modal').length) {
            lockEnquiryModalPageScroll();
            parkEnquiryFormExcept('modal');
        } else if ($form.closest('.acc_enquiry_form, .load-more-enquiry-form').length) {
            parkEnquiryFormExcept('listing');
        }
    });

    $(document).on(
        'click',
        'form#gform_1 input[type="submit"], form#gform_1 #gform_submit_button_1, form#gform_1 .gform_button',
        function () {
            const $form = $(this).closest('form');
            if ($form.closest('.Enquiry-modal').length) {
                lockEnquiryModalPageScroll();
                parkEnquiryFormExcept('modal');
            } else if ($form.closest('.acc_enquiry_form, .load-more-enquiry-form').length) {
                parkEnquiryFormExcept('listing');
            }
        }
    );

    $(document).on('click', '.Enquiry-modal-close, .Enquiry-modal-overlay', function (e) {
        e.preventDefault();
        closeEnquiryModal();
    });

    function convertYMDToDMY(dateStr) {
        if (!dateStr) return '';
        if (dateStr.indexOf('/') !== -1) return dateStr;
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function parseRoomDataFromBox($box) {
        let roomData = {};
        try {
            const rawRoomData = $box.find('.rb-room-data').val();
            if (!rawRoomData) return roomData;
            const sanitizedData = rawRoomData.replace(/"roomDescription":"(.*?)"(?=,"itemUniqueId")/g, function (match, group) {
                const escapedGroup = group.replace(/(?<!\\)"/g, '\\"');
                return '"roomDescription":"' + escapedGroup + '"';
            });
            roomData = JSON.parse(sanitizedData);
        } catch (e) {
            console.warn('Could not parse .rb-room-data', e);
        }
        return roomData;
    }

    function populateEnquiryModal(data) {
        data = data || {};
        const $scope = getModalEnquiryScope();
        if (!$scope.length) return;

        const hasProductData = !!(data.propertyName || data.resortName || data.checkIn || data.checkOut || data.roomName);
        const $propertyField = $scope.find('#input_1_39, .property_name textarea').first();

        if (data.propertyName) {
            $propertyField.val(data.propertyName).prop('readonly', true).addClass('disabled');
        } else {
            $propertyField.val('').prop('readonly', false).removeClass('disabled');
        }

        const $resortField = $scope.find('#input_1_4, .resort_name select').first();
        if (data.resortName) {
            $resortField.val(data.resortName).addClass('disabled');
        } else {
            $resortField.removeClass('disabled');
        }

        const $roomField = $scope.find('#input_1_44, .room_name input').first();
        if (data.roomName) {
            $roomField.val(data.roomName);
        } else if (hasProductData) {
            $roomField.val('');
        }

        if (data.checkIn) {
            const checkInDmy = convertYMDToDMY(data.checkIn);
            $scope.find('#input_1_5').val(checkInDmy).trigger('change');
            syncCheckin(checkInDmy);
        }

        if (data.checkOut) {
            const checkOutDmy = convertYMDToDMY(data.checkOut);
            $scope.find('#input_1_6').val(checkOutDmy).prop('disabled', false).trigger('change');
            syncCheckout(checkOutDmy);
        }

        if (hasProductData) {
            $scope.find('.enquiry_type input').attr('value', 'Product').trigger('change');
        }
    }

    $(document).on('click', '.enq_cta, .enquire_btn', function (e) {
        e.preventDefault();

        const $btn = $(this);
        openEnquiryModal();

        const $ratePlanBox = $btn.closest('.rb-rateplan-box');
        if ($ratePlanBox.length) {
            const roomData = parseRoomDataFromBox($ratePlanBox);
            populateEnquiryModal({
                propertyName: roomData.propertyName || '',
                resortName: roomData.resortName || '',
                checkIn: roomData.checkIn || '',
                checkOut: roomData.checkOut || '',
                roomName: roomData.roomName || ''
            });
            return;
        }

        if ($btn.hasClass('enquire_btn')) {
            const $card = $btn.closest('.accom-card, .result-card');
            const propertyName = (
                $btn.parents('.accom-content').find('h3').first().text() ||
                $card.find('.accom-content h3').first().text() ||
                ''
            ).trim();
            populateEnquiryModal({ propertyName: propertyName });
            return;
        }

        populateEnquiryModal({});
    });
    // ahtisham work end



    // OPEN NAV

    $('.menu_toggle, .search_toggle').on('click', function (e) {

        e.preventDefault();

        $('.nav_area').addClass('is-open');

        $('body').addClass('is-open');

    });



    // CLOSE NAV

    $('.close_toggle').on('click', function (e) {

        e.preventDefault();

        $('.nav_area').removeClass('is-open');

        $('body').removeClass('is-open');

    });



    // Add arrow to parent menu items

    $('.mobile_menu li.menu-item-has-children > a').after(

        '<div class="trig"><svg width="13" height="8" viewBox="0 0 13 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.6929 1.44357L7.06787 7.06857C6.98948 7.14723 6.89634 7.20964 6.79378 7.25223C6.69122 7.29482 6.58126 7.31674 6.47021 7.31674C6.35916 7.31674 6.24921 7.29482 6.14665 7.25223C6.04409 7.20964 5.95094 7.14723 5.87256 7.06857L0.247557 1.44357C0.0890491 1.28506 -2.36196e-09 1.07008 0 0.845917C2.36196e-09 0.621752 0.0890491 0.406769 0.247557 0.24826C0.406066 0.0897521 0.621049 0.000703278 0.845214 0.000703275C1.06938 0.000703273 1.28436 0.0897521 1.44287 0.24826L6.47092 5.27631L11.499 0.247558C11.6575 0.0890494 11.8725 0 12.0966 0C12.3208 0 12.5358 0.0890494 12.6943 0.247558C12.8528 0.406066 12.9418 0.621049 12.9418 0.845214C12.9418 1.06938 12.8528 1.28436 12.6943 1.44287L12.6929 1.44357Z" fill="white"/></svg></div>');

    $('.mobile_menu').on('click', '.trig', function (e) {

        e.preventDefault();



        const $parentLi = $(this).closest('li');

        const $submenu = $parentLi.children('.sub-menu');



        // Close other open submenus (accordion behaviour)

        $parentLi

            .siblings('.menu-item-has-children')

            .removeClass('open')

            .children('.sub-menu')

            .slideUp(300);



        // Toggle current submenu

        $parentLi.toggleClass('open');

        $submenu.slideToggle(300);

    });





    /** Check in Checkout script */



    const savedResort = localStorage.getItem('sb_resort');

    const savedCheckin = localStorage.getItem('niseko_checkin');

    const savedCheckout = localStorage.getItem('niseko_checkout');

    const savedAdults = parseInt(localStorage.getItem('sb_adults'), 10);

    const savedChildren = parseInt(localStorage.getItem('sb_children'), 10);

    const savedInfants = parseInt(localStorage.getItem('sb_infants'), 10);



    if (savedResort) {

        $('.js-sb-resort').each(function () {

            if (!$(this).val()) {

                $(this).val(savedResort);

            }

        });

    }



    /*new resort function*/



    // Helper function to parse D/M/Y date string to Date object

    function parseDMYDate(dateStr) {

        if (!dateStr) return null;

        const parts = dateStr.split('/');

        if (parts.length !== 3) return null;

        const day = parseInt(parts[0], 10);

        const month = parseInt(parts[1], 10) - 1; // Month is 0-indexed

        const year = parseInt(parts[2], 10);

        return new Date(year, month, day);

    }



    // Helper function to format Date object to D/M/Y string with zero-padding

    function formatDMYDate(date) {

        if (!date) return '';

        const day = String(date.getDate()).padStart(2, '0');

        const month = String(date.getMonth() + 1).padStart(2, '0');

        const year = date.getFullYear();

        return `${day}/${month}/${year}`;

    }



    // Helper function to get minimum checkout date based on check-in + gap

    function getMinimumCheckoutDate(checkinDateStr, minDaysGap) {

        const checkinDate = parseDMYDate(checkinDateStr);

        if (!checkinDate) return '';

        const checkoutDate = new Date(checkinDate);

        checkoutDate.setDate(checkoutDate.getDate() + minDaysGap);

        return formatDMYDate(checkoutDate);

    }



    // Helper function to get maximum check-in date based on checkout - gap

    function getMaximumCheckInDate(checkoutDateStr, minDaysGap) {

        const checkoutDate = parseDMYDate(checkoutDateStr);

        if (!checkoutDate) return '';

        const checkinDate = new Date(checkoutDate);

        checkinDate.setDate(checkinDate.getDate() - minDaysGap);

        return formatDMYDate(checkinDate);

    }





    // Unified date picker — syncs all checkin/checkout fields



    const CHECKIN_SEL = '#input_1_5, .js-sb-checkin, #sc-check-in';

    const CHECKOUT_SEL = '#input_1_6, .js-sb-checkout, #sc-check-out';



    // d/m/Y (input value format) → m/d/Y (dateDropper option format)

    function toPickerDate(dmyStr) {

        if (!dmyStr) return '';

        const p = dmyStr.split('/');

        return p.length === 3 ? p[1] + '/' + p[0] + '/' + p[2] : '';

    }



    function injectPickerText() {

        if (!kv_object.date_dropper_content) return;

        const $pick = $('.datedropper .picker .pick-lg');

        if ($pick.length && !$pick.find('.kv-text').length) {

            $pick.prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`);

        }

    }



    function syncCheckin(val) {

        $(CHECKIN_SEL).val(val);

        localStorage.setItem('niseko_checkin', val);

    }



    function syncCheckout(val) {

        $(CHECKOUT_SEL).val(val).prop('disabled', false);

        localStorage.setItem('niseko_checkout', val);

        localStorage.setItem('maxdate', toPickerDate(val));

    }



    function initCheckoutPickers(minDate, skipEl) {

        $(CHECKOUT_SEL).each(function () {

            if (skipEl && this === skipEl) return;

            const $el = $(this);

            const currentVal = $el.val();

            const defaultDate = currentVal ? toPickerDate(currentVal) : '';



            try { $el.dateDropper('destroy'); } catch (e) { }



            const opts = {

                large: 1,

                largeDefault: 1,

                minDate: minDate,



                maxDate: kv_object.check_end_date,

                format: 'd/m/Y',

                eventSelector: 'focus',

                onChange: function (res) {

                    injectPickerText();

                    const val = ('0' + res.date.d).slice(-2) + '/' + ('0' + res.date.m).slice(-2) + '/' + res.date.Y;

                    syncCheckout(val);

                    const curMinDate = localStorage.getItem('mindate') || kv_object.check_start_date;

                    initCheckoutPickers(curMinDate, $el[0]);

                }

            };



            if (defaultDate) opts.defaultDate = defaultDate;



            $el.addClass('dateDropper').dateDropper(opts);

        });

    }



    function initCheckinPickers(skipEl) {

        $(CHECKIN_SEL).each(function () {

            if (skipEl && this === skipEl) return;

            const $el = $(this);

            const currentVal = $el.val();



            try { $el.dateDropper('destroy'); } catch (e) { }



            const opts = {

                large: 1,

                largeDefault: 1,

                preset: false,

                minDate: kv_object.check_start_date,

                maxDate: kv_object.check_end_date,

                format: 'd/m/Y',

                eventSelector: 'focus',

                onChange: function (res) {

                    injectPickerText();

                    const val = ('0' + res.date.d).slice(-2) + '/' + ('0' + res.date.m).slice(-2) + '/' + res.date.Y;

                    syncCheckin(val);



                    // Build checkout minDate in m/d/Y

                    let minDate = res.date.m + '/' + res.date.d + '/' + res.date.Y;

                    localStorage.setItem('mindate', minDate);



                    // Apply minimum nights gap

                    let gapDays = 0;

                    if (kv_object.check_min_days_option === '1' && kv_object.check_min_days !== '') {

                        gapDays = parseInt(kv_object.check_min_days, 10);

                        const gapDt = new Date(res.date.Y, res.date.m - 1, res.date.d);

                        gapDt.setDate(gapDt.getDate() + gapDays);

                        minDate = (gapDt.getMonth() + 1) + '/' + gapDt.getDate() + '/' + gapDt.getFullYear();

                    }



                    const defDays = parseInt(kv_object.default_days, 10) || gapDays;



                    // Auto-correct checkout to default_days if gap is too small

                    const curCheckout = $(CHECKOUT_SEL).first().val();

                    if (curCheckout && gapDays > 0) {

                        const ci = parseDMYDate(val);

                        const co = parseDMYDate(curCheckout);

                        if (ci && co && Math.ceil((co - ci) / 86400000) < gapDays) {

                            syncCheckout(getMinimumCheckoutDate(val, defDays));

                        }

                    }



                    // Default checkout to checkin + default_days if currently empty

                    if (!$(CHECKOUT_SEL).first().val()) {

                        const defDt = new Date(res.date.Y, res.date.m - 1, res.date.d);

                        defDt.setDate(defDt.getDate() + defDays);

                        syncCheckout(

                            ('0' + defDt.getDate()).slice(-2) + '/' +

                            ('0' + (defDt.getMonth() + 1)).slice(-2) + '/' +

                            defDt.getFullYear()

                        );

                    }



                    initCheckoutPickers(minDate);

                    initCheckinPickers($el[0]);

                }

            };



            if (currentVal) opts.defaultDate = toPickerDate(currentVal);



            $el.addClass('dateDropper').dateDropper(opts);

        });

    }



    // Restore saved values into fields before picker init so pickers open at the correct date

    if (savedCheckin) $(CHECKIN_SEL).val(savedCheckin);

    if (savedCheckout) $(CHECKOUT_SEL).val(savedCheckout);

    $(CHECKOUT_SEL).prop('disabled', false);



    // Initialise all pickers

    const initMinDate = localStorage.getItem('mindate') || kv_object.check_start_date;



    if ($(CHECKIN_SEL).length) initCheckinPickers();

    if ($(CHECKOUT_SEL).length) initCheckoutPickers(initMinDate);



    $(document).on('mousedown', '.pick-lg li.pick-v', function () {

        $(CHECKIN_SEL + ', ' + CHECKOUT_SEL).each(function () {

            if ($(this).hasClass('dateDropper')) {

                try { $(this).dateDropper('hide'); } catch (e) { }

            }

        });

    });





    /** Check in Checkout script */

    $(document).on('click', '.rb-toggle-long-desc', function (e) {

        e.preventDefault();

        e.stopPropagation();



        const $box = jQuery(this).closest('.rb-rateplan-box');

        const $longDesc = $box.find('.rb-long-desc');



        $longDesc.stop(true, true).slideToggle(200);

    });



    async function initFlywireCheckout(sessionId) {



        const sdk = await window.FlywireSDK("fk_MTVDTkhPVTZKeEtOQmpmVGZPc0NXZz09");



        const elements = await sdk.elements();



        const checkout = await elements.create("payment", {

            sessionId: sessionId,

            displayMode: "container"

        });



        checkout.onEvent("success", (event) => {

            // DO NOT trust frontend — wait for webhook



            let form = $('#gform_3'),

                payment_id = $('#input_3_17').val(),

                hotel_data = localStorage.getItem('rb_cart');

            $.ajax({

                url: kv_object.ajaxurl,

                method: "POST",

                dataType: "json",

                data: {

                    action: "add_other_data_in_fw",

                    payload: {

                        payment_id: payment_id,

                        hotel_data: hotel_data,

                    }

                },

                success: function (res) {

                },

                error: function (xhr, exception) {

                    var msg = "";

                    if (xhr.status === 0) {

                        msg = "Not connect.\n Verify Network." + xhr.responseText;

                    } else if (xhr.status == 404) {

                        msg = "Requested page not found. [404]" + xhr.responseText;

                    } else if (xhr.status == 500) {

                        msg = "Internal Server Error [500]." + xhr.responseText;

                    } else if (exception === "") {

                        msg = "Requested JSON parse failed.";

                    } else if (exception === "timeout") {

                        msg = "Time out error." + xhr.responseText;

                    } else if (exception === "abort") {

                        msg = "Ajax request aborted.";

                    } else {

                        msg = "Error:" + xhr.status + " " + xhr.responseText;

                    }



                    console.error('error');

                    console.error(msg);

                }

            });



            /*hide flywire and show confirmation message*/

            $('#flywire_box').hide();

            $('.quote_form').trigger('submit');

            $('.booking-confirmation-form').show();

        });



        checkout.onEvent("error", (event) => {

            console.error("❌ Payment error", event);

            console.error(JSON.stringify(event, null, 2));



            $.ajax({

                url: kv_object.ajaxurl,

                method: "POST",

                dataType: "json",

                data: {

                    action: "add_other_data_in_fw",

                    payload: {

                        payment_id: payment_id,

                        hotel_data: hotel_data,

                    }

                },

                success: function (res) {

                },

                error: function (xhr, exception) {

                    var msg = "";

                    if (xhr.status === 0) {

                        msg = "Not connect.\n Verify Network." + xhr.responseText;

                    } else if (xhr.status == 404) {

                        msg = "Requested page not found. [404]" + xhr.responseText;

                    } else if (xhr.status == 500) {

                        msg = "Internal Server Error [500]." + xhr.responseText;

                    } else if (exception === "") {

                        msg = "Requested JSON parse failed.";

                    } else if (exception === "timeout") {

                        msg = "Time out error." + xhr.responseText;

                    } else if (exception === "abort") {

                        msg = "Ajax request aborted.";

                    } else {

                        msg = "Error:" + xhr.status + " " + xhr.responseText;

                    }



                    console.error('error');

                    console.error(msg);

                }

            });

        });



        checkout.mount("flywire_box");

    }



    function getValidFlywireSession() {

        const stored = localStorage.getItem('flywire_session');

        if (!stored) return null;



        const session = JSON.parse(stored);



        // Convert the UTC string from Flywire into a JS Date Object

        // JS handles the "+00:00" offset automatically

        const expiryTime = new Date(session.expires_at);



        // Get the current time as a Date Object

        const currentTime = new Date();



        // Compare them. If current time is greater than expiry, it's dead.

        if (currentTime >= expiryTime || currentTime.getTime() > (expiryTime.getTime() - 60000)) {

            localStorage.removeItem('flywire_session');

            return null;

        }



        return session;

    }



    $(document).on('gform_post_render', function (event, formId) {



        // Re-initialise date pickers whenever form 1 (enquiry form) renders

        if (formId === 1) {

            const ci = localStorage.getItem('niseko_checkin');

            const co = localStorage.getItem('niseko_checkout');

            if (ci) $(CHECKIN_SEL).val(ci);

            if (co) $(CHECKOUT_SEL).val(co);

            $(CHECKOUT_SEL).prop('disabled', false);

            const reMinDate = localStorage.getItem('mindate') || kv_object.check_start_date;

            if ($(CHECKIN_SEL).length) initCheckinPickers();

            if ($(CHECKOUT_SEL).length) initCheckoutPickers(reMinDate);



            // Sync visual eq-popover inputs from GF fields.

            // GF preserves submitted values in the hidden selects on validation re-render,

            // so we read from them instead of localStorage (which belongs to the search bar).

            var reAdults = parseInt(enquiryFind('#input_1_7').val(), 10);

            var reChildren = parseInt(enquiryFind('#input_1_10').val(), 10);

            var reInfants = parseInt(enquiryFind('#input_1_42').val(), 10);



            if (reAdults > 0) { $('.eq-adults').val(reAdults); }

            if (reChildren >= 0) { $('.eq-children').val(reChildren); }

            if (reInfants >= 0) { $('.eq-infants').val(reInfants); }

            // Keep popup open after validation, and restore the parked (unused) form
            // so it does not inherit validation messages from the form that was submitted.
            if (typeof unparkEnquiryForm === 'function') {
                unparkEnquiryForm();
            }
            if ($('body').hasClass('enquire-open')) {
                $('.Enquiry-modal').addClass('active');
                if (typeof scrollEnquiryModalToValidation === 'function') {
                    scrollEnquiryModalToValidation();
                }
            }

        }



        if (formId !== 3) return;



        const $form = $(`#gform_${formId}`);

        const $trigger = $('#flywire-trigger');

        // const room_type = kv_roomtype_get();



        // if( room_type !== 'bedbank' ){



        // If the trigger exists, validation "failed" on purpose for payment

        if ($trigger.length > 0) {



            let fw_session_obj = getValidFlywireSession(),

                fw_session_id = '';



            // 1. Hide the generic GF error message so it looks professional

            $('.validation_error').hide();



            let firstName = $form.find("input[name='input_11']").val() || "Unknown";

            let lastName = $form.find("input[name='input_12']").val() || "Unknown";

            let email = $form.find("input[name='input_3']").val() || "unknown@example.com";

            let phone = $form.find("input[name='input_13']").val() || "090078601";

            let country = $form.find("select[name='input_5']").val() || "Japan";

            let city = $form.find("input[name='input_8']").val() || "Tokyo Metropolis";

            let postcode = $form.find("input[name='input_9']").val() || "09876";

            let lang = $form.find("select[name='input_6']").val() || "en";

            let address = $form.find("textarea[name='input_10']").val() || "Default Address";

            let deposit = $('#deposit-amount').attr('data-price') || "0";



            $('.fw_total input').attr('value', deposit);



            // 2. Run your existing AJAX to get the session

            if (fw_session_obj === null) {



                $.ajax({

                    url: kv_object.ajaxurl,

                    method: "POST",

                    dataType: "json",

                    data: {

                        action: "create_flywire_session",

                        payload: {

                            firstName: firstName,

                            lastName: lastName,

                            email: email,

                            phone: phone,

                            address: address,

                            city: city,

                            country: country,

                            postcode: postcode,

                            amount: deposit,

                            lang: lang,

                        }

                    },

                    success: function (res) {

                        if (res.success && res.data.id) {

                            localStorage.setItem('flywire_session', JSON.stringify(res.data));

                            fw_session_id = res.data.id;



                            $form.find('input[name="input_17"]').val(fw_session_id);

                            $('.booking-confirmation-form').hide();

                            initFlywireCheckout(fw_session_id);

                        }

                    },

                    error: function (xhr, exception) {

                        var msg = "";

                        if (xhr.status === 0) {

                            msg = "Not connect.\n Verify Network." + xhr.responseText;

                        } else if (xhr.status == 404) {

                            msg = "Requested page not found. [404]" + xhr.responseText;

                        } else if (xhr.status == 500) {

                            msg = "Internal Server Error [500]." + xhr.responseText;

                        } else if (exception === "") {

                            msg = "Requested JSON parse failed.";

                        } else if (exception === "timeout") {

                            msg = "Time out error." + xhr.responseText;

                        } else if (exception === "abort") {

                            msg = "Ajax request aborted.";

                        } else {

                            msg = "Error:" + xhr.status + " " + xhr.responseText;

                        }



                        console.error(msg);

                    }



                });

            }

            else {

                fw_session_id = fw_session_obj.id;



                $form.find('input[name="input_17"]').val(fw_session_id);

                $('.booking-confirmation-form').hide();

                initFlywireCheckout(fw_session_id);

            }

        }

        // }



        /* API work will be here*/

    });



    $(document).on('click', '   .accom-content .book_btn', function (e) {

        let value = $(this).data('room_id'),

            a = $(this).parents('a'),

            url = a.attr('href');

        localStorage.setItem('go_to_form', value);

        window.location.href = url;

    });



    function hz_ajax_error(xhr, exception) {

        var msg = "";

        if (xhr.status === 0) {

            alert("Internet not connected.\n Verify Network.");

        } else if (exception === "timeout") {

            alert("Request time out. \n Please try again");

        } else if (exception === "abort") {

            alert("Ajax request aborted.");

        } else if (xhr.status == 404) {

            msg = "Requested page not found. [404]" + xhr.responseText;

        } else if (xhr.status == 500) {

            msg = "Internal Server Error [500]." + xhr.responseText;

        } else if (exception === "") {

            msg = "Requested JSON parse failed.";

        } else {

            msg = "Error:" + xhr.status + " " + xhr.responseText;

        }



        console.error(msg);

    }



    function kv_booking_cart_get() {

        return JSON.parse(localStorage.getItem('rb_cart') || '{"items":[]}');

    }



    function kv_booking_cart_set(cart) {

        localStorage.setItem('rb_cart', JSON.stringify(cart));

    }



    function kv_roomtype_get() {

        var cart = kv_booking_cart_get();

        if (cart?.items?.length > 0) {

            return cart.items[0].room_type;

        }

    }



    /*slide up dropdown results when clicked outside*/

    $(document).on("click", function (event) {

        var $container = $(".dropdown_results"); // The div you want to slide up



        // If the click is NOT on the container and NOT on a child of the container

        if (!$container.is(event.target) && $container.has(event.target).length === 0) {

            $container.slideUp("fast");

        }

    });



    document.querySelectorAll('.faq-q').forEach(function (btn) {

        btn.addEventListener('click', function () {

            var isOpen = btn.classList.contains('open');

            document.querySelectorAll('.faq-q').forEach(function (b) {

                b.classList.remove('open');

                var a = b.nextElementSibling;

                if (a) a.classList.remove('visible');

            });

            if (!isOpen) {

                btn.classList.add('open');

                var answer = btn.nextElementSibling;

                if (answer) answer.classList.add('visible');

            }

        });

    });



    let heroCard = null;



    if (!shouldAddClasses) {

        jQuery(window).on('scroll', function ($) {

            if (!heroCard) {

                heroCard = jQuery('.js-search-card')[1];

            }

            let threshold = heroCard

                ? (jQuery(heroCard).offset().top + jQuery(heroCard).outerHeight() - 100)

                : 300;



            jQuery('header , .mobPopWrapper').toggleClass('showHeadarFilter', jQuery(window).scrollTop() > threshold);



            let filter_section = jQuery('section.hero-banner-with-filter'),

                topHeader = jQuery('topHeader'),

                filter_height = filter_section.length > 0 ? filter_section.outerHeight() : 0,

                topHeader_height = topHeader.length > 0 ? topHeader.outerHeight() : 0,

                scroll_top = jQuery(window).scrollTop();



            jQuery('.sticky-cta-container').toggleClass('active', scroll_top >= (filter_height - topHeader_height));



        });

    }



    /* ── Mobile search modal ── */

    $('.mobPopWrapper .openPop').on('click', function () {

        $('.mobFilterModal').addClass('open');

    })

    $('.closeMobSearch').on('click', function () {

        $(this).parents('.mobFilterModal').removeClass('open');

    });

    $('.mobFilterModal').on('click', function (e) {

        if ($(e.target).is('.mobFilterModal')) {

            $(this).removeClass('open');

        }

    });



    $('.backToResultsWrap').on('click', function (e) {

        localStorage.setItem('go_to_main_listing', '1');

    });



    let to_main_listing = localStorage.getItem('go_to_main_listing'),

        header_height = $('header').outerHeight() || 0;



    if (to_main_listing !== null) {

        $('html, body').animate({

            scrollTop: ($('#accom-search-form').offset().top - header_height)

        }, 400);



        localStorage.removeItem('go_to_main_listing');

    }



    // if( window.location.origin + window.location.pathname == base_url+'/accommodation/' ){

    //     alert( 'inside page' );

    //     localStorage.removeItem('sb-resort');

    //     jQuery('#sb-resort').val('');

    //     // Optional: trigger the change event if other scripts depend on it

    //     jQuery('#sb-resort').trigger('change');

    // }



    $(document).on('click', '.location-modal-close', function (e) {

        let parent = $(this).parents('#location-info-modal');

        parent.removeClass('open');

    });



    $(document).on('click', '#open-location-info', function (e) {

        let modal = $('#location-info-modal');

        modal.addClass('open');

    });



    $(document).on('click', '.sb-field', function (e) {

        // If the click is already on the input/select or if it's the guests field (which handles its own clicks), return.

        if ($(e.target).is('input, select, .js-sb-guests-display') || $(this).hasClass('sb-guests')) {

            return;

        }



        const $targetChild = $(this).find('input.sb-input, select.sb-select');



        if ($targetChild.length) {

            // Trigger focus for inputs (to open date pickers) or click for selects

            $targetChild.trigger($targetChild.is('input') ? 'focus' : 'click');

        }

    });



    $(document).on('click', '.enq-btn', function (e) {

        e.preventDefault();



        // Get room title and hotel name from button attributes

        const roomTitle = $(this).attr('room-title');

        const hotelName = $(this).attr('hotel-name');

        const resortName = $(this).attr('resort-name');



        // Save to localStorage

        if (roomTitle) {

            localStorage.setItem('enquiry_room_title', roomTitle);

        }



        if (hotelName) {

            localStorage.setItem('enquiry_hotel_name', hotelName);

        }



        if (resortName) {

            localStorage.setItem('enquiry_resort_name', resortName);

        }



        // Redirect to /about/

        window.location.href = '/enquire/';

    });





    // Populate quote form with room and hotel details from localStorage

    if ($('form.quote_form').length) {

        const roomTitle = localStorage.getItem('enquiry_room_title') ?? '';

        const hotelName = localStorage.getItem('enquiry_hotel_name') ?? '';

        const resortName = localStorage.getItem('enquiry_resort_name') ?? '';



        if (roomTitle) {

            $('.room_name input').val(roomTitle);

        }

        if (hotelName) {

            $('.property_name textarea').val(hotelName);

            $('.property_name textarea').addClass('disabled');

        }

        if (resortName) {

            $('.resort_name select').val(resortName);

            $('.resort_name select').addClass('disabled');

        }



        if (roomTitle || hotelName || resortName) {





            localStorage.removeItem('enquiry_room_title');

            localStorage.removeItem('enquiry_hotel_name');

            localStorage.removeItem('enquiry_resort_name');



            $('.enquiry_type input').attr('value', 'Product');



            $('.room_name input, .property_name textarea, .resort_name select, .enquiry_type input').trigger('change');

        }

    }



    $(document).on('click', '.acc-gallery span', function (e) {

        var prev_item = $(this).prev();

        prev_item.trigger('click');

    });



    $(document).on('click', '#sc-guests-popup', function (e) {

        var g_popup = $('#room-filter-guests-popover');

        g_popup.addClass('active');

    });



    if ($('.eq-guests-popover').length > 0) {

        let adults = localStorage.getItem('sb_adults') !== null ? parseInt(localStorage.getItem('sb_adults')) : 2,

            children = localStorage.getItem('sb_children') !== null ? parseInt(localStorage.getItem('sb_children')) : 0,

            infants = localStorage.getItem('sb_infants') !== null ? parseInt(localStorage.getItem('sb_infants')) : 0;



        $('.eq-adults').attr('value', adults);

        enquiryFind('#input_1_7').val(adults).trigger('change');



        $('.eq-children').attr('value', children);

        enquiryFind('#input_1_10').val(children);

        // no trigger('change') here — avoids opening the child age popup on page load



        $('.eq-infants').attr('value', infants);

        enquiryFind('#input_1_42').val(infants).trigger('change');

    }



    var cart = kv_booking_cart_get();

    var items = cart?.data?.items;

    if (items?.length > 0) {

        $('.sticky-cart-container .item-count').text(items.length);

    }



});/* end jquery */



document.addEventListener('DOMContentLoaded', function () {



    const initialAdults = parseInt(localStorage.getItem('sb_adults'), 10);

    const initialChildren = parseInt(localStorage.getItem('sb_children'), 10);

    const initialInfants = parseInt(localStorage.getItem('sb_infants'), 10);



    document.querySelectorAll('.search-card').forEach(function (card) {



        let g = {



            adults: Number.isFinite(initialAdults) && initialAdults > 0 ? initialAdults : 2,



            children: Number.isFinite(initialChildren) && initialChildren >= 0 ? initialChildren : 0,



            infants: Number.isFinite(initialInfants) && initialInfants >= 0 ? initialInfants : 0



        };



        let guestPopOpen = false;



        const el = {



            display: card.querySelector('.js-sb-guests-display'),

            field: card.querySelector('.sb-guests'),

            pop: card.querySelector('.guests-popover'),

            adultsVal: card.querySelector('.js-v-adults'),

            childrenVal: card.querySelector('.js-v-children'),

            infantsVal: card.querySelector('.js-v-infants'),

            mAdults: card.querySelector('.js-m-adults'),

            mChildren: card.querySelector('.js-m-children'),

            mInfants: card.querySelector('.js-m-infants'),

            btnAM: card.querySelector('.js-btn-adults-minus'),

            btnCM: card.querySelector('.js-btn-children-minus'),

            btnIM: card.querySelector('.js-btn-infants-minus'),



        };



        if (!el.display || !el.pop) return;



        function renderGuests() {



            localStorage.setItem('sb_adults', String(g.adults));

            localStorage.setItem('sb_children', String(g.children));

            localStorage.setItem('sb_infants', String(g.infants));



            const totalGuests = g.adults + g.children;

            let label = `${totalGuests} Guest${totalGuests !== 1 ? 's' : ''}`;



            if (g.infants > 0) {

                label += `<br>${g.infants} Infant${g.infants !== 1 ? 's' : ''}`;

            }



            // For HTML display fields

            jQuery('.js-sb-guests-display, .js-guests-display').each(function () {

                jQuery(this).html(label).removeClass('empty');

            });



            // For input field: original form + popup form

            const inputLabel = label.replace('<br>', ', ');

            jQuery('.sv-guests').val(inputLabel);



            if (el.display) {

                el.display.classList.toggle('empty', totalGuests <= 0 && g.infants <= 0);

            }



            jQuery('.js-v-adults').text(g.adults);

            jQuery('.js-v-children').text(g.children);

            jQuery('.js-v-infants').text(g.infants);



            jQuery('.js-m-adults').val(g.adults);

            jQuery('.js-m-children').val(g.children);

            jQuery('.js-m-infants').val(g.infants);



            if (el.btnAM) el.btnAM.disabled = g.adults <= 1;



            jQuery('.js-btn-adults-minus').prop('disabled', g.adults <= 1);

            jQuery('.js-btn-children-minus').prop('disabled', g.children <= 0);

            jQuery('.js-btn-infants-minus').prop('disabled', g.infants <= 0);



            if (el.btnIM) el.btnIM.disabled = g.infants <= 0;



            if (el.mAdults) el.mAdults.value = g.adults;

            if (el.mChildren) el.mChildren.value = g.children;

            if (el.mInfants) el.mInfants.value = g.infants;



        }



        el.field.addEventListener('click', function (e) {



            e.stopPropagation();



            guestPopOpen = !guestPopOpen;



            el.pop.classList.toggle('open', guestPopOpen);



        });



        document.addEventListener('click', function (e) {



            if (!guestPopOpen) return;



            if (!card.contains(e.target)) {



                guestPopOpen = false;



                el.pop.classList.remove('open');



            }



        });



        function bindGuestButtons(scope) {

            if (!scope) return;



            scope.querySelectorAll('.g-btn').forEach(btn => {



                // Prevent duplicate click binding

                if (btn.dataset.guestBound === '1') return;

                btn.dataset.guestBound = '1';



                btn.addEventListener('click', function (e) {

                    e.preventDefault();



                    const row = this.closest('.g-row');



                    const applyLocalAdjust = function (type, delta) {

                        if (type === 'adults') {

                            g.adults = Math.max(1, (g.adults || 0) + delta);

                        } else if (type === 'children') {

                            g.children = Math.max(0, (g.children || 0) + delta);

                        } else if (type === 'infants') {

                            g.infants = Math.max(0, (g.infants || 0) + delta);

                        }



                        renderGuests();

                    };



                    const runAdjust = function (type, delta) {

                        if (typeof window.adj === 'function') {



                            // Always trust JS state, never DOM

                            const current = {

                                adults: g.adults,

                                children: g.children,

                                infants: g.infants

                            };



                            current[type] = Math.max(

                                type === 'adults' ? 1 : 0,

                                (current[type] || 0) + delta

                            );



                            g = current;



                            renderGuests();

                            return;

                        }



                        applyLocalAdjust(type, delta);

                    };



                    if (this.classList.contains('js-btn-adults-minus')) {

                        runAdjust('adults', -1);

                    } else if (this.classList.contains('js-btn-children-minus')) {

                        runAdjust('children', -1);

                        if (g.children > 0) { triggerChildAgePopup(g.children); }

                    } else if (this.classList.contains('js-btn-infants-minus')) {

                        runAdjust('infants', -1);

                    } else if (row && row.querySelector('.js-v-adults')) {

                        runAdjust('adults', 1);

                    } else if (row && row.querySelector('.js-v-children')) {

                        runAdjust('children', 1);

                        if (g.children > 0) { triggerChildAgePopup(g.children); }

                    } else if (row && row.querySelector('.js-v-infants')) {

                        runAdjust('infants', 1);

                    }

                });



            });

        }



        // Existing page form / card

        bindGuestButtons(card);



        // Popup form

        bindGuestButtons(document.getElementById('room-search-popup-modal'));

        bindGuestButtons(document.getElementById('room-filter-guests-popover'));



        jQuery(document).on('click', '.sv-guests', function (e) {

            e.preventDefault();

            e.stopPropagation();



            const $form = jQuery(this).closest('form');

            const $popover = $form.find('.room-filter-guests-popover');



            jQuery('.room-filter-guests-popover').not($popover).removeClass('active');

            $popover.toggleClass('active');

        });



        jQuery(document).on('click', '.room-filter-guests-popover', function (e) {

            e.stopPropagation();

        });



        jQuery(document).on('click', function (e) {

            if (!jQuery(e.target).closest('.sv-guests, .room-filter-guests-popover').length) {

                jQuery('.room-filter-guests-popover').removeClass('active');

            }

        });



        function syncMobile() {



            g.adults = Math.max(1, parseInt(el.mAdults?.value || 2));



            g.children = Math.max(0, parseInt(el.mChildren?.value || 0));



            g.infants = Math.max(0, parseInt(el.mInfants?.value || 0));



            renderGuests();



        }



        if (el.mAdults) el.mAdults.addEventListener('change', syncMobile);



        if (el.mChildren) el.mChildren.addEventListener('change', syncMobile);



        if (el.mInfants) el.mInfants.addEventListener('change', syncMobile);



        renderGuests();



    });



    /* =========================
  
       LEAFLET MAP SCRIPT
  
  ========================= */



    if (typeof nearbyData === "undefined" || nearbyData.length === 0) return;



    /* =========================

       INIT MAP

    ========================= */

    const map = L.map('nearby-map').setView(

        [mainLocation.lat, mainLocation.lng],

        15

    );



    // L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {

    //     attribution: ''

    // }).addTo(map);



    // L.tileLayer('https://stadiamaps.com/{z}/{x}/{y}{r}.png', {

    //     attribution: ''

    // }).addTo(map);



    const stadiaKey = "c417ca3b-5448-48cd-b861-22d28bc5fc27";



    L.tileLayer(`https://tiles.stadiamaps.com/tiles/osm_bright/{z}/{x}/{y}{r}.png?api_key=${stadiaKey}`, {

        attribution: '&copy; OpenStreetMap contributors &copy; Stadia Maps',

        maxZoom: 20

    }).addTo(map);



    // L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {

    //     attribution: ''

    // }).addTo(map);



    /* =========================

       ICONS

    ========================= */

    const greenIcon = L.icon({

        iconUrl: "https://maps.google.com/mapfiles/ms/icons/green-dot.png",

        iconSize: [40, 40]

    });



    const redIcon = L.icon({

        iconUrl: themeUrl + "/images/icons/placeholder.png",

        iconSize: [32, 32]

    });



    /* =========================

       MAIN MARKER

    ========================= */

    const mainMarker = L.marker(

        [mainLocation.lat, mainLocation.lng],

        { icon: greenIcon }

    ).addTo(map);



    const mainPopup = `

        <div style="padding:10px; font-size:14px;">

            <strong>${mainLocation.title}</strong><br>

            ${mainLocation.address}

        </div>

    `;



    mainMarker.bindPopup(mainPopup).openPopup();



    /* =========================

       NEARBY MARKERS

    ========================= */

    const markers = [];



    nearbyData.forEach((item, index) => {



        const lat = parseFloat(item.lat);

        const lng = parseFloat(item.lng);



        const marker = L.marker([lat, lng], { icon: redIcon })

            .addTo(map)

            .bindPopup(`

                <div style="font-size:14px;">

                    <strong>${item.title}</strong><br>

                    ${item.km} km away

                </div>

            `);



        markers.push(marker);



        marker.on("click", function () {

            marker.openPopup();

            map.setView([lat, lng], 16);

            highlightItem(index);

        });

    });



    var items = kv_object.loc_items ? kv_object.loc_items : [];

    hide_landmark_items(items);



    /* =========================

       AUTO FIT ALL MARKERS

    ========================= */

    const group = new L.featureGroup(markers.concat([mainMarker]));

    map.fitBounds(group.getBounds().pad(0.2));



    /* =========================

       SIDEBAR CLICK

    ========================= */

    document.querySelectorAll(".nearby-item").forEach((el) => {

        el.addEventListener("click", function () {

            const i = this.dataset.index;

            markers[i].fire("click");

        });

    });



    /* =========================

       HIGHLIGHT ACTIVE ITEM

    ========================= */

    function highlightItem(index) {

        document.querySelectorAll(".nearby-item").forEach(el => el.classList.remove("active"));



        const current = document.querySelector('.nearby-item[data-index="' + index + '"]');



        if (current) {

            current.classList.add("active");

            // current.scrollIntoView({ behavior: "smooth", block: "center" });

        }

    }



    function hide_landmark_items(items = []) {

        document.querySelectorAll(".nearby-item").forEach(el => {

            /* if item value is in the items array add class hide to item */

            const title = el.querySelector('span').innerText.trim();

            if (items.includes(title)) {

                el.classList.add("hide");

            }

        });

    }



});

