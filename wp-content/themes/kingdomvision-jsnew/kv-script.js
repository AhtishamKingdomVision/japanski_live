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

        $('.mob_quote_inner').find('#input_1_66, select[name="input_66"], .resort_name select').val(resort);

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

    var isRestricted = cart_restricted_paths.some(function (path) {
        if (!path) return false;
        return pathname === path || pathname.indexOf(path) !== -1;
    });

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
    var kvChildAgeTargetScope = null;

    function kvGetEnquiryGuestScope($el) {
        const $scope = $el.closest('.gform_wrapper, .mob_quote_form1, .Enquiry-modal-content, .acc_enquiry_form');
        return $scope.length ? $scope : $();
    }

    function kvWriteChildAgesToScope($scope) {
        if (!$scope || !$scope.length) return;
        $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {
            let val = $(value).val();
            let child = $(value).data('child');
            $scope.find('.' + child + ' input').val(val);
        });
    }

    function kvGetStoredChildAge(child) {
        const val = localStorage.getItem('sb_' + child);
        return val && val !== '0' ? val : '';
    }

    function kvApplyStoredChildAgesToPopup(noOfChilds) {
        const childCount = parseInt(noOfChilds, 10) || 0;

        $('section.child_age .ch_inn ul li').each(function (index) {
            const $item = $(this);
            const $select = $item.find('select');
            const child = $select.data('child');

            if ((index + 1) > childCount) {
                $item.hide();
                $select.val('0');
                return;
            }

            $item.show();
            $select.val(kvGetStoredChildAge(child) || '0');
        });
    }

    function kvWriteStoredChildAgesToScope($scope, noOfChilds) {
        if (!$scope || !$scope.length) return;

        const childCount = parseInt(noOfChilds, 10) || 0;

        for (let i = 1; i <= 15; i++) {
            const child = 'child_' + i;
            const val = i <= childCount ? kvGetStoredChildAge(child) : '';
            $scope.find('.' + child + ' input').val(val);
        }
    }

    window.kvWriteStoredChildAgesToScope = kvWriteStoredChildAgesToScope;

    $(document).on('change', '.rec_children select, #input_1_10, #input_4_10', function (e) {

        let noOfChilds = $(this).val();
        kvChildAgeTargetScope = kvGetEnquiryGuestScope($(this));

        if (noOfChilds == '')
            return;

        if (noOfChilds == '0') {
            kvWriteStoredChildAgesToScope(kvChildAgeTargetScope, 0);
            return;
        }

        $('section.child_age').addClass('active');
        kvApplyStoredChildAgesToPopup(noOfChilds);

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

        const $scope = kvChildAgeTargetScope && kvChildAgeTargetScope.length
            ? kvChildAgeTargetScope
            : ($('body').hasClass('enquire-open') ? $('.Enquiry-modal-content') : $('.acc_enquiry_form'));

        $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {

            let val = $(value).val();

            let child = $(value).data('child');

            $scope.find('.' + child + ' input').val(val);

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

    function normalizeResortName(value) {
        if (!value) return '';

        return String(value)
            .replace(/-accommodation$/i, '')
            .replace(/\s+Accommodation$/i, '')
            .replace(/-/g, ' ')
            .trim()
            .replace(/\b\w/g, function (char) {
                return char.toUpperCase();
            });
    }

    function getResortOptions($resortField) {
        return $resortField.find('option').map(function () {
            return {
                rawValue: String(this.value || '').trim(),
                value: normalizeResortName(this.value),
                text: normalizeResortName(jQuery(this).text())
            };
        }).get().filter(function (option) {
            const text = option.text.toLowerCase();
            return option.value
                && option.value.toLowerCase() !== 'all'
                && text !== 'resort'
                && text.indexOf('resort *') === -1;
        });
    }

    function matchResortOption(options, resortName) {
        const normalized = normalizeResortName(resortName).toLowerCase();
        if (!normalized) return null;

        return options.find(function (option) {
            return option.value.toLowerCase() === normalized
                || option.text.toLowerCase() === normalized;
        }) || null;
    }

    // Only treat URLs like /hakuba/accommodation/ as a locked resort page.
    // Plain /accommodation/ must NOT lock the Resort field.
    function getUrlResortName($resortField) {
        const options = getResortOptions($resortField);
        const path = window.location.pathname.toLowerCase();
        const pathParts = path.split('/').filter(Boolean);

        // /accommodation/ or /.../accommodation/ with no resort segment before it
        const accommodationIndex = pathParts.findIndex(function (part) {
            return part === 'accommodation' || part.endsWith('-accommodation');
        });

        if (accommodationIndex === -1) {
            return '';
        }

        // Exact "/accommodation/" root listing — never lock from URL
        if (pathParts[accommodationIndex] === 'accommodation') {
            const previous = accommodationIndex > 0 ? pathParts[accommodationIndex - 1] : '';
            if (!previous) {
                return '';
            }

            // /hakuba/accommodation/...
            const match = matchResortOption(options, previous);
            return match ? (match.rawValue || match.value || match.text) : '';
        }

        // /hakuba-accommodation/...
        if (pathParts[accommodationIndex].endsWith('-accommodation')) {
            const slugResort = pathParts[accommodationIndex].replace(/-accommodation$/, '');
            const match = matchResortOption(options, slugResort);
            return match ? (match.rawValue || match.value || match.text) : '';
        }

        return '';
    }

    function setEnquiryResortField($resortField, resortName, isLocked) {
        const options = getResortOptions($resortField);
        const match = matchResortOption(options, resortName);

        if (match) {
            $resortField.val(match.rawValue || match.value);
        } else if (normalizeResortName(resortName)) {
            $resortField.val(normalizeResortName(resortName));
        } else {
            $resortField.val('');
        }

        // Lock when resort is known (URL page or selected property). Keep value submittable.
        $resortField
            .toggleClass('disabled', !!isLocked)
            .prop('disabled', false)
            .attr('aria-disabled', isLocked ? 'true' : 'false')
            .attr('tabindex', isLocked ? '-1' : '0')
            .trigger('change');
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

        const $resortField = $scope.find('#input_1_66, select[name="input_66"], .resort_name select').first();
        const urlResortName = getUrlResortName($resortField);
        // Prefer URL resort when on a resort page; otherwise use the selected property resort.
        const resortName = urlResortName || data.resortName || '';
        // Lock whenever resort is known (from URL or selected property).
        setEnquiryResortField($resortField, resortName, !!resortName);

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

        // Prefill → lock BBF bar until user clicks Change
        $scope.find('.gform_wrapper.quote_form_wrapper').attr('data-bbf-unlocked', '0');
        syncEnquiryBbfLock($scope);
        setTimeout(function () { syncEnquiryBbfLock($scope); }, 200);
        setTimeout(function () { syncEnquiryBbfLock($scope); }, 600);
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
            populateEnquiryModal({
                propertyName: propertyName,
                resortName: $card.data('resortName') || $btn.attr('resort-name') || ''
            });
            return;
        }

        populateEnquiryModal({
            propertyName: $btn.attr('hotel-name') || '',
            resortName: $btn.attr('resort-name') || '',
            roomName: $btn.attr('room-title') || ''
        });
    });

    $(document).on('mousedown keydown', '.resort_name select.disabled, #input_1_66.disabled, select[name="input_66"].disabled', function (e) {
        e.preventDefault();
    });

    // BBF bar: onchange → lock when all 4 filled; Change → unlock editable
    function getEnquiryFormWrappers($from) {
        if ($from && $from.length) {
            if ($from.is('.gform_wrapper.quote_form_wrapper')) return $from;
            const $closest = $from.closest('.gform_wrapper.quote_form_wrapper');
            if ($closest.length) return $closest;
            const $found = $from.find('.gform_wrapper.quote_form_wrapper');
            if ($found.length) return $found;
        }
        return $('.gform_wrapper.quote_form_wrapper');
    }

    function getEnquiryBbfFields($wrap) {
        const $resort = $wrap.find('.resort_name select, select[name="input_66"], #input_1_66').first();
        const $checkIn = $wrap.find('input[name="input_5"], #input_1_5').first();
        let $checkOut = $wrap.find('input[name="input_6"], #input_1_6').first();
        if (!$checkIn.length) {
            // fallback calendar inputs in BBF row
        }
        const $calInputs = $wrap.find('.gfield.bbf.calender_icon input, .calender_icon input');
        const $ci = $checkIn.length ? $checkIn : $calInputs.eq(0);
        const $co = $checkOut.length ? $checkOut : $calInputs.eq(1);

        return {
            $resort: $resort,
            $checkIn: $ci,
            $checkOut: $co,
            $guests: $wrap.find('.eq-sb-guests-display, .guest_input > span').first(),
            $dates: $().add($ci).add($co)
        };
    }

    function enquiryBbfAllFilled($wrap) {
        const f = getEnquiryBbfFields($wrap);
        const resort = (f.$resort.val() || '').toString().trim();
        const checkIn = (f.$checkIn.val() || '').toString().trim();
        const checkOut = (f.$checkOut.val() || '').toString().trim();
        const guestLabel = (f.$guests.text() || '').trim();
        const adults = parseInt($wrap.find('.rec_adults select').first().val(), 10);
        const hasGuests = /\d+\s*Guest/i.test(guestLabel) || (!isNaN(adults) && adults > 0);
        return !!(resort && checkIn && checkOut && hasGuests);
    }

    function setEnquiryBbfLocked($wrap, locked) {
        if (!$wrap || !$wrap.length) return;

        $wrap.toggleClass('bbf-fields-locked', !!locked);
        const f = getEnquiryBbfFields($wrap);

        if (locked) {
            f.$resort
                .addClass('disabled')
                .attr('aria-disabled', 'true')
                .attr('tabindex', '-1')
                .prop('disabled', false);
            f.$dates.prop('readonly', true).attr('tabindex', '-1');
            f.$guests.attr('aria-disabled', 'true');
            $wrap.find('.eq-guests-popover').removeClass('open');
        } else {
            f.$resort
                .removeClass('disabled')
                .attr('aria-disabled', 'false')
                .attr('tabindex', '0');
            f.$dates.prop('readonly', false).removeAttr('tabindex');
            f.$guests.removeAttr('aria-disabled');
        }
    }

    function syncEnquiryBbfLock($from) {
        getEnquiryFormWrappers($from).each(function () {
            const $wrap = $(this);
            if (!$wrap.find('.gfield.bbf').length) return;

            const allFilled = enquiryBbfAllFilled($wrap);
            const manuallyUnlocked = $wrap.attr('data-bbf-unlocked') === '1';

            if (!allFilled) {
                // Koi field empty → editable, next complete fill can lock again
                $wrap.attr('data-bbf-unlocked', '0');
                setEnquiryBbfLocked($wrap, false);
                refreshBbfToggleLabel($wrap);
                return;
            }

            // Sari fields filled → readonly (unless Change clicked)
            setEnquiryBbfLocked($wrap, !manuallyUnlocked);
            refreshBbfToggleLabel($wrap);
        });
    }

    window.kvSyncEnquiryBbfLock = syncEnquiryBbfLock;

    // onchange: jese hi sari fields filled → readonly
    const BBF_CHANGE_SEL = [
        '.gform_wrapper.quote_form_wrapper .resort_name select',
        '.gform_wrapper.quote_form_wrapper select[name="input_66"]',
        '.gform_wrapper.quote_form_wrapper input[name="input_5"]',
        '.gform_wrapper.quote_form_wrapper input[name="input_6"]',
        '.gform_wrapper.quote_form_wrapper #input_1_66',
        '.gform_wrapper.quote_form_wrapper #input_1_5',
        '.gform_wrapper.quote_form_wrapper #input_1_6',
        '.gform_wrapper.quote_form_wrapper .calender_icon input',
        '.gform_wrapper.quote_form_wrapper .rec_adults select',
        '.gform_wrapper.quote_form_wrapper .rec_children select'
    ].join(', ');

    $(document).on('change', BBF_CHANGE_SEL, function () {
        syncEnquiryBbfLock($(this));
    });

    // Guests counter buttons also affect filled state
    $(document).on('click', '.gform_wrapper.quote_form_wrapper .eq-guests-popover .g-btn', function () {
        const $wrap = $(this).closest('.gform_wrapper.quote_form_wrapper');
        setTimeout(function () { syncEnquiryBbfLock($wrap); }, 50);
    });

    // Change ⇄ Done toggle: sirf link ka text node badlo (icon safe rahe)
    function setBbfToggleText($a, text) {
        const textNode = $a.contents().filter(function () {
            return this.nodeType === 3 && this.nodeValue.trim().length;
        }).first();
        if (textNode.length) {
            textNode[0].nodeValue = text;
        } else {
            $a.prepend(document.createTextNode(text + ' '));
        }
    }

    function getBbfToggleLink($wrap) {
        return $wrap.find('.gfield.bbf a, .gfield a').filter(function () {
            return /change|done/i.test(($(this).text() || ''));
        }).first();
    }

    // Label ko lock state ke sath sync rakho: locked → "Change", unlocked → "Done"
    function refreshBbfToggleLabel($wrap) {
        const $a = getBbfToggleLink($wrap);
        if (!$a.length) return;
        const unlocked = $wrap.attr('data-bbf-unlocked') === '1';
        setBbfToggleText($a, unlocked ? 'Done' : 'Change');
    }

    // Change → editable, Done → wapis lock
    $(document).on('click', '.gform_wrapper.quote_form_wrapper .gfield a', function (e) {
        const label = ($(this).text() || '').replace(/\s+/g, ' ').trim();
        if (!/change|done/i.test(label)) return;

        e.preventDefault();
        e.stopPropagation();

        const $wrap = $(this).closest('.gform_wrapper.quote_form_wrapper');
        const isUnlocked = $wrap.attr('data-bbf-unlocked') === '1';

        if (isUnlocked) {
            // Done → agar sari fields filled hain to lock, warna editable rehne do
            $wrap.attr('data-bbf-unlocked', '0');
            syncEnquiryBbfLock($wrap);
        } else {
            // Change → editable
            $wrap.attr('data-bbf-unlocked', '1');
            setEnquiryBbfLocked($wrap, false);
        }

        refreshBbfToggleLabel($wrap);
    });

    // Block dateDropper / select while locked
    $(document).on('mousedown focus click', '.bbf-fields-locked .gfield.bbf input, .bbf-fields-locked .gfield.bbf select', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(this).blur();
        return false;
    });

    // Initial sync for already-filled forms
    syncEnquiryBbfLock();
    setTimeout(function () { syncEnquiryBbfLock(); }, 300);
    setTimeout(function () { syncEnquiryBbfLock(); }, 1000);

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

        $('.gform_wrapper.quote_form_wrapper').find('input[name="input_5"], #input_1_5').trigger('change');
        if (typeof window.kvSyncEnquiryBbfLock === 'function') {
            window.kvSyncEnquiryBbfLock();
        }

    }



    function syncCheckout(val) {

        $(CHECKOUT_SEL).val(val).prop('disabled', false);

        localStorage.setItem('niseko_checkout', val);

        localStorage.setItem('maxdate', toPickerDate(val));

        $('.gform_wrapper.quote_form_wrapper').find('input[name="input_6"], #input_1_6').trigger('change');
        if (typeof window.kvSyncEnquiryBbfLock === 'function') {
            window.kvSyncEnquiryBbfLock();
        }

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

    if (typeof syncEnquiryBbfLock === 'function') {
        syncEnquiryBbfLock();
    }



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

            // Prefill the enquiry Resort field (GF field 66) from the resort page URL
            // (e.g. /niseko/accommodation/) or the saved search resort, mirroring how
            // check-in/check-out are prefilled above. Never override an existing choice.
            $('.mob_quote_form1, .gform_wrapper.quote_form_wrapper, .acc_enquiry_form').each(function () {
                const $resortField = $(this).find('#input_1_66, select[name="input_66"], .resort_name select').first();
                if (!$resortField.length || $resortField.val()) return;

                const urlResort = getUrlResortName($resortField);
                let savedResort = localStorage.getItem('sb_resort') || '';
                if (savedResort.toLowerCase() === 'all') savedResort = '';

                const resortName = urlResort || savedResort;
                if (resortName) {
                    setEnquiryResortField($resortField, resortName, !!urlResort);
                }
            });

            const reMinDate = localStorage.getItem('mindate') || kv_object.check_start_date;

            if ($(CHECKIN_SEL).length) initCheckinPickers();

            if ($(CHECKOUT_SEL).length) initCheckoutPickers(reMinDate);



            // Sync visual eq-popover inputs from THIS form's GF fields only.
            // Use class selectors only — duplicate #input_1_* IDs are unsafe with .find().
            $('.mob_quote_form1, .gform_wrapper.quote_form_wrapper').each(function () {
                const $wrap = $(this);
                if (!$wrap.find('.rec_adults, .eq-adults').length) {
                    return;
                }

                var reAdults = parseInt($wrap.find('.rec_adults select').first().val(), 10);
                var reChildren = parseInt($wrap.find('.rec_children select').first().val(), 10);

                if (reAdults > 0) { $wrap.find('.eq-adults').val(reAdults); }
                if (!isNaN(reChildren) && reChildren >= 0) { $wrap.find('.eq-children').val(reChildren); }
                $wrap.find('.eq-infants').val(0);
                $wrap.find('.eq-infants').closest('.g-row').hide();
                $wrap.find('.rec_infants').closest('.gfield, .gfield_html, li, .guest_search').hide();
            });

            if (typeof window.kvRefreshEnquiryGuestLabels === 'function') {
                window.kvRefreshEnquiryGuestLabels();
            }

            if (typeof window.kvSyncEnquiryBbfLock === 'function') {
                window.kvSyncEnquiryBbfLock();
            }

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

    $(document).on('click', '.enq-btn-popup', function (e) {

        e.preventDefault();

        e.stopPropagation();

        const $btn = $(this);

        openEnquiryModal();

        populateEnquiryModal({
            propertyName: $btn.attr('hotel-name') || '',
            resortName: $btn.attr('resort-name') || '',
            roomName: $btn.attr('room-title') || '',
            checkIn: $('#sc-check-in').val() || localStorage.getItem('niseko_checkin') || localStorage.getItem('sb_checkin') || '',
            checkOut: $('#sc-check-out').val() || localStorage.getItem('niseko_checkout') || localStorage.getItem('sb_checkout') || ''
        });

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

            $('#input_1_66, select[name="input_66"], .resort_name select').val(resortName);

            $('#input_1_66, select[name="input_66"], .resort_name select').addClass('disabled');

        }



        if (roomTitle || hotelName || resortName) {





            localStorage.removeItem('enquiry_room_title');

            localStorage.removeItem('enquiry_hotel_name');

            localStorage.removeItem('enquiry_resort_name');



            $('.enquiry_type input').attr('value', 'Product');



            $('.room_name input, .property_name textarea, #input_1_66, select[name="input_66"], .resort_name select, .enquiry_type input').trigger('change');

        }

    }



    $(document).on('click', '.acc-gallery span', function (e) {

        var prev_item = $(this).prev();

        prev_item.trigger('click');

    });



    // Room-listing guests open/close is handled by .sv-guests delegation below (DOMContentLoaded).

    // Initialise each enquiry guest popover from its own fields / HTML defaults.
    // Do NOT seed from search-card localStorage — guest counts stay independent.
    if ($('.eq-guests-popover').length > 0) {
        $('.eq-guests-popover').each(function () {
            const $pop = $(this);
            const $scope = $pop.closest('.gform_wrapper, .mob_quote_form1, .Enquiry-modal-content, .acc_enquiry_form');
            const $ctx = $scope.length ? $scope : $pop.parent();

            let adults = parseInt($ctx.find('.rec_adults select').first().val(), 10);
            let children = parseInt($ctx.find('.rec_children select').first().val(), 10);
            const infants = 0;

            if (isNaN(adults) || adults < 1) adults = parseInt($pop.find('.eq-adults').val(), 10) || 2;
            if (isNaN(children) || children < 0) children = parseInt($pop.find('.eq-children').val(), 10) || 0;

            $pop.find('.eq-adults').val(adults);
            $pop.find('.eq-children').val(children);
            $pop.find('.eq-infants').val(0);

            $ctx.find('.rec_adults select').first().val(adults);
            $ctx.find('.rec_children select').first().val(children);
            $ctx.find('.rec_infants select').first().val(0);

            // Hide infants UI if still present in Gravity Forms HTML
            $pop.find('.eq-infants').closest('.g-row').hide();
            $ctx.find('.rec_infants').closest('.gfield, .gfield_html, li, .guest_search').hide();
        });
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



            infants: 0



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

            g.infants = 0;

            localStorage.setItem('sb_infants', '0');



            const totalGuests = g.adults + g.children;

            let label = `${totalGuests} Guest${totalGuests !== 1 ? 's' : ''}`;



            // Shared guests: search cards + enquiry popup + below enquiry form.
            if (typeof window.kvApplySharedGuests === 'function') {
                window.kvApplySharedGuests(g);
            } else if (typeof window.syncGuestUI === 'function') {
                window.syncGuestUI(g);
            } else {
                document.querySelectorAll('.search-card.js-search-card').forEach(function (sc) {
                    const $sc = jQuery(sc);
                    $sc.find('.js-sb-guests-display, .js-guests-display').html(label).removeClass('empty');
                    $sc.find('.js-v-adults').text(g.adults);
                    $sc.find('.js-v-children').text(g.children);
                    $sc.find('.js-v-infants').text(g.infants);
                    $sc.find('.js-m-adults').val(g.adults);
                    $sc.find('.js-m-children').val(g.children);
                    $sc.find('.js-m-infants').val(g.infants);
                    $sc.find('.js-btn-adults-minus').prop('disabled', g.adults <= 1);
                    $sc.find('.js-btn-children-minus').prop('disabled', g.children <= 0);
                    $sc.find('.js-btn-infants-minus').prop('disabled', g.infants <= 0);
                });
            }

            // Keep this card's direct refs in sync too
            if (el.display) {
                jQuery(el.display).html(label).removeClass('empty');
                el.display.classList.toggle('empty', totalGuests <= 0 && g.infants <= 0);
            }
            if (el.adultsVal) el.adultsVal.textContent = g.adults;
            if (el.childrenVal) el.childrenVal.textContent = g.children;
            if (el.infantsVal) el.infantsVal.textContent = g.infants;
            if (el.btnAM) el.btnAM.disabled = g.adults <= 1;
            if (el.btnCM) el.btnCM.disabled = g.children <= 0;
            if (el.btnIM) el.btnIM.disabled = g.infants <= 0;

        }



        // Open/close is handled by onclick="toggleGuests(...)" on the field.
        // Do NOT toggle .open here — that double-toggles and cancels the popover.
        el.field.addEventListener('click', function (e) {
            e.stopPropagation();
            guestPopOpen = el.pop.classList.contains('open');
        });



        document.addEventListener('click', function (e) {



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

                    // Keep per-card state aligned with shared search guest storage
                    // (hero + sticky header are separate DOM cards).
                    const storedAdults = parseInt(localStorage.getItem('sb_adults'), 10);
                    const storedChildren = parseInt(localStorage.getItem('sb_children'), 10);
                    const storedInfants = parseInt(localStorage.getItem('sb_infants'), 10);
                    if (Number.isFinite(storedAdults) && storedAdults > 0) g.adults = storedAdults;
                    if (Number.isFinite(storedChildren) && storedChildren >= 0) g.children = storedChildren;
                    g.infants = 0;



                    const applyLocalAdjust = function (type, delta) {

                        if (type === 'adults') {

                            g.adults = Math.max(1, (g.adults || 0) + delta);

                        } else if (type === 'children') {

                            g.children = Math.max(0, (g.children || 0) + delta);

                        }



                        renderGuests();

                    };



                    const runAdjust = function (type, delta) {

                        applyLocalAdjust(type, delta);

                    };



                    if (this.classList.contains('js-btn-adults-minus')) {

                        runAdjust('adults', -1);

                    } else if (this.classList.contains('js-btn-children-minus')) {

                        runAdjust('children', -1);

                    } else if (row && row.querySelector('.js-v-adults')) {

                        runAdjust('adults', 1);

                    } else if (row && row.querySelector('.js-v-children')) {

                        runAdjust('children', 1);

                        if (g.children > 0) { triggerChildAgePopup(g.children); }

                    }

                });



            });

        }



        // Search-card guest buttons only (room-listing popovers are bound outside this loop)
        bindGuestButtons(card);



        function syncMobile() {



            g.adults = Math.max(1, parseInt(el.mAdults?.value || 2));



            g.children = Math.max(0, parseInt(el.mChildren?.value || 0));



            g.infants = 0;



            renderGuests();



        }



        if (el.mAdults) el.mAdults.addEventListener('change', syncMobile);



        if (el.mChildren) el.mChildren.addEventListener('change', syncMobile);



        renderGuests();



    });



    /* Room listing / change-guests popup counters.
       Must run even when no .search-card exists on the page. */
    (function initRoomFilterGuestCounters() {
        function readGuestState() {
            const adults = parseInt(localStorage.getItem('sb_adults'), 10);
            const children = parseInt(localStorage.getItem('sb_children'), 10);
            return {
                adults: Number.isFinite(adults) && adults > 0 ? adults : 2,
                children: Number.isFinite(children) && children >= 0 ? children : 0,
                infants: 0
            };
        }

        function guestLabel(g) {
            const total = g.adults + g.children;
            return total > 0
                ? (total + ' Guest' + (total !== 1 ? 's' : ''))
                : '';
        }

        function updateRoomFilterUI(g) {
            document.querySelectorAll('.room-filter-guests-popover').forEach(function (pop) {
                const adultsVal = pop.querySelector('.js-v-adults');
                const childrenVal = pop.querySelector('.js-v-children');
                const btnAM = pop.querySelector('.js-btn-adults-minus');
                const btnCM = pop.querySelector('.js-btn-children-minus');
                if (adultsVal) adultsVal.textContent = String(g.adults);
                if (childrenVal) childrenVal.textContent = String(g.children);
                if (btnAM) btnAM.disabled = g.adults <= 1;
                if (btnCM) btnCM.disabled = g.children <= 0;
            });

            const label = guestLabel(g);
            document.querySelectorAll('.sv-guests').forEach(function (input) {
                if (label) {
                    input.value = label;
                    input.classList.remove('empty');
                } else {
                    input.value = '';
                }
            });
        }

        function applyRoomFilterAdjust(type, delta) {
            const g = readGuestState();

            if (type === 'adults') {
                g.adults = Math.max(1, g.adults + delta);
            } else if (type === 'children') {
                g.children = Math.max(0, g.children + delta);
            } else {
                return;
            }

            g.infants = 0;
            localStorage.setItem('sb_adults', String(g.adults));
            localStorage.setItem('sb_children', String(g.children));
            localStorage.setItem('sb_infants', '0');

            updateRoomFilterUI(g);

            if (typeof window.kvApplySharedGuests === 'function') {
                window.kvApplySharedGuests(g);
            }

            if (type === 'children' && delta > 0 && g.children > 0) {
                triggerChildAgePopup(g.children);
            }
        }

        jQuery(document).off('click.kvRoomGuests', '.room-filter-guests-popover .g-btn');
        jQuery(document).on('click.kvRoomGuests', '.room-filter-guests-popover .g-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (this.disabled) return;

            const row = this.closest('.g-row');

            if (this.classList.contains('js-btn-adults-minus')) {
                applyRoomFilterAdjust('adults', -1);
            } else if (this.classList.contains('js-btn-children-minus')) {
                applyRoomFilterAdjust('children', -1);
            } else if (this.classList.contains('js-btn-adults-plus') || (row && row.querySelector('.js-v-adults'))) {
                applyRoomFilterAdjust('adults', 1);
            } else if (this.classList.contains('js-btn-children-plus') || (row && row.querySelector('.js-v-children'))) {
                applyRoomFilterAdjust('children', 1);
            }
        });

        jQuery(document).off('click.kvRoomGuestsOpen', '.sv-guests');
        jQuery(document).on('click.kvRoomGuestsOpen', '.sv-guests', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $form = jQuery(this).closest('form');
            const $popover = $form.find('.room-filter-guests-popover');

            jQuery('.room-filter-guests-popover').not($popover).removeClass('active');
            $popover.toggleClass('active');
        });

        jQuery(document).off('click.kvRoomGuestsStop', '.room-filter-guests-popover');
        jQuery(document).on('click.kvRoomGuestsStop', '.room-filter-guests-popover', function (e) {
            e.stopPropagation();
        });

        jQuery(document).off('click.kvRoomGuestsClose');
        jQuery(document).on('click.kvRoomGuestsClose', function (e) {
            if (!jQuery(e.target).closest('.sv-guests, .room-filter-guests-popover').length) {
                jQuery('.room-filter-guests-popover').removeClass('active');
            }
        });

        updateRoomFilterUI(readGuestState());
    })();



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

