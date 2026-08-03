if (base_url === undefined || pathname === undefined || host === undefined || pathArray === undefined || urlParams === undefined) {

    var base_url = window.location.origin; //https://gvelondon.com

    var pathname = window.location.pathname; //cars/bugatti-veyron-sang-noir/

    var host = window.location.host; //gvelondon.com

    var pathArray = window.location.pathname.split('/').filter(Boolean); //returns object ['', 'cars', ''] 

    var urlParams = new URLSearchParams(window.location.search);

}

var themeUrl = kv_object.themeUrl;

function triggerChildAgePopup(noOfChilds) {
    // Legacy name kept for callers — ages now render inline inside the guests popover.
    if (typeof window.kvSyncAllInlineChildAges === 'function') {
        window.kvSyncAllInlineChildAges(noOfChilds);
    }
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
        console.log( 'resort' );
        console.log( resort );
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



    // Child Age Work — inline under Children in the same guests popover (no second popup).
    var kvChildAgeTargetScope = null;
    var KV_CHILD_AGE_MIN = 1;
    var KV_CHILD_AGE_MAX = 15;

    function kvGetEnquiryGuestScope($el) {
        const $scope = $el.closest('.gform_wrapper, .mob_quote_form1, .Enquiry-modal-content, .acc_enquiry_form, .form_area');
        return $scope.length ? $scope : $();
    }

    function kvGetStoredChildAge(child) {
        const val = localStorage.getItem('sb_' + child);
        const n = parseInt(val, 10);
        return !isNaN(n) && n >= KV_CHILD_AGE_MIN && n <= KV_CHILD_AGE_MAX ? String(n) : '';
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

    function kvEnsureChildAgesMount($pop) {
        if (!$pop || !$pop.length) return $();
        let $mount = $pop.find('.kv-child-ages').first();
        if (!$mount.length) {
            $mount = $('<div class="kv-child-ages" hidden></div>');
            const $childrenRow = $pop.find('.g-row').has('.js-v-children, .eq-children').last();
            if ($childrenRow.length) {
                $childrenRow.after($mount);
            } else {
                $pop.append($mount);
            }
        }

        // Change Guests / room-filter popup already has its own Done (upd-guest-btn).
        // Never inject the inner .kv-guests-done there.
        const isRoomFilterPop = $pop.is('.room-filter-guests-popover') ||
            $pop.closest('.room-search-popup-modal, #room-filter-form, #room-filter-form-popup').length > 0;

        if (isRoomFilterPop) {
            $pop.find('.kv-guests-done').remove();
            if (!$pop.find('.kv-child-ages-error').length) {
                $mount.after('<div class="kv-child-ages-error">Please set an age for each child.</div>');
            }
            return $mount;
        }

        if (!$pop.find('.kv-guests-done').length) {
            $mount.after(
                '<button type="button" class="kv-guests-done" onclick="window.kvCloseGuestsPopover(event,this)">Done</button>'
            );
        } else {
            $pop.find('.kv-guests-done').attr('onclick', 'window.kvCloseGuestsPopover(event,this)');
        }
        if (!$pop.find('.kv-child-ages-error').length) {
            $pop.find('.kv-guests-done').before('<div class="kv-child-ages-error">Please set an age for each child.</div>');
        }
        return $mount;
    }

    function kvRenderInlineChildAges($pop, childCount) {
        if (!$pop || !$pop.length) return;
        const count = Math.max(0, Math.min(15, parseInt(childCount, 10) || 0));
        const $mount = kvEnsureChildAgesMount($pop);
        if (!$mount.length) return;

        $pop.find('.kv-child-ages-error').removeClass('is-visible');

        if (count <= 0) {
            $mount.empty().attr('hidden', true).removeClass('is-open is-many');
            return;
        }

        let html = '';
        for (let i = 1; i <= count; i++) {
            const key = 'child_' + i;
            let age = parseInt(kvGetStoredChildAge(key), 10);
            if (isNaN(age) || age < KV_CHILD_AGE_MIN) age = KV_CHILD_AGE_MIN;
            if (age > KV_CHILD_AGE_MAX) age = KV_CHILD_AGE_MAX;
            localStorage.setItem('sb_' + key, String(age));

            const minusDisabled = age <= KV_CHILD_AGE_MIN ? ' disabled' : '';
            const plusDisabled = age >= KV_CHILD_AGE_MAX ? ' disabled' : '';
            // Inline onclick: guests-popover uses stopPropagation so document delegation never reaches age buttons.
            html +=
                '<div class="kv-child-age-row" data-child="' + key + '">' +
                    '<span class="kv-child-age-label">Child ' + i + ' age</span>' +
                    '<div class="g-counter">' +
                        '<button type="button" class="g-btn js-btn-cage-minus"' + minusDisabled +
                            ' aria-label="Decrease child ' + i + ' age"' +
                            ' onclick="window.kvBumpChildAge(event,this,-1)">−</button>' +
                        '<span class="g-val js-v-cage">' + age + '</span>' +
                        '<button type="button" class="g-btn js-btn-cage-plus"' + plusDisabled +
                            ' aria-label="Increase child ' + i + ' age"' +
                            ' onclick="window.kvBumpChildAge(event,this,1)">+</button>' +
                    '</div>' +
                '</div>';
        }

        $mount.html(html).removeAttr('hidden').addClass('is-open');
        $mount.toggleClass('is-many', count >= 4);

        // Keep expanded ages visible (header search was clipping the bottom).
        if ($pop.hasClass('open') || $pop.hasClass('active')) {
            setTimeout(function () {
                const el = $mount.get(0);
                if (el && typeof el.scrollIntoView === 'function') {
                    el.scrollIntoView({ block: 'nearest' });
                }
            }, 20);
        }

        const $scope = kvGetEnquiryGuestScope($pop);
        if ($scope.length) {
            kvWriteStoredChildAgesToScope($scope, count);
        } else if (typeof window.kvWriteStoredChildAgesToScope === 'function') {
            $('.Enquiry-modal-content, .acc_enquiry_form, .form_area, .gform_wrapper.quote_form_wrapper').each(function () {
                kvWriteStoredChildAgesToScope($(this), count);
            });
        }
    }

    window.kvRenderInlineChildAges = kvRenderInlineChildAges;

    window.kvSyncAllInlineChildAges = function (childCount) {
        const count = childCount != null
            ? childCount
            : (parseInt(localStorage.getItem('sb_children'), 10) || 0);

        $('.guests-popover, .eq-guests-popover, .room-filter-guests-popover, #eq-guests-popover').each(function () {
            kvRenderInlineChildAges($(this), count);
        });
    };

    window.kvBumpChildAge = function (e, btn, delta) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        }
        if (!btn || btn.disabled) return;

        const $btn = $(btn);
        const $row = $btn.closest('.kv-child-age-row');
        const $pop = $btn.closest('.guests-popover, .eq-guests-popover, .room-filter-guests-popover, #eq-guests-popover');
        if (!$row.length) return;

        const child = $row.attr('data-child');
        let age = parseInt($row.find('.js-v-cage').text(), 10);
        if (isNaN(age)) age = KV_CHILD_AGE_MIN;

        age += (parseInt(delta, 10) || 0);
        if (age < KV_CHILD_AGE_MIN) age = KV_CHILD_AGE_MIN;
        if (age > KV_CHILD_AGE_MAX) age = KV_CHILD_AGE_MAX;

        localStorage.setItem('sb_' + child, String(age));
        $row.find('.js-v-cage').text(String(age));
        $row.find('.js-btn-cage-minus').prop('disabled', age <= KV_CHILD_AGE_MIN);
        $row.find('.js-btn-cage-plus').prop('disabled', age >= KV_CHILD_AGE_MAX);
        if ($pop.length) $pop.find('.kv-child-ages-error').removeClass('is-visible');

        const $scope = kvGetEnquiryGuestScope($pop);
        const children = parseInt(localStorage.getItem('sb_children'), 10) || 0;
        if ($scope.length) {
            kvWriteStoredChildAgesToScope($scope, children);
        } else {
            $('.Enquiry-modal-content, .acc_enquiry_form, .form_area, .gform_wrapper.quote_form_wrapper').each(function () {
                kvWriteStoredChildAgesToScope($(this), children);
            });
        }
    };

    function kvValidateInlineChildAges($pop) {
        const children = parseInt(localStorage.getItem('sb_children'), 10) || 0;
        if (children <= 0) return true;
        let ok = true;
        $pop.find('.kv-child-age-row').each(function () {
            const age = parseInt($(this).find('.js-v-cage').text(), 10);
            if (isNaN(age) || age < KV_CHILD_AGE_MIN) ok = false;
        });
        return ok && $pop.find('.kv-child-age-row').length >= children;
    }

    // Inline onclick needed: guests-popover / room-filter popover stopPropagation
    // so document-delegated clicks never reach .kv-guests-done.
    window.kvCloseGuestsPopover = function (e, btn) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        }

        const $btn = $(btn || (e && e.currentTarget) || null);
        const $pop = $btn.closest(
            '.guests-popover, .eq-guests-popover, .room-filter-guests-popover, #eq-guests-popover'
        );
        if (!$pop.length) return;

        if (!kvValidateInlineChildAges($pop)) {
            $pop.find('.kv-child-ages-error').addClass('is-visible');
            return;
        }

        $pop.find('.kv-child-ages-error').removeClass('is-visible');
        $pop.removeClass('open show active');
        $pop.closest('.search-card').find('.sb-guests-desktop').removeClass('active');
        $('header.newHeader').removeClass('kv-guests-open');
        if (typeof window.kvClearGuestsPopoverPin === 'function') {
            window.kvClearGuestsPopoverPin($pop);
        }
    };

    $(document).on('click', '.kv-guests-done', function (e) {
        window.kvCloseGuestsPopover(e, this);
    });

    // Close guests popover → drop header boost.
    $(document).on('click', function (e) {
        if ($(e.target).closest('.guests-popover, .sb-guests, .sb-guests-desktop').length) return;
        $('header.newHeader').removeClass('kv-guests-open');
    });

    $(document).on('change', '.rec_children select, #input_1_10, #input_4_10', function () {
        const noOfChilds = $(this).val();
        kvChildAgeTargetScope = kvGetEnquiryGuestScope($(this));

        if (noOfChilds === '' || noOfChilds == null) return;

        if (String(noOfChilds) === '0') {
            kvWriteStoredChildAgesToScope(kvChildAgeTargetScope, 0);
            window.kvSyncAllInlineChildAges(0);
            return;
        }

        // Inline ages in guests popover — do not open section.child_age.
        window.kvSyncAllInlineChildAges(noOfChilds);
        if (kvChildAgeTargetScope && kvChildAgeTargetScope.length) {
            kvWriteStoredChildAgesToScope(kvChildAgeTargetScope, noOfChilds);
        }
    });

    // Legacy age popup confirm (kept if markup still present somewhere).
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

            localStorage.setItem('sb_' + child, val);
            $scope.find('.' + child + ' input').val(val);
        });

        $('section.child_age').removeClass('active');
        window.kvSyncAllInlineChildAges();
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
    // Listing + single pages can render 2x GF #gform_1 (page + modal).
    // Park the inactive copy on submit so validation stays on the active form.

    var parkedEnquiryMount = null;
    var enquiryModalFormHtml = null;
    var pageEnquiryFormHtml = null;
    var enquiryModalCloseTimer = null;
    var enquiryPageSuccessTimer = null;
    var enquiryModalAwaitingSubmit = false;
    var enquiryModalSuccessShown = false;
    var enquiryPageAwaitingSubmit = false;
    var enquiryPageSuccessShown = false;

    function getModalEnquiryScope() {
        return $('.Enquiry-modal-content').first();
    }

    function stripEnquiryValidationHtml(html) {
        if (!html) return html;
        try {
            const $tmp = $('<div>').html(html);
            $tmp.find('.gform_wrapper').removeClass('gform_validation_error');
            $tmp.find(
                '.gform_validation_errors, .gform_validation_error, .validation_error, ' +
                '.validation_message, .gfield_validation_message, .gform_submission_error, ' +
                '.gform_validation_container, [id$="_validation_container"]'
            ).remove();
            $tmp.find('.gfield_error').removeClass('gfield_error');
            $tmp.find('[aria-invalid="true"]').attr('aria-invalid', 'false');
            return $tmp.html();
        } catch (err) {
            return html;
        }
    }

    function cacheEnquiryModalFormHtml() {
        const $slot = $('.Enquiry-modal-form-slot').first();
        if (!$slot.length) return;
        // Only cache a live form (not a confirmation / success screen).
        if (
            $slot.find('form#gform_1, form[id^="gform_"], form.quote_form').length &&
            !$slot.find('.gform_confirmation_message, .kv-enquiry-success').length
        ) {
            enquiryModalFormHtml = stripEnquiryValidationHtml($slot.html());
        }
    }

    function cachePageEnquiryFormHtml() {
        const $mount = getPageEnquiryMount();
        if (!$mount.length) return;
        if (
            $mount.find('form#gform_1, form[id^="gform_"], form[id^="parked_gform_"], form.quote_form').length &&
            !$mount.find('.gform_confirmation_message, .gform_confirmation_wrapper').length
        ) {
            // Cache with real IDs (unpark briefly if needed).
            const wasParked = $mount.hasClass('kv-enquiry-parked');
            if (wasParked) {
                $mount.find('[data-kv-parked-id]').each(function () {
                    this.id = $(this).attr('data-kv-parked-id');
                    $(this).removeAttr('data-kv-parked-id');
                });
                $mount.removeClass('kv-enquiry-parked');
            }
            pageEnquiryFormHtml = stripEnquiryValidationHtml($mount.html());
            if (wasParked || $('body').hasClass('enquire-open')) {
                parkEnquiryFormExcept('modal');
            }
        }
    }

    function resetPageEnquiryForm() {
        const $mount = getPageEnquiryMount();
        if (!$mount.length) return;

        if (pageEnquiryFormHtml) {
            $mount.html(pageEnquiryFormHtml);
        } else {
            // Fallback: remove leaked confirmation markup from the page form.
            $mount.find('.gform_confirmation_wrapper, .gform_confirmation_message, .gform_confirmation_message_1').remove();
        }

        // While popup is open, keep page copy parked so GF keeps targeting the modal.
        if ($('body').hasClass('enquire-open') || $('.Enquiry-modal.active').length) {
            parkEnquiryFormExcept('modal');
        }

        try {
            $(document).trigger('gform_post_render', [1, 0]);
        } catch (err) { /* no-op */ }
    }

    function resetEnquiryModalForm() {
        const $slot = $('.Enquiry-modal-form-slot').first();
        if (!$slot.length || !enquiryModalFormHtml) return;

        $slot.html(enquiryModalFormHtml);
        $('.Enquiry-modal').removeClass('is-success');
        $('.Enquiry-modal-title').text('Enquire Now');
        enquiryModalAwaitingSubmit = false;
        enquiryModalSuccessShown = false;

        try {
            $(document).trigger('gform_post_render', [1, 0]);
        } catch (err) { /* no-op */ }
    }

    function markEnquiryModalSubmit($form) {
        if (!$form || !$form.length) return;

        if ($form.closest('.Enquiry-modal').length) {
            enquiryModalAwaitingSubmit = true;
            enquiryModalSuccessShown = false;
            enquiryPageAwaitingSubmit = false;
            cacheEnquiryModalFormHtml();
            bindEnquiryGformAjaxFrame();
            return;
        }

        // Page / enquire / "Skip the searching" / blog sidebar form submit.
        if (
            $form.closest(
                '.acc_enquiry_form, .mob_quote_form1, .mob_quote_form, .form_area, .load-more-enquiry-form, section.enquiry_form, .kv-blog-enquiry-form'
            ).length
        ) {
            enquiryPageAwaitingSubmit = true;
            enquiryPageSuccessShown = false;
            enquiryModalAwaitingSubmit = false;
            cachePageEnquiryFormHtml();
            bindEnquiryGformAjaxFrame();
        }
    }

    function getPageEnquiryFormWrap() {
        // /enquire/ hero form
        const $formArea = $('.form_area .mob_quote_form').first();
        if ($formArea.length) return $formArea;

        const $acc = $('.acc_enquiry_form').first();
        if ($acc.length) return $acc;

        // Blog single sidebar quote form
        const $blog = $('.kv-blog-enquiry-form').first();
        if ($blog.length) return $blog;

        const $section = $('section.enquiry_form, .full-section.enquiry_form').first();
        if ($section.length) return $section;

        const $mq = $('.mob_quote_form, .mob_quote_form1').filter(function () {
            return $(this).closest('.Enquiry-modal').length === 0;
        }).first();
        if ($mq.length) return $mq;

        return getPageEnquiryMount();
    }

    function handlePageEnquirySuccess(customMessage, opts) {
        opts = opts || {};
        if (enquiryPageSuccessShown) return;
        // Modal owns success UX while popup is open.
        if ($('body').hasClass('enquire-open') && $('.Enquiry-modal.active').length) return;

        // Never show thank-you over a validation state (empty submit).
        if (enquiryPageHasValidation()) {
            enquiryPageAwaitingSubmit = false;
            return;
        }

        // Require a real success signal — never invent thank-you from "awaiting" alone.
        if (!opts.force && !pageHasEnquiryConfirmation()) {
            return;
        }

        const text = customMessage || 'Thanks for your enquiry. Our team will get back to you very soon.';
        const $wrap = getPageEnquiryFormWrap();
        if (!$wrap.length) return;

        enquiryPageSuccessShown = true;
        enquiryPageAwaitingSubmit = false;

        // Restore live form fields (GF replaces wrapper with confirmation).
        resetPageEnquiryForm();
        unparkEnquiryForm();
        // Cached HTML can still carry errors from an earlier failed submit.
        clearEnquiryValidationIn($wrap);
        clearEnquiryValidationIn(getPageEnquiryMount());

        // Remove any prior banner (inside wrap or sibling above it).
        $wrap.find('.kv-page-enquiry-success').remove();
        $wrap.prev('.kv-page-enquiry-success').remove();

        const bannerHtml =
            '<div class="kv-page-enquiry-success" role="status" aria-live="polite">' +
                '<span class="kv-page-enquiry-success__icon" aria-hidden="true">✓</span>' +
                '<p class="kv-page-enquiry-success__text">' + text + '</p>' +
            '</div>';

        // Place outside the blue form box (same as /enquire/):
        // - .acc_enquiry_form: title lives outside GF → insert before whole block
        // - .form_area / mob_quote / blog: title is inside GF → insert before .gform_wrapper
        let $bannerTarget;
        if ($wrap.hasClass('acc_enquiry_form') || $wrap.closest('.acc_enquiry_form').length) {
            const $acc = $wrap.hasClass('acc_enquiry_form') ? $wrap : $wrap.closest('.acc_enquiry_form');
            $acc.before(bannerHtml);
            $bannerTarget = $acc.prev('.kv-page-enquiry-success');
        } else {
            const $gform = $wrap.find('.gform_wrapper, form#gform_1, form.quote_form').first();
            if ($gform.length) {
                $gform.before(bannerHtml);
                $bannerTarget = $gform.prev('.kv-page-enquiry-success');
            } else {
                $wrap.prepend(bannerHtml);
                $bannerTarget = $wrap.find('.kv-page-enquiry-success').first();
            }
        }

        if (enquiryPageSuccessTimer) {
            clearTimeout(enquiryPageSuccessTimer);
        }
        enquiryPageSuccessTimer = setTimeout(function () {
            enquiryPageSuccessTimer = null;
            const $banner = $bannerTarget && $bannerTarget.length
                ? $bannerTarget
                : $('.kv-page-enquiry-success').not('.kv-enquiry-modal-success');
            $banner.fadeOut(250, function () {
                $(this).remove();
                enquiryPageSuccessShown = false;
            });
        }, 3500);
    }

    function enquiryModalHasValidation($root) {
        const $scope = $root && $root.length ? $root : $('.Enquiry-modal');
        return $scope.find(
            '.gform_validation_error, .gform_validation_errors, .gfield_error, .validation_message, .gfield_validation_message'
        ).length > 0;
    }

    function enquiryPageHasValidation() {
        const $scope = getPageEnquiryFormWrap();
        if ($scope && $scope.length) {
            if (
                $scope.find(
                    '.gform_validation_error, .gform_validation_errors, .gfield_error, .validation_message, .gfield_validation_message'
                ).length
            ) {
                return true;
            }
        }
        return $(
            '.form_area .gform_validation_error, .form_area .gform_validation_errors, .form_area .gfield_error, ' +
            '.acc_enquiry_form .gform_validation_error, .acc_enquiry_form .gform_validation_errors, .acc_enquiry_form .gfield_error, ' +
            '.mob_quote_form .gform_validation_error, .mob_quote_form .gform_validation_errors, .mob_quote_form .gfield_error, ' +
            '.kv-blog-enquiry-form .gform_validation_error, .kv-blog-enquiry-form .gform_validation_errors, .kv-blog-enquiry-form .gfield_error'
        ).filter(function () {
            return $(this).closest('.Enquiry-modal').length === 0;
        }).length > 0;
    }

    function pageHasEnquiryConfirmation() {
        return $(
            '.form_area .gform_confirmation_wrapper, .form_area .gform_confirmation_message, ' +
            '.acc_enquiry_form .gform_confirmation_wrapper, .acc_enquiry_form .gform_confirmation_message, ' +
            '.mob_quote_form .gform_confirmation_wrapper, .mob_quote_form .gform_confirmation_message, ' +
            '.mob_quote_form1 .gform_confirmation_wrapper, .mob_quote_form1 .gform_confirmation_message, ' +
            '.kv-blog-enquiry-form .gform_confirmation_wrapper, .kv-blog-enquiry-form .gform_confirmation_message, ' +
            '#gform_confirmation_wrapper_1, .gform_confirmation_message_1'
        ).filter(function () {
            return $(this).closest('.Enquiry-modal').length === 0;
        }).length > 0;
    }

    function bindEnquiryGformAjaxFrame() {
        const frame = document.getElementById('gform_ajax_frame_1');
        if (!frame || frame.getAttribute('data-kv-enq-bound') === '1') return;
        frame.setAttribute('data-kv-enq-bound', '1');
        frame.addEventListener('load', function () {
            if (enquiryModalSuccessShown || enquiryPageSuccessShown) return;
            if (!enquiryModalAwaitingSubmit && !enquiryPageAwaitingSubmit) return;
            let html = '';
            try {
                const doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
                html = doc && doc.body ? String(doc.body.innerHTML || '') : '';
            } catch (err) {
                html = '';
            }
            if (!html) return;

            const looksValidation =
                html.indexOf('gform_validation_error') !== -1 ||
                html.indexOf('gform_validation_errors') !== -1 ||
                html.indexOf('gfield_error') !== -1 ||
                html.indexOf('validation_message') !== -1;

            if (looksValidation) {
                enquiryModalAwaitingSubmit = false;
                enquiryPageAwaitingSubmit = false;
                return;
            }

            // Match GF's own iframe checks (message confirmation OR page redirect script).
            const looksSuccess =
                html.indexOf('gform_confirmation_wrapper') !== -1 ||
                html.indexOf('gform_confirmation_message') !== -1 ||
                html.indexOf('gformRedirect(){') !== -1 ||
                html.indexOf('gformRedirect() {') !== -1;

            if (!looksSuccess) return;

            if (
                enquiryModalAwaitingSubmit ||
                ($('body').hasClass('enquire-open') && $('.Enquiry-modal.active').length)
            ) {
                handleEnquiryModalSuccess();
            } else if (enquiryPageAwaitingSubmit) {
                handlePageEnquirySuccess(null, { force: true });
            }
        });
    }

    // GF redefines window.gformRedirect on every page-confirmation response.
    // Intercept those redefinitions so the popup never navigates away.
    (function patchGformRedirectForModal() {
        var currentRedirect = null;

        function wrappedRedirect() {
            if (
                enquiryModalAwaitingSubmit ||
                ($('body').hasClass('enquire-open') && $('.Enquiry-modal.active').length)
            ) {
                handleEnquiryModalSuccess();
                return;
            }
            if (enquiryPageAwaitingSubmit) {
                handlePageEnquirySuccess(null, { force: true });
                return;
            }
            if (typeof currentRedirect === 'function') {
                return currentRedirect.apply(this, arguments);
            }
        }

        try {
            Object.defineProperty(window, 'gformRedirect', {
                configurable: true,
                enumerable: true,
                get: function () {
                    return wrappedRedirect;
                },
                set: function (fn) {
                    currentRedirect = fn;
                }
            });
        } catch (err) {
            // Fallback if defineProperty is blocked.
            window.gformRedirect = wrappedRedirect;
        }
    })();

    function handleEnquiryModalSuccess(customMessage) {
        const $modal = $('.Enquiry-modal');
        if (!$modal.length) return;
        if (enquiryModalSuccessShown) return;
        // Never replace a live validation state with thank-you.
        if (enquiryModalHasValidation($modal)) {
            enquiryModalAwaitingSubmit = false;
            return;
        }

        // Keep going even if active class was briefly lost during GF replace.
        enquiryModalSuccessShown = true;
        enquiryModalAwaitingSubmit = false;

        const text = customMessage || 'Thanks for your enquiry. Our team will get back to you very soon.';
        const $content = $modal.find('.Enquiry-modal-content').first();
        const $title = $modal.find('.Enquiry-modal-title').first();

        // Restore live form (GF may have replaced it with confirmation markup).
        resetEnquiryModalForm();
        enquiryModalSuccessShown = true;
        enquiryModalAwaitingSubmit = false;
        clearEnquiryValidationIn(getModalEnquiryScope());

        // Same style as page form: banner under title, form stays visible below.
        $content.find('.kv-page-enquiry-success, .kv-enquiry-success').remove();
        const bannerHtml =
            '<div class="kv-page-enquiry-success kv-enquiry-modal-success" role="status" aria-live="polite">' +
                '<span class="kv-page-enquiry-success__icon" aria-hidden="true">✓</span>' +
                '<p class="kv-page-enquiry-success__text">' + text + '</p>' +
            '</div>';
        if ($title.length) {
            $title.after(bannerHtml);
        } else {
            $content.prepend(bannerHtml);
        }

        $modal
            .addClass('is-success active')
            .css({ display: 'flex', opacity: '1', visibility: 'visible' });
        $content.css({
            display: 'block',
            opacity: '1',
            visibility: 'visible',
            'z-index': '3'
        });
        $title.text('Enquire Now');
        $('body').addClass('enquire-open');

        // GF often writes confirmation onto the page "Skip the searching" form
        // (duplicate #gform_wrapper_1). Keep thank-you only in the popup.
        resetPageEnquiryForm();
        parkEnquiryFormExcept('modal');

        if (enquiryModalCloseTimer) {
            clearTimeout(enquiryModalCloseTimer);
        }
        enquiryModalCloseTimer = setTimeout(function () {
            enquiryModalCloseTimer = null;
            closeEnquiryModal();
            resetEnquiryModalForm();
        }, 3500);
    }

    function maybeHandleEnquiryModalSuccessFromDom() {
        if (enquiryModalSuccessShown) return false;
        if (!enquiryModalAwaitingSubmit && !$('body').hasClass('enquire-open') && !$('.Enquiry-modal.active').length) {
            return false;
        }

        const $modal = $('.Enquiry-modal');
        if (enquiryModalHasValidation($modal)) {
            enquiryModalAwaitingSubmit = false;
            return false;
        }

        // Require real GF confirmation markup only — never treat "form missing" as success
        // (that race was replacing validation errors with a fake thank-you).
        const hasConfirmation = $modal.find(
            '.gform_confirmation_message, .gform_confirmation_wrapper, .gform_confirmation_message_1'
        ).length > 0;

        if (hasConfirmation) {
            handleEnquiryModalSuccess();
            return true;
        }
        return false;
    }

    /** Page/listing/enquire/blog form mount (anything except the modal copy). */
    function getPageEnquiryMount() {
        // /enquire/ + get-a-quote hero form (.mob_quote_form, not .mob_quote_form1)
        const $formAreaInner = $('.form_area .mob_quote_form .mob_quote_inner, .form_area .mob_quote_inner')
            .filter(function () {
                return $(this).closest('.Enquiry-modal').length === 0;
            })
            .first();
        if ($formAreaInner.length) return $formAreaInner;

        // Blog single sidebar form
        const $blog = $('.kv-blog-enquiry-form').first();
        if ($blog.length) return $blog;

        const $root = $(
            '.acc_enquiry_form, .load-more-enquiry-form, section.enquiry_form, .full-section.enquiry_form'
        ).first();

        if ($root.length) {
            if ($root.hasClass('mob_quote_form1') || $root.hasClass('mob_quote_form')) return $root;
            const $nested = $root.find('.mob_quote_form1, .mob_quote_form .mob_quote_inner, .mob_quote_inner').first();
            if ($nested.length) return $nested;
        }

        const $mqInner = $('.mob_quote_form .mob_quote_inner, .mob_quote_form1')
            .filter(function () {
                return $(this).closest('.Enquiry-modal').length === 0;
            })
            .first();
        if ($mqInner.length) return $mqInner;

        return $();
    }

    // Back-compat alias used by gform_post_render / guest sync.
    function getListingEnquiryScope() {
        return getPageEnquiryMount();
    }

    function clearEnquiryValidationIn($scope) {
        if (!$scope || !$scope.length) return;
        const $wrapper = $scope.find('.gform_wrapper').addBack('.gform_wrapper').first();
        const $target = $wrapper.length ? $wrapper : $scope;
        $target.removeClass('gform_validation_error');
        $target.find(
            '.gform_validation_errors, .gform_validation_error, .validation_error, ' +
            '.validation_message, .gfield_validation_message, .gform_submission_error, ' +
            '.gform_validation_container, [id$="_validation_container"]'
        ).remove();
        $target.find('.gfield_error').removeClass('gfield_error');
        $target.find('[aria-invalid="true"]').attr('aria-invalid', 'false');
        $scope.find(
            '.gform_validation_errors, .validation_message, .gfield_validation_message, .gform_submission_error'
        ).remove();
        $scope.find('.gfield_error').removeClass('gfield_error');
    }

    function unparkEnquiryForm() {
        $('.kv-enquiry-parked').each(function () {
            const $root = $(this);
            $root.find('[data-kv-parked-id]').each(function () {
                this.id = $(this).attr('data-kv-parked-id');
                $(this).removeAttr('data-kv-parked-id');
            });
            $root.removeClass('kv-enquiry-parked');
        });
        parkedEnquiryMount = null;
    }

    function parkEnquiryFormExcept(active) {
        unparkEnquiryForm();
        const $inactive = active === 'modal'
            ? getPageEnquiryMount()
            : $('.Enquiry-modal .mob_quote_form1').first();

        if (!$inactive.length) return;

        $inactive.addClass('kv-enquiry-parked');
        $inactive.find('[id]').each(function () {
            if ($(this).attr('data-kv-parked-id')) return;
            $(this).attr('data-kv-parked-id', this.id);
            this.id = 'parked_' + this.id;
        });
        parkedEnquiryMount = $inactive;
    }

    function parkActiveEnquiryForm($form) {
        if (!$form || !$form.length) return;
        if ($form.closest('.Enquiry-modal').length) {
            parkEnquiryFormExcept('modal');
            return;
        }
        if ($form.closest('.Enquiry-modal').length === 0 && $form.closest('.mob_quote_form1, .mob_quote_form, .form_area, .acc_enquiry_form, .load-more-enquiry-form, section.enquiry_form, .kv-blog-enquiry-form').length) {
            parkEnquiryFormExcept('listing');
        }
    }

    function enquiryFind(selector) {
        if ($('body').hasClass('enquire-open')) {
            const $inModal = getModalEnquiryScope().find(selector);
            if ($inModal.length) return $inModal;
        }
        const $inPage = getPageEnquiryMount().find(selector);
        if ($inPage.length) return $inPage;
        return $(selector);
    }

    function enquiryDatesFromPage() {
        return {
            checkIn: $('#sc-check-in').val() || localStorage.getItem('niseko_checkin') || localStorage.getItem('sb_checkin') || '',
            checkOut: $('#sc-check-out').val() || localStorage.getItem('niseko_checkout') || localStorage.getItem('sb_checkout') || ''
        };
    }

    function closeEnquiryModal() {
        if (enquiryModalCloseTimer) {
            clearTimeout(enquiryModalCloseTimer);
            enquiryModalCloseTimer = null;
        }
        enquiryModalAwaitingSubmit = false;
        clearEnquiryValidationIn(getModalEnquiryScope());
        // Wipe any confirmation that leaked onto the page form, then unpark.
        resetPageEnquiryForm();
        unparkEnquiryForm();
        $('.Enquiry-modal .kv-page-enquiry-success, .Enquiry-modal .kv-enquiry-modal-success, .Enquiry-modal .kv-enquiry-success').remove();
        $('.Enquiry-modal').removeClass('active is-success').css({ display: '', opacity: '', visibility: '' });
        $('.Enquiry-modal-content').css({ display: '', opacity: '', visibility: '', 'z-index': '' });
        $('.Enquiry-modal-title').text('Enquire Now');
        $('body').removeClass('enquire-open');
        // Reset success flag after close so next open works; form reset restores HTML.
        resetEnquiryModalForm();
        enquiryModalSuccessShown = false;
    }

    function openEnquiryModal() {
        try {
            clearEnquiryValidationIn(getModalEnquiryScope());
            clearEnquiryValidationIn(getPageEnquiryMount());
            cachePageEnquiryFormHtml();
        } catch (err) { /* no-op */ }

        const $modal = $('.Enquiry-modal');
        if (!$modal.length) return false;

        $modal.find('.kv-page-enquiry-success, .kv-enquiry-modal-success, .kv-enquiry-success').remove();

        // Previous submit left confirmation in the slot → restore blank form first.
        if ($modal.find('.gform_confirmation_message, .gform_confirmation_wrapper, .kv-enquiry-success').length) {
            resetEnquiryModalForm();
        } else {
            cacheEnquiryModalFormHtml();
        }

        $modal.removeClass('is-success').addClass('active').css('display', 'flex');
        $modal.find('.Enquiry-modal-title').text('Enquire Now');
        $('body').addClass('enquire-open');
        // Park page form for the whole time popup is open so GF updates the modal only.
        parkEnquiryFormExcept('modal');
        return true;
    }

    /** Shared entry: open modal + populate from a trigger button/attrs. */
    function openEnquiryFromTrigger($btn, data) {
        data = data || {};
        if (!openEnquiryModal()) {
            return false;
        }

        const dates = enquiryDatesFromPage();
        const propertyName = (
            data.propertyName ||
            ($btn && ($btn.attr('hotel-name') || $btn.attr('data-hotel-name'))) ||
            resolvePagePropertyForEnquiry($btn) ||
            ''
        ).toString().trim();
        const resortName = (
            data.resortName ||
            ($btn && $btn.attr('resort-name')) ||
            resolvePageResortForEnquiry($btn) ||
            ''
        ).toString().trim();
        const roomName = (
            data.roomName ||
            ($btn && $btn.attr('room-title')) ||
            ''
        ).toString().trim();

        // On property single pages, lock resort + property when we know them.
        let lockProduct = !!data.lockProductFields;
        if (
            typeof data.lockProductFields === 'undefined' &&
            $('body').hasClass('single-accommodation') &&
            propertyName
        ) {
            lockProduct = true;
        }

        try {
            populateEnquiryModal($.extend({}, data, {
                propertyName: propertyName,
                resortName: resortName,
                roomName: roomName,
                checkIn: data.checkIn || dates.checkIn,
                checkOut: data.checkOut || dates.checkOut,
                lockProductFields: lockProduct
            }));
        } catch (err) {
            console.warn('populateEnquiryModal failed', err);
        }
        return true;
    }

    $(document).on(
        'submit',
        'form#gform_1, form.quote_form',
        function () {
            const $form = $(this);
            markEnquiryModalSubmit($form);
            parkActiveEnquiryForm($form);
        }
    );

    $(document).on(
        'click',
        'form#gform_1 input[type="submit"], form#gform_1 #gform_submit_button_1, form#gform_1 .gform_button, form.quote_form .gform_button',
        function () {
            const $form = $(this).closest('form');
            markEnquiryModalSubmit($form);
            parkActiveEnquiryForm($form);
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

    // Resolve a resort name from any pathname (current page or document.referrer).
    // Supports /hakuba/accommodation/, /hakuba-accommodation/, and /hakuba/...
    // Plain /accommodation/ or /enquire/ → empty (no default).
    function getResortNameFromPath(pathname, options) {
        options = options || [];
        const path = String(pathname || '').toLowerCase();
        const pathParts = path.split('/').filter(Boolean);

        if (!pathParts.length) {
            return '';
        }

        // Never treat the enquire page itself as a resort source.
        if (pathParts[0] === 'enquire' || pathParts[0] === 'get-a-quote') {
            return '';
        }

        const accommodationIndex = pathParts.findIndex(function (part) {
            return part === 'accommodation' || part.endsWith('-accommodation');
        });

        if (accommodationIndex !== -1) {
            // Exact "/accommodation/" root listing — no resort in URL
            if (pathParts[accommodationIndex] === 'accommodation') {
                const previous = accommodationIndex > 0 ? pathParts[accommodationIndex - 1] : '';
                if (!previous) {
                    return '';
                }
                const match = matchResortOption(options, previous);
                return match ? (match.rawValue || match.value || match.text) : '';
            }

            // /hakuba-accommodation/...
            if (pathParts[accommodationIndex].endsWith('-accommodation')) {
                const slugResort = pathParts[accommodationIndex].replace(/-accommodation$/, '');
                const match = matchResortOption(options, slugResort);
                return match ? (match.rawValue || match.value || match.text) : '';
            }
        }

        // /niseko/ or /niseko/things-to-do/ — first segment matched against resort options
        const first = pathParts[0];
        const matchFirst = matchResortOption(options, first);
        return matchFirst ? (matchFirst.rawValue || matchFirst.value || matchFirst.text) : '';
    }

    // Only treat URLs like /hakuba/accommodation/ as a locked resort page.
    // Plain /accommodation/ must NOT lock the Resort field.
    function getUrlResortName($resortField) {
        const options = getResortOptions($resortField);
        return getResortNameFromPath(window.location.pathname, options);
    }

    // When landing on /enquire/, map resort from the previous page URL if it had one.
    function getReferrerResortName($resortField) {
        try {
            const ref = document.referrer;
            if (!ref) return '';
            const url = new URL(ref);
            if (url.origin !== window.location.origin) return '';
            return getResortNameFromPath(url.pathname, getResortOptions($resortField));
        } catch (e) {
            return '';
        }
    }

    // One-shot handoff from sticky CTA / .enq-btn (not search-card sb_resort).
    // Kept in memory for this page load so multiple form renders can reuse it.
    var _enquiryResortHandoff = null;

    function consumeStashedEnquiryResort() {
        if (_enquiryResortHandoff !== null) {
            return _enquiryResortHandoff;
        }
        let stored = '';
        try {
            stored = sessionStorage.getItem('enquiry_resort_name') || '';
            sessionStorage.removeItem('enquiry_resort_name');
        } catch (e) { /* ignore */ }
        if (!stored) {
            stored = localStorage.getItem('enquiry_resort_name') || '';
        }
        localStorage.removeItem('enquiry_resort_name');
        _enquiryResortHandoff = stored;
        return _enquiryResortHandoff;
    }

    function stashEnquiryResortName(resortName) {
        const value = (resortName || '').toString().trim();
        _enquiryResortHandoff = null;
        if (value) {
            try { sessionStorage.setItem('enquiry_resort_name', value); } catch (e) { /* ignore */ }
            localStorage.setItem('enquiry_resort_name', value);
        } else {
            try { sessionStorage.removeItem('enquiry_resort_name'); } catch (e) { /* ignore */ }
            localStorage.removeItem('enquiry_resort_name');
        }
    }

    function getSavedSearchResort() {
        const live = ($('.js-sb-resort').first().val() || '').toString().trim();
        if (live && live.toLowerCase() !== 'all') {
            return live;
        }
        let saved = localStorage.getItem('sb_resort') || '';
        if (saved.toLowerCase() === 'all') saved = '';
        return saved;
    }

    function isBareEnquirePage() {
        const parts = window.location.pathname.toLowerCase().split('/').filter(Boolean);
        return parts[0] === 'enquire' || parts[0] === 'get-a-quote';
    }

    function hasSameOriginReferrer() {
        try {
            if (!document.referrer) return false;
            return new URL(document.referrer).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function resolvePageResortForEnquiry($btn) {
        const fromAttr = (($btn && $btn.attr('resort-name')) || '').toString().trim();
        if (fromAttr) return fromAttr;

        const $field = $('#input_1_66, select[name="input_66"], .resort_name select, .js-sb-resort').first();
        const options = $field.length ? getResortOptions($field) : [];
        const fromPath = getResortNameFromPath(window.location.pathname, options);
        if (fromPath) return fromPath;

        const searchResort = getSavedSearchResort();
        if (searchResort) return searchResort;

        if (!options.length) {
            return getResortNameFromPath(window.location.pathname, [
                { value: 'Niseko', text: 'Niseko', rawValue: 'Niseko' },
                { value: 'Hakuba', text: 'Hakuba', rawValue: 'Hakuba' },
                { value: 'Furano', text: 'Furano', rawValue: 'Furano' },
                { value: 'Rusutsu', text: 'Rusutsu', rawValue: 'Rusutsu' },
                { value: 'Kiroro', text: 'Kiroro', rawValue: 'Kiroro' },
                { value: 'Tokyo', text: 'Tokyo', rawValue: 'Tokyo' }
            ]) || '';
        }
        return '';
    }

    /** Property title for accommodation single pages (Yuzuki, etc.). */
    function resolvePagePropertyForEnquiry($btn) {
        const fromAttr = (($btn && ($btn.attr('hotel-name') || $btn.attr('data-hotel-name'))) || '')
            .toString()
            .trim();
        if (fromAttr) return fromAttr;

        if (!$('body').hasClass('single-accommodation')) {
            return '';
        }

        const stickyHotel = (
            $('.sticky-cta-container a.sticky-cta-btn').attr('hotel-name') ||
            $('.sticky-cta-container a.sticky-cta-btn').attr('data-hotel-name') ||
            ''
        ).toString().trim();
        if (stickyHotel) return stickyHotel;

        const fromHeading = (
            $('h1.main-title, .form_area h1, .accSingleBannerUpdate h1, .breadcrumb-wrapper h1')
                .first()
                .text() || ''
        ).replace(/\s+/g, ' ').trim();
        if (fromHeading) return fromHeading;

        const og = ($('meta[property="og:title"]').attr('content') || '').toString().trim();
        if (og) {
            return og.split('|')[0].split(' - ')[0].trim();
        }

        return '';
    }

    // Property slug from paths like /niseko/accommodation/yuzuki/
    function getPropertySlugFromPath(pathname) {
        const parts = String(pathname || '').split('/').filter(Boolean);
        const accIdx = parts.findIndex(function (part) {
            return String(part).toLowerCase() === 'accommodation';
        });
        if (accIdx < 0) return '';
        const slug = parts[accIdx + 1] || '';
        if (!slug || /^(page|feed|amp)$/i.test(slug)) return '';
        return slug;
    }

    // /niseko/accommodation/ (and /page/N) — not a single property URL.
    function isAccommodationListingPath(pathname) {
        pathname = pathname || window.location.pathname || '';
        const parts = String(pathname).split('/').filter(Boolean);
        const hasAcc = parts.some(function (part) {
            return String(part).toLowerCase() === 'accommodation';
        });
        if (!hasAcc) return false;
        return !getPropertySlugFromPath(pathname);
    }

    // Survives GF ajax re-render after localStorage handoff is consumed.
    let pageEnquiryPropertyHandoff = '';

    // Page "Skip the searching" form only.
    // Listing → never map. Single property → current page name. /enquire/ → sticky handoff only.
    function resolveIncomingPageEnquiryProperty() {
        if (isAccommodationListingPath()) {
            pageEnquiryPropertyHandoff = '';
            return '';
        }

        if ($('body').hasClass('single-accommodation')) {
            const fromPage = resolvePagePropertyForEnquiry($());
            if (fromPage) {
                pageEnquiryPropertyHandoff = fromPage;
                return fromPage;
            }
        }

        // Other pages (e.g. /enquire/): sticky/search CTA handoff — never referrer.
        let hotel = (localStorage.getItem('enquiry_hotel_name') || '').trim();
        if (hotel) {
            pageEnquiryPropertyHandoff = hotel;
            return hotel;
        }
        return pageEnquiryPropertyHandoff || '';
    }

    function getPageEnquiryFormRoots() {
        return $('.acc_enquiry_form, .form_area, section.enquiry_form, .full-section.enquiry_form, .load-more-enquiry-form, .kv-blog-enquiry-form')
            .filter(function () {
                return $(this).closest('.Enquiry-modal').length === 0;
            });
    }

    function applyIncomingPropertyToPageEnquiryForms() {
        // Hard guard: listing page form must stay empty for property.
        if (isAccommodationListingPath()) {
            return false;
        }

        const hotelName = resolveIncomingPageEnquiryProperty();
        if (!hotelName) return false;

        const $roots = getPageEnquiryFormRoots();
        if (!$roots.length) return false;

        let applied = false;
        $roots.each(function () {
            const $root = $(this);
            const $propertyField = $root.find(
                '#input_1_39, .property_name textarea, .property_name input, textarea[name="input_39"], input[name="input_39"]'
            ).first();
            if (!$propertyField.length) return;

            const current = String($propertyField.val() || '').trim();
            if (current) {
                applied = true;
                return;
            }

            $propertyField.val(hotelName).addClass('disabled');
            applied = true;
            $root.find('.enquiry_type input').attr('value', 'Product');
            $propertyField.trigger('change');
        });

        if (applied) {
            localStorage.removeItem('enquiry_hotel_name');
        }
        return applied;
    }

    function getEnquiryPrefillResort($resortField) {
        const stored = consumeStashedEnquiryResort();
        const urlResort = getUrlResortName($resortField);
        const refResort = getReferrerResortName($resortField);
        if (stored || urlResort || refResort) {
            return stored || urlResort || refResort;
        }

        const searchResort = getSavedSearchResort();
        // /enquire/ direct open → no default. Same-origin navigation or blog/other pages → map search resort.
        if (isBareEnquirePage()) {
            return hasSameOriginReferrer() ? searchResort : '';
        }
        return searchResort;
    }

    function setEnquiryResortField($resortField, resortName, isLocked) {
        if (!$resortField || !$resortField.length) {
            return;
        }

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

        // Property/room Enquire → lock resort + property. Search / general → all editable.
        const lockProduct = !!data.lockProductFields;
        const hasProductData = !!(data.propertyName || data.resortName || data.checkIn || data.checkOut || data.roomName);
        const $propertyField = $scope.find(
            '#input_1_39, .property_name textarea, .property_name input, textarea[name="input_39"], input[name="input_39"]'
        ).first();

        if (data.propertyName) {
            $propertyField.val(data.propertyName);
            if (lockProduct) {
                $propertyField.prop('readonly', true).addClass('disabled');
            } else {
                $propertyField.prop('readonly', false).removeClass('disabled');
            }
        } else if (!$('body').hasClass('single-accommodation')) {
            $propertyField.val('').prop('readonly', false).removeClass('disabled');
        }

        const $resortField = $scope.find('.resort_name select, select[name="input_66"]').first();
        const urlResortName = getUrlResortName($resortField);
        // Prefer explicit trigger resort; otherwise URL resort for prefill only.
        const resortName = data.resortName || urlResortName || '';
        // Lock resort only for property/room Enquire flows.
        setEnquiryResortField($resortField, resortName, lockProduct && !!resortName);

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

        // Dates/guests always editable (Edit button removed). Resort/property lock only for product CTAs.
        $scope.find('.gform_wrapper.quote_form_wrapper')
            .attr('data-bbf-unlocked', '1')
            .attr('data-bbf-lock-resort', (lockProduct && !!resortName) ? '1' : '0')
            .attr('data-bbf-lock-property', (lockProduct && !!data.propertyName) ? '1' : '0');
        syncEnquiryBbfLock($scope);
        if (typeof initAllBbfToggles === 'function') {
            initAllBbfToggles();
        }
        setTimeout(function () { syncEnquiryBbfLock($scope); initAllBbfToggles(); }, 200);
        setTimeout(function () { syncEnquiryBbfLock($scope); initAllBbfToggles(); }, 600);
    }

    $(document).on('click', '.enq_cta, .enquire_btn, .enq-btn-popup', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);

        // Sticky footer CTA → open enquiry popup (prefill property on accommodation singles).
        if ($btn.hasClass('sticky-cta-btn') && $btn.closest('.sticky-cta-container').length) {
            const propertyName = resolvePagePropertyForEnquiry($btn);
            const resortName = String(
                $btn.attr('resort-name') || resolvePageResortForEnquiry($btn) || ''
            ).trim();

            console.log( 'resortName' );
            console.log( resortName );

            console.log( 'resolvePageResortForEnquiry' );
            console.log( resolvePageResortForEnquiry );

            const opened = openEnquiryFromTrigger($btn, {
                propertyName: propertyName,
                resortName: resortName,
                lockProductFields: !!propertyName
            });
            if (!opened) {
                stashEnquiryResortName(resortName);
                if (propertyName) localStorage.setItem('enquiry_hotel_name', propertyName);
                window.location.href = '/enquire/';
            }
            return;
        }

        // Listing cards (property Enquire) → lock resort + property
        if ($btn.hasClass('enquire_btn')) {
            const $card = $btn.closest('.accom-card, .result-card');
            const propertyName = (
                $btn.attr('hotel-name') ||
                $btn.parents('.accom-content').find('h3').first().text() ||
                $card.find('.accom-content h3').first().text() ||
                resolvePagePropertyForEnquiry($btn) ||
                ''
            ).trim();
            openEnquiryFromTrigger($btn, {
                propertyName: propertyName,
                resortName: $card.data('resortName') || $btn.attr('resort-name') || '',
                lockProductFields: true
            });
            return;
        }

        // Booking rate-plan / room Enquire → lock resort + property
        const $ratePlanBox = $btn.closest('.rb-rateplan-box');
        if ($ratePlanBox.length) {
            const roomData = parseRoomDataFromBox($ratePlanBox);
            openEnquiryFromTrigger($btn, {
                propertyName: roomData.propertyName || $btn.attr('hotel-name') || resolvePagePropertyForEnquiry($btn) || '',
                resortName: roomData.resortName || $btn.attr('resort-name') || '',
                checkIn: roomData.checkIn || '',
                checkOut: roomData.checkOut || '',
                roomName: roomData.roomName || $btn.attr('room-title') || '',
                lockProductFields: true
            });
            return;
        }

        // Search-card Enquire: on accommodation single → prefill + lock this property.
        if ($btn.hasClass('sb-enquire')) {
            const $card = $btn.closest('.search-card');
            const $resort = $card.find('.js-sb-resort').first();
            let resortName = '';
            if ($resort.length) {
                const val = String($resort.val() || '').trim();
                if (val && val.toLowerCase() !== 'all') {
                    resortName = normalizeResortName(
                        $resort.find('option:selected').text() || val
                    );
                }
            }
            const checkIn = $card.find('.js-sb-checkin').val() || '';
            const checkOut = $card.find('.js-sb-checkout').val() || '';
            const isAccSingle = $('body').hasClass('single-accommodation');
            const propertyName = isAccSingle ? resolvePagePropertyForEnquiry($btn) : '';
            if (!resortName && isAccSingle) {
                resortName = resolvePageResortForEnquiry($btn);
            }
            const opened = openEnquiryFromTrigger($btn, {
                propertyName: propertyName,
                resortName: resortName,
                checkIn: checkIn,
                checkOut: checkOut,
                lockProductFields: !!(isAccSingle && propertyName)
            });
            // No modal on this page → same handoff as sticky CTA
            if (!opened) {
                if (checkIn) localStorage.setItem('sb_checkin', checkIn);
                if (checkOut) localStorage.setItem('sb_checkout', checkOut);
                stashEnquiryResortName(resortName);
                if (propertyName) localStorage.setItem('enquiry_hotel_name', propertyName);
                window.location.href = '/enquire/';
            }
            return;
        }

        // Single-room / generic Enquire Now (.enq-btn-popup) → lock resort + property
        openEnquiryFromTrigger($btn, {
            propertyName: resolvePagePropertyForEnquiry($btn),
            resortName: resolvePageResortForEnquiry($btn),
            roomName: $btn.attr('room-title') || '',
            lockProductFields: true
        });
    });

    // Legacy .enq-btn (non-sticky) → full enquire page.
    $(document).on('click', '.enq-btn', function (e) {
        // Sticky CTA now opens popup via .enq-btn-popup — skip redirect.
        if ($(this).hasClass('sticky-cta-btn') || $(this).hasClass('enq-btn-popup')) return;

        e.preventDefault();
        const $btn = $(this);
        const roomTitle = $btn.attr('room-title') || '';
        const hotelName = $btn.attr('hotel-name') || '';
        const resortName = resolvePageResortForEnquiry($btn);

        if (roomTitle) localStorage.setItem('enquiry_room_title', roomTitle);
        if (hotelName) localStorage.setItem('enquiry_hotel_name', hotelName);
        stashEnquiryResortName(resortName);

        window.location.href = '/enquire/';
    });

    // Sticky CTA with enquire href (legacy markup) → stash resort; popup handler owns click when enq-btn-popup present.
    $(document).on('click', 'a.sticky-cta-btn', function () {
        const $btn = $(this);
        if ($btn.hasClass('enq-btn-popup') || $btn.hasClass('enq-btn')) return;
        if ($btn.closest('.sticky-cart-container').length) return;

        const href = ($btn.attr('href') || '').toString();
        if (!/\/enquire\/?/i.test(href)) return;

        stashEnquiryResortName(resolvePageResortForEnquiry($btn));
    });

    // Header/search/enquiry resort → keep every unlocked resort field in sync.
    var _kvResortSyncing = false;

    function matchSearchResortOptionValue($select, resortName) {
        if (!$select || !$select.length) return '';
        const normalized = normalizeResortName(resortName).toLowerCase();
        if (!normalized || normalized === 'all' || normalized === 'resort') return '';

        let found = '';
        $select.find('option').each(function () {
            const v = String(this.value || '').trim();
            const t = String(jQuery(this).text() || '').trim();
            if (!v || v.toLowerCase() === 'all') return;
            if (
                normalizeResortName(v).toLowerCase() === normalized ||
                normalizeResortName(t).toLowerCase() === normalized
            ) {
                found = v;
                return false;
            }
        });
        return found;
    }

    function resortValueForStorage(resortName) {
        const raw = (resortName || '').toString().trim();
        if (!raw || raw.toLowerCase() === 'all' || raw.toLowerCase() === 'resort') return '';
        if (/-accommodation$/i.test(raw)) return raw;
        const normalized = normalizeResortName(raw);
        if (!normalized) return '';
        return normalized.toLowerCase().replace(/\s+/g, '-') + '-accommodation';
    }

    function syncResortEverywhere(resortName, $source) {
        if (_kvResortSyncing) return;
        _kvResortSyncing = true;

        try {
            const raw = (resortName || '').toString().trim();
            const isClear = !raw || raw.toLowerCase() === 'all' || raw.toLowerCase() === 'resort';
            const storageVal = isClear ? '' : (resortValueForStorage(raw) || raw);

            if (storageVal) {
                localStorage.setItem('sb_resort', storageVal);
            } else {
                localStorage.removeItem('sb_resort');
            }

            // Sync all search-card resort selects (hero + header + mobile)
            $('.js-sb-resort').each(function () {
                const $el = $(this);
                if ($source && $el.is($source)) return;

                if (isClear) {
                    if ($el.val()) $el.val('');
                    return;
                }

                const matchVal = matchSearchResortOptionValue($el, raw) ||
                    matchSearchResortOptionValue($el, storageVal);
                if (matchVal && $el.val() !== matchVal) {
                    $el.val(matchVal);
                }
            });

            // Sync sidebar resort radios if present
            $('input[name="resort"]').each(function () {
                const $el = $(this);
                if ($source && $el.is($source)) return;
                if (isClear) {
                    $el.prop('checked', false);
                    return;
                }
                const elNorm = normalizeResortName($el.val()).toLowerCase();
                const wantNorm = normalizeResortName(raw).toLowerCase();
                $el.prop('checked', elNorm === wantNorm || $el.val() === storageVal);
            });

            // Sync enquiry forms (skip locked product CTAs)
            $('.resort_name select, select[name="input_66"]').each(function () {
                const $resortField = $(this);
                if ($source && $resortField.is($source)) return;
                if ($resortField.closest('.gform_wrapper').attr('data-bbf-lock-resort') === '1') return;
                if ($resortField.hasClass('disabled') || $resortField.attr('aria-disabled') === 'true') return;

                if (isClear) {
                    if ($resortField.val()) {
                        $resortField.val('').trigger('change');
                    }
                    return;
                }

                setEnquiryResortField($resortField, raw, false);
            });
        } finally {
            _kvResortSyncing = false;
        }
    }

    $(document).on('change', '.js-sb-resort', function () {
        syncResortEverywhere($(this).val(), $(this));
    });

    $(document).on('change', '.resort_name select, select[name="input_66"]', function () {
        const $field = $(this);
        if ($field.closest('.gform_wrapper').attr('data-bbf-lock-resort') === '1') return;
        if ($field.hasClass('disabled') || $field.attr('aria-disabled') === 'true') return;
        syncResortEverywhere($field.val(), $field);
    });

    $(document).on('mousedown keydown', '.resort_name select.disabled, select[name="input_66"].disabled', function (e) {
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
        // Resort select lives on .resort_name (field id may differ; do not use #input_1_66 HTML slot).
        const $resort = $wrap.find('.resort_name select, select[name="input_66"]').first();
        const $checkIn = $wrap.find('input[name="input_5"], #input_1_5').first();
        let $checkOut = $wrap.find('input[name="input_6"], #input_1_6').first();
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

    function enquiryShouldLockResort($wrap) {
        return $wrap.attr('data-bbf-lock-resort') === '1';
    }

    function setEnquiryBbfLocked($wrap, locked) {
        if (!$wrap || !$wrap.length) return;

        // Edit button removed: never lock dates/guests. Only resort may stay locked for product CTAs.
        $wrap.removeClass('bbf-fields-locked');
        const f = getEnquiryBbfFields($wrap);
        const keepResortLocked = enquiryShouldLockResort($wrap);

        if (keepResortLocked) {
            f.$resort
                .addClass('disabled')
                .attr('aria-disabled', 'true')
                .attr('tabindex', '-1')
                .prop('disabled', false);
        } else {
            f.$resort
                .removeClass('disabled')
                .attr('aria-disabled', 'false')
                .attr('tabindex', '0');
        }
        f.$dates.prop('readonly', false).removeAttr('tabindex');
        f.$guests.removeAttr('aria-disabled');

        $wrap.toggleClass('bbf-resort-locked', keepResortLocked);
    }

    function syncEnquiryBbfLock($from) {
        getEnquiryFormWrappers($from).each(function () {
            const $wrap = $(this);
            if (!$wrap.find('.gfield.bbf').length) return;

            // Always keep BBF bar editable (no Edit/Confirm gate).
            $wrap.attr('data-bbf-unlocked', '1');
            setEnquiryBbfLocked($wrap, false);
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

    // Edit ⇄ Confirm toggle button (no switch knob)
    function setBbfToggleText($a, text, unlocked) {
        if (!$a || !$a.length) return;

        $a.addClass('bbf-edit-btn')
            .toggleClass('is-editing', !!unlocked)
            .attr({ role: 'button', 'aria-pressed': unlocked ? 'true' : 'false', href: 'javascript:void(0)' });
        $a.find('.bbf-toggle-track, .bbf-toggle-thumb, .bbf-toggle-label').remove();

        const $icons = $a.children('i, svg, img, .fa, [class*="icon"], [class*="pencil"]').detach();
        $a.contents().filter(function () { return this.nodeType === 3; }).remove();
        $a.prepend(document.createTextNode(text + ' '));

        if ($icons.length) {
            $a.append($icons);
        } else {
            $a.append(unlocked
                ? '<i class="fa fa-check" aria-hidden="true"></i>'
                : '<i class="fa fa-pencil" aria-hidden="true"></i>');
        }

        $a.find('i.fa, i[class*="fa-"]').first()
            .removeClass('fa-pencil fa-check fa-pen')
            .addClass(unlocked ? 'fa-check' : 'fa-pencil');
    }

    function getBbfToggleLink($wrap) {
        return $wrap.find('.gfield.bbf.edit-field a.bbf-edit-btn, .gfield.bbf.edit-field a, .gfield.bbf a.bbf-edit-btn, .gfield.bbf a, .gfield.edit-field a, .gfield a').filter(function () {
            return $(this).hasClass('bbf-edit-btn') || /edit|confirm|change|done/i.test(($(this).text() || ''));
        }).first();
    }

    // locked → "Edit", unlocked → "Confirm"
    function refreshBbfToggleLabel($wrap) {
        const $a = getBbfToggleLink($wrap);
        if (!$a.length) return;
        const unlocked = $wrap.attr('data-bbf-unlocked') === '1';
        setBbfToggleText($a, unlocked ? 'Confirm' : 'Edit', unlocked);
    }

    // Edit → editable, Confirm → wapis lock
    $(document).on('click', '.gform_wrapper.quote_form_wrapper .gfield a, .gform_wrapper.quote_form_wrapper .gfield .bbf-edit-btn', function (e) {
        const $link = $(this);
        const label = ($link.text() || '').replace(/\s+/g, ' ').trim();
        if (!$link.hasClass('bbf-edit-btn') && !/edit|confirm|change|done/i.test(label)) return;

        e.preventDefault();
        e.stopPropagation();

        const $wrap = $link.closest('.gform_wrapper.quote_form_wrapper');
        const isUnlocked = $wrap.attr('data-bbf-unlocked') === '1';

        if (isUnlocked) {
            // Confirm → agar sari fields filled hain to lock
            $wrap.attr('data-bbf-unlocked', '0');
            syncEnquiryBbfLock($wrap);
        } else {
            // Edit → dates/guests editable (resort may stay locked on Enquire Now)
            $wrap.attr('data-bbf-unlocked', '1');
            setEnquiryBbfLocked($wrap, false);
        }

        refreshBbfToggleLabel($wrap);
    });

    // Block interaction while BBF dates were historically locked (kept for safety)
    $(document).on('mousedown focus click', '.bbf-fields-locked .gfield.bbf input, .bbf-fields-locked .gfield.bbf select', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(this).blur();
        return false;
    });

    // Property/room Enquire: resort stays locked
    $(document).on('mousedown focus click keydown', '.gform_wrapper[data-bbf-lock-resort="1"] .resort_name select, .gform_wrapper[data-bbf-lock-resort="1"] select[name="input_66"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(this).blur();
        return false;
    });

    // Property/room Enquire: property stays locked
    $(document).on('mousedown focus click keydown', '.gform_wrapper[data-bbf-lock-property="1"] .property_name textarea, .gform_wrapper[data-bbf-lock-property="1"] #input_1_39', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(this).blur();
        return false;
    });

    function initAllBbfToggles() {
        $('.gform_wrapper.quote_form_wrapper').each(function () {
            refreshBbfToggleLabel($(this));
        });
    }
    window.kvInitAllBbfToggles = initAllBbfToggles;

    syncEnquiryBbfLock();
    initAllBbfToggles();
    setTimeout(function () { syncEnquiryBbfLock(); initAllBbfToggles(); }, 300);
    setTimeout(function () { syncEnquiryBbfLock(); initAllBbfToggles(); }, 1000);

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



    // Enquiry popup / page form: after successful AJAX submit.
    // gform_confirmation_loaded only fires after a valid submit (never on validation errors).
    $(document).on('gform_confirmation_loaded', function (event, formId) {
        if (parseInt(formId, 10) !== 1) return;
        if (enquiryModalSuccessShown || enquiryPageSuccessShown) return;
        if (enquiryModalHasValidation($('.Enquiry-modal'))) {
            enquiryModalAwaitingSubmit = false;
            return;
        }
        if (
            enquiryModalAwaitingSubmit ||
            ($('body').hasClass('enquire-open') && $('.Enquiry-modal.active').length)
        ) {
            handleEnquiryModalSuccess();
            return;
        }
        if (enquiryPageAwaitingSubmit || pageHasEnquiryConfirmation()) {
            if (enquiryPageHasValidation()) {
                enquiryPageAwaitingSubmit = false;
                return;
            }
            handlePageEnquirySuccess(null, { force: true });
        }
    });

    // Fallback for GF AJAX posts to the current page URL.
    $(document).ajaxComplete(function (event, xhr, settings) {
        if (enquiryModalSuccessShown || enquiryPageSuccessShown) return;

        const wantsModal =
            enquiryModalAwaitingSubmit ||
            ($('body').hasClass('enquire-open') && $('.Enquiry-modal.active').length);
        const wantsPage = enquiryPageAwaitingSubmit;
        if (!wantsModal && !wantsPage) return;

        const data = settings && settings.data != null ? String(settings.data) : '';
        const isGformPost = data.indexOf('gform_submit') !== -1 || data.indexOf('gform_submit_button_1') !== -1;
        if (!isGformPost) return;

        let body = '';
        try { body = xhr && xhr.responseText ? String(xhr.responseText) : ''; } catch (e) { body = ''; }
        if (!body) return;

        if (
            body.indexOf('gform_validation_errors') !== -1 ||
            body.indexOf('gform_validation_error') !== -1 ||
            body.indexOf('gfield_error') !== -1
        ) {
            enquiryModalAwaitingSubmit = false;
            enquiryPageAwaitingSubmit = false;
            return;
        }

        const hasConfirmation =
            body.indexOf('gform_confirmation_message') !== -1 ||
            body.indexOf('gform_confirmation_wrapper') !== -1 ||
            body.indexOf('gform_confirmation_message_1') !== -1;

        if (!hasConfirmation) return;

        setTimeout(function () {
            if (wantsModal) {
                if (enquiryModalHasValidation($('.Enquiry-modal'))) {
                    enquiryModalAwaitingSubmit = false;
                    return;
                }
                handleEnquiryModalSuccess();
                return;
            }
            if (enquiryPageHasValidation()) {
                enquiryPageAwaitingSubmit = false;
                return;
            }
            handlePageEnquirySuccess(null, { force: true });
        }, 50);
    });

    // Watch modal slot mutations (GF iframe/AJAX often replaces markup without ajaxComplete).
    (function watchEnquiryModalSlot() {
        const slot = document.querySelector('.Enquiry-modal-form-slot');
        if (!slot || typeof MutationObserver === 'undefined') return;
        const observer = new MutationObserver(function () {
            if (!enquiryModalAwaitingSubmit || enquiryModalSuccessShown) return;
            setTimeout(function () {
                maybeHandleEnquiryModalSuccessFromDom();
            }, 30);
        });
        observer.observe(slot, { childList: true, subtree: true });
    })();

    // Cache clean copies for reset after success (modal + page "Skip the searching" form).
    cacheEnquiryModalFormHtml();
    cachePageEnquiryFormHtml();
    setTimeout(cacheEnquiryModalFormHtml, 500);
    setTimeout(cachePageEnquiryFormHtml, 500);
    setTimeout(cacheEnquiryModalFormHtml, 1500);
    setTimeout(cachePageEnquiryFormHtml, 1500);

    $(document).on('gform_post_render', function (event, formId) {



        // Re-initialise date pickers whenever form 1 (enquiry form) renders

        if (formId === 1) {

            const ci = localStorage.getItem('niseko_checkin');

            const co = localStorage.getItem('niseko_checkout');

            if (ci) $(CHECKIN_SEL).val(ci);

            if (co) $(CHECKOUT_SEL).val(co);

            $(CHECKOUT_SEL).prop('disabled', false);

            // Prefill Resort only from URL / sticky handoff / referrer — never sb_resort default.
            // Direct /enquire/ with no prior resort page → leave unselected.
            $('.mob_quote_form1, .gform_wrapper.quote_form_wrapper, .acc_enquiry_form').each(function () {
                const $resortField = $(this).find('#input_1_66, select[name="input_66"], .resort_name select').first();
                if (!$resortField.length || $resortField.val()) return;

                const resortName = getEnquiryPrefillResort($resortField);
                if (resortName) {
                    const lockFromUrl = !!(getUrlResortName($resortField) || getReferrerResortName($resortField));
                    setEnquiryResortField($resortField, resortName, lockFromUrl);
                }
            });

            // Page form only: map property when handed off from previous page / single property.
            applyIncomingPropertyToPageEnquiryForms();

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
            if (typeof window.kvInitAllBbfToggles === 'function') {
                window.kvInitAllBbfToggles();
            }

            // Keep popup open after validation. While popup is open, keep the page
            // form parked so confirmation/validation never sticks under "Skip the searching".
            if ($('body').hasClass('enquire-open') || enquiryModalAwaitingSubmit) {
                parkEnquiryFormExcept('modal');
                if (
                    getPageEnquiryMount().find(
                        '.gform_confirmation_wrapper, .gform_confirmation_message, .gform_confirmation_message_1'
                    ).length
                ) {
                    resetPageEnquiryForm();
                }
                $('.Enquiry-modal').addClass('active').css('display', 'flex');

                if (!maybeHandleEnquiryModalSuccessFromDom()) {
                    if ($('.Enquiry-modal .gform_validation_error, .Enquiry-modal .gform_validation_errors, .Enquiry-modal .gfield_error').length) {
                        enquiryModalAwaitingSubmit = false;
                    }
                    cacheEnquiryModalFormHtml();
                    if (typeof clearEnquiryValidationIn === 'function') {
                        clearEnquiryValidationIn(getListingEnquiryScope());
                    }
                }
            } else {
                if (typeof unparkEnquiryForm === 'function') {
                    unparkEnquiryForm();
                }
                // Validation re-render: clear awaiting, never show fake thank-you.
                if (enquiryPageHasValidation()) {
                    enquiryPageAwaitingSubmit = false;
                    cachePageEnquiryFormHtml();
                } else if (pageHasEnquiryConfirmation()) {
                    // Real confirmation only — do not use awaiting flag alone.
                    handlePageEnquirySuccess(null, { force: true });
                } else {
                    if (enquiryPageAwaitingSubmit) {
                        // Still waiting for iframe/confirmation; keep flag.
                    } else {
                        cacheEnquiryModalFormHtml();
                        cachePageEnquiryFormHtml();
                    }
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

      function kvUpdateStickyCtaActive() {
        const $cta = jQuery('.sticky-cta-container');
        if (!$cta.length) return;

        const filter_section = jQuery('section.hero-banner-with-filter');
        const topHeader = jQuery('.topHeader');
        const filter_height = filter_section.length > 0 ? filter_section.outerHeight() : 0;
        const topHeader_height = topHeader.length > 0 ? topHeader.outerHeight() : 0;
        const scroll_top = jQuery(window).scrollTop();

        // where-to-stay / guide pages often have no hero filter → show as soon as user scrolls a bit,
        // or immediately when threshold is 0.
        const threshold = Math.max(0, filter_height - topHeader_height);
        $cta.toggleClass('active', scroll_top >= threshold);
    }

    // Sticky CTA active class — always (including /where-to-stay/ where shouldAddClasses is true).
    jQuery(window).on('scroll.kvStickyCta', kvUpdateStickyCtaActive);
    kvUpdateStickyCtaActive();



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



    // Populate page quote form: single property → name; listing → no property;
    // /enquire/ → sticky handoff. Popup modal is separate (openEnquiryFromTrigger).
    if ($('form.quote_form').length) {
        const roomTitle = localStorage.getItem('enquiry_room_title') ?? '';
        const hotelName = resolveIncomingPageEnquiryProperty();
        const $pageRoots = getPageEnquiryFormRoots();
        const $resortScope = $pageRoots.first().length ? $pageRoots.first() : $(document);
        const $resortField = $resortScope.find('#input_1_66, select[name="input_66"], .resort_name select').first();
        const resortName = getEnquiryPrefillResort($resortField);

        if (roomTitle) {
            $pageRoots.find('.room_name input').val(roomTitle);
        }

        applyIncomingPropertyToPageEnquiryForms();

        if (resortName && $resortField.length) {
            setEnquiryResortField($resortField, resortName, !!(hotelName || roomTitle));
        }

        if (roomTitle || hotelName || resortName) {
            localStorage.removeItem('enquiry_room_title');
            // enquiry_hotel_name cleared inside applyIncomingPropertyToPageEnquiryForms when applied

            if (roomTitle || hotelName) {
                $pageRoots.find('.enquiry_type input').attr('value', 'Product');
            }

            $pageRoots.find(
                '.room_name input, .property_name textarea, .property_name input, #input_1_66, select[name="input_66"], .resort_name select, .enquiry_type input'
            ).trigger('change');
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

            if (typeof window.kvSyncAllInlineChildAges === 'function') {
                window.kvSyncAllInlineChildAges(g.children);
            }

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

                    if (this.disabled) return;
                    // Age steppers sit under Children — closest('.g-row') would hit the parent Children row.
                    if (this.classList.contains('js-btn-cage-minus') || this.classList.contains('js-btn-cage-plus')) return;
                    if (this.closest('.kv-child-age-row')) return;

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

                        if (typeof window.kvSyncAllInlineChildAges === 'function') {
                            window.kvSyncAllInlineChildAges(g.children);
                        }

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

            if (type === 'children' && typeof window.kvSyncAllInlineChildAges === 'function') {
                window.kvSyncAllInlineChildAges(g.children);
            }
        }

        jQuery(document).off('click.kvRoomGuests', '.room-filter-guests-popover .g-btn');
        jQuery(document).on('click.kvRoomGuests', '.room-filter-guests-popover .g-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (this.disabled) return;
            // Ignore inline child-age steppers.
            if (this.classList.contains('js-btn-cage-minus') || this.classList.contains('js-btn-cage-plus')) return;
            if (this.closest('.kv-child-age-row')) return;

            const row = this.closest('.g-row');
            if (row && row.classList.contains('kv-child-age-row')) return;

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

/* Accommodation gallery skeleton loaders */
jQuery(function ($) {
    $('.acc-gallery .gi.is-skeleton[data-bg]').each(function () {
        var $el = $(this);
        var url = $el.attr('data-bg');
        if (!url) {
            $el.removeClass('is-skeleton');
            return;
        }

        var img = new Image();
        img.onload = img.onerror = function () {
            $el.css('background-image', 'url("' + url + '")');
            $el.removeClass('is-skeleton');
        };
        img.src = url;
    });
});

