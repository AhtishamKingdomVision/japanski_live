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

jQuery(function ($) {
    console.log('script loaded');

//     $(document).ready(function() {
//       $(document).on('change', '#input_1_4', function() {
//         if ($(this).val() === 'Hakuba') {
//           $('#gform_submit_button_1').prop('disabled', true);
//           $('#gform_submit_button_1').addClass('closess');
//         } else {
//           $('#gform_submit_button_1').prop('disabled', false);
//           $('#gform_submit_button_1').removeClass('closess');
//         }
//       });
//     });


    // Floating Bar cookies
    // let floatingbar = getCookie('floating_bar');
    // if(floatingbar == ''){
    $('.floating').addClass('active');
    // $('#gform_submit_button_3').on('click' , function($){
    //     $('.floating_content').addClass('remove_text');
    //     setTimeout(function() { 
    //       $('.floating_content').removeClass('remove_text');
    //     }, 12000);
    // })


     $(document).ready(function(){
        
        if (!localStorage.getItem("vote_popup_shown")){
            setTimeout(function() {
                $(".vote_for_us").removeClass('hide');
                localStorage.setItem("vote_popup_shown", "show")
            }, 20000); // 20 seconds = 20000ms

        }
        else if(localStorage.getItem("vote_popup_shown")){
            $(".vote_for_us").removeClass('hide');
        }

        $(document).on('gform_confirmation_loaded', function(event, formId){
            // if(formId == 1) {
            //   $(document).on('change', '#input_1_4', function() {
            //     if ($(this).val() === 'Hakuba') {
            //       $('#gform_submit_button_1').prop('disabled', true);
            //       $('#gform_submit_button_1').addClass('closess');
            //     } else {
            //       $('#gform_submit_button_1').prop('disabled', false);
            //       $('#gform_submit_button_1').removeClass('closess');
            //     }
            //   });
            // }
            if(formId == 3) {
                $('.floating_content').addClass('remove_text');
                setTimeout(function() { 
                  $('.floating_content').removeClass('remove_text');
                }, 10000);
            }
        });
    });
 
    $(document).on('click', '.close_flo', function(){
        $('.floating').removeClass('active');
        setCookie('floating_bar', 'floating_cache', 1 );
    });

    // $(document).on('gform_confirmation_loaded', function(event, formId){
    //     if(formId == 3){
    //             setCookie('floating_bar', 'floating_cache', 1 );
    //             // $('.floating').removeClass('active');
    //             $(".floating_content").addClass('full_col');
    //             $(".floating_content p").replaceWith("<p>Thank you for submitting your e-mail address, we will be in touch when bookings open</p>");
    //     }
    // });

    // Floating Bar cookies

    // Configure/customize these variables.
    var showChar = 265; // How many characters are shown by default
    var showCharReview = 250; // How many characters are shown by default
    var ellipsestext = "...";
    var moretext = "Read More";
    var lesstext = "Read Less";

    $('.review-more').each(function () {
        var content = $(this).html();
        if (content.length > showCharReview) {
            var c = content.substr(0, showCharReview);
            var h = content.substr(showCharReview, content.length - showCharReview);
            var html = c + '<span class="moreellipses">' + ellipsestext + '&nbsp;</span><span class="morecontent"><span>' + h + '</span>&nbsp;&nbsp;<a href="" class="morelink">' + moretext + '</a></span>';
            $(this).html(html);
        }
    });

    $('.more').each(function () {
        var content = $(this).html();

        if (content.length > showChar) {
            var c = content.substr(0, showChar);
            var h = content.substr(showChar, content.length - showChar);
            var html = c + '<span class="moreellipses">' + ellipsestext + '&nbsp;</span><span class="morecontent"><span>' + h + '</span>&nbsp;&nbsp;<a href="" class="morelink">' + moretext + '</a></span>';
            $(this).html(html);
        }
    });
    $(".morelink").attr("rel","nofollow");
    $(".morelink").attr("href","javascript:;");
    $(".morelink").click(function () {
        if ($(this).hasClass("less")) {
            $(this).removeClass("less");
            $(this).html(moretext);
        } else {
            $(this).addClass("less");
            $(this).html(lesstext);
        }
        $(this).parent().prev().toggle();
        $(this).prev().toggle();
        return false;
    });

    $(document).on('submit', '#main_form', function (event) {
        event.preventDefault();
        let form2 = $( this ).find('[type="submit"]').attr( 'data-list' ),
            parentDiv = $(this).parents('.pl__inn'),
            classname = form2 ? '_1' : '',
            searches = $('#searches'+classname).val(),
            rooms_field = $('#rooms_field'+classname).val(),
            price = $('#price'+classname).val(),
            type = $('#type'+classname).val() ? $('#type'+classname).val() : '',
            area = $('#area'+classname).val(),
            current_page = $(this).attr('data-page'),
            change_page = 0;
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                // action: 'main_form_ajax_kv',    //funtion call
                searches: searches,
                rooms_field: rooms_field,
                price: price,
                type: type,
                area: area,
            },
            success: function (response) {
                if(form2){
                    $("."+form2).html( $('.'+form2, response).html() );
                }
                else{
                    $(".pl_listing").html($('.pl_listing', response).html());
                }
                $(".loadmore-button-wrapper").html($('.loadmore-button-wrapper', response).html());
                change_page = 1;
                parentDiv.find('#main_form').attr('data-page', change_page);
            },
            error: function (jqXHR, exception) {
                var msg = '';
                if (jqXHR.status === 0) {
                    msg = 'Not connect.\n Verify Network.';
                } else if (jqXHR.status == 404) {
                    msg = 'Requested page not found. [404]';
                } else if (jqXHR.status == 500) {
                    msg = 'Internal Server Error [500].';
                } else if (exception === 'parsererror') {
                    msg = 'Requested JSON parse failed.';
                } else if (exception === 'timeout') {
                    msg = 'Time out error.';
                } else if (exception === 'abort') {
                    msg = 'Ajax request aborted.';
                } else {
                    msg = 'Uncaught Error.\n' + jqXHR.responseText;
                }
                console.log('Error: '+ msg );
            },
        });
        if( change_page == 1 ){
            $(this).attr('data-page', '1');
            console.log( $(this).attr('data-page') );
            console.log( event.innerHTML );
        }
    });

    $(function () {
        var slider = document.getElementById("price");
        var slider_1 = document.getElementById("price_1");
        var output = document.getElementById("demo");
        var output_1 = document.getElementById("demo_1");
        if (slider) {
            var commaseparated = numberWithCommas(slider.value);
            output.innerHTML = '(JPY ' + commaseparated + ')';

            slider.oninput = function () {
                output.innerHTML = '(JPY ' + numberWithCommas(this.value) + ')';
            }
        }

        if (slider_1) {
            var commaseparated = numberWithCommas(slider_1.value);
            output_1.innerHTML = '(JPY ' + commaseparated + ')';

            slider_1.oninput = function () {
                output_1.innerHTML = '(JPY ' + numberWithCommas(this.value) + ')';
            }
        }

    });

    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // jQuery('#area').select2();
    if ($('.accomodation-select2').length > 0) {
        $('.accomodation-select2').select2();
    }
    if ($('.kv_loadmore').length > 0) {
        $(document).on('click', '.kv_loadmore', function (event) {
            let current_page = parseInt( $(this).parents('.pl__inn').find('#main_form').attr('data-page') );
            event.preventDefault();
            current_page = current_page + 1;

            let form = $(this).parents('.pl__inn').find('#main_form'),
                // form2_btn = form.find( '[type="submit"]' ).attr( 'data-list' ),
                form22 = form.find('[type="submit"]').attr( 'data-list' ),
                button = $(this),
                classname = button.attr('data-load') ? '_1' : '',
                max_page = $('.kv_loadmore').data('max_page') || 1,
                searches = $('#searches'+classname).val(),
                rooms_field = $('#rooms_field'+classname).val(),
                price = $('#price'+classname).val(),
                type = $('#type'+classname).val() ? $('#type'+classname).val() : '',
                area = $('#area'+classname).val(),
                guest = $('#guest').val();


            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    current_page: current_page,
                    searches: searches,
                    rooms_field: rooms_field,
                    price: price,
                    area: area,
                    type: type,
                    guest: guest,
                },
                beforeSend: function (xhr) {
                    button.text('Loading...'); // change the button text, you can also add a preloader image
                },
                success: function (response) {
                    form.attr('data-page', current_page);
                    button.text('Load More');
                    if(form22){
                        $("."+form22+" .pl_list").append( $('.'+form22+' .pl_list', response).html() );
                    }
                    else
                    $(".pl_listing .pl_list").append($('.pl_listing .pl_list', response).html());

                    if (current_page >= max_page) {
                        button.remove();
                    }
                },
                error: function (jqXHR, exception) {
                    var msg = '';
                    if (jqXHR.status === 0) {
                        msg = 'Not connect.\n Verify Network.';
                    } else if (jqXHR.status == 404) {
                        msg = 'Requested page not found. [404]';
                    } else if (jqXHR.status == 500) {
                        msg = 'Internal Server Error [500].';
                    } else if (exception === 'parsererror') {
                        msg = 'Requested JSON parse failed.';
                    } else if (exception === 'timeout') {
                        msg = 'Time out error.';
                    } else if (exception === 'abort') {
                        msg = 'Ajax request aborted.';
                    } else {
                        msg = 'Uncaught Error.\n' + jqXHR.responseText;
                    }
                    console.log( msg );
                },
            });
        });
    }

    jQuery(document).on('gform_page_loaded', function(event, form_id, current_page){
        if(form_id == 1 || form_id == 4){
            if ($('input#input_'+form_id+'_5').length == 1) {
                $('input#input_'+form_id+'_5').addClass('dateDropper').dateDropper({
                    'large': 1,
                    'minDate': kv_object.check_start_date,
                    'maxDate': kv_object.check_end_date,
                     preset: false,
                    'largeDefault': 1,
                    'format': 'd/m/Y',
                    // description: "The event description here",
                    eventSelector: 'click',
                    onChange: function (res) {
                        console.log('onChange');
                        if(kv_object.date_dropper_content){
                            jQuery('.datedropper .picker').find('ul.pick-lg-b').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                        }
                        let minDate = res.date.m + '/' + res.date.d + '/' + res.date.Y;
                        if(kv_object.check_min_days_option == '1' && kv_object.check_min_days != ""){
                            let dt = new Date(minDate);
                            dt.setDate(dt.getDate() + (parseInt(kv_object.check_min_days)))
                            minDate = (dt.getMonth() + 1)  + '/' + dt.getDate() + '/' + dt.getFullYear();
                            $('input#input_'+form_id+'_6').attr('disabled', true).addClass('dateDropper').dateDropper('destroy');
                            $('input#input_'+form_id+'_6').removeAttr('disabled', true).dateDropper({
                                'large': 1,
                                'minDate': minDate,
                                'maxDate': kv_object.check_end_date,
                                'largeDefault': 1,
                                'format': 'd/m/Y',
                                eventSelector: 'click',
                                onchange: function(res){
                                    console.log('ch')
                                    if(kv_object.date_dropper_content){
                                        jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                                    }
                                }
                            });
                        }
                        $('input#input_'+form_id+'_6').removeAttr('disabled').dateDropper('set', {
                            minDate,
                            eventSelector: 'click',
                            onchange: function(res){
                                console.log('ch')
                                if(kv_object.date_dropper_content){
                                    jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                                }
                            }
                        });
                    }
                });

                $(document).on('mousedown', '.pick-lg li.pick-v', function (e) {
                    $('input#input_'+form_id+'_5').dateDropper('hide');
                    $('input#input_'+form_id+'_6').dateDropper('hide');
                })
            }

            if ($('input#input_'+form_id+'_6').length == 1) {
                $('input#input_'+form_id+'_6').attr('disabled', true).addClass('dateDropper').dateDropper({
                    'large': 1,
                    'minDate': kv_object.check_start_date,
                    'maxDate': kv_object.check_end_date,
                    'largeDefault': 1,
                    eventSelector: 'click',
                    'format': 'd/m/Y',
                    onchange: function(rs){
                        console.log('ch')
                        if(kv_object.date_dropper_content){
                            jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                        }
                    }
                });
            }
        }
    });

    if ($('input#input_4_5').length == 1) {
        $('input#input_4_5').addClass('dateDropper').dateDropper({
            'large': 1,
            'minDate': kv_object.check_start_date,
            'maxDate': kv_object.check_end_date,
            'largeDefault': 1,
            preset: false,

            'format': 'd/m/Y',
            eventSelector: 'click',
            onChange: function (res) {
                console.log('onChange');
                if(kv_object.date_dropper_content){
                    jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                }
                let minDate = res.date.m + '/' + res.date.d + '/' + res.date.Y;
                if(kv_object.check_min_days_option == '1' && kv_object.check_min_days != ""){
                    let dt = new Date(minDate);
                    dt.setDate(dt.getDate() + (parseInt(kv_object.check_min_days)))
                    minDate = (dt.getMonth() + 1)  + '/' + dt.getDate() + '/' + dt.getFullYear();
                    $('input#input_4_6').attr('disabled', true).addClass('dateDropper').dateDropper('destroy');
                    $('input#input_4_6').removeAttr('disabled', true).dateDropper({
                        'large': 1,
                        'minDate': minDate,
                        'maxDate': kv_object.check_end_date,
                        'largeDefault': 1,
                        eventSelector: 'click',
                        'format': 'd/m/Y',
                        onchange: function(rs){
                            console.log('ch')
                            if(kv_object.date_dropper_content){
                                jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                            }
                        }
                    });
                }
                $('input#input_4_6').removeAttr('disabled').dateDropper('set', {
                    minDate,
                    eventSelector: 'click',
                    onchange: function(res){
                        console.log('ch')
                        if(kv_object.date_dropper_content){
                            jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                        }
                    }
                });
            }
        });

        $(document).on('mousedown', '.pick-lg li.pick-v', function (e) {
            $('input#input_4_5').dateDropper('hide');
            $('input#input_4_6').dateDropper('hide');
        })
    }

    if ($('input#input_1_5').length == 1) {
        $('input#input_1_5').addClass('dateDropper').dateDropper({
            'large': 1,
            'minDate': kv_object.check_start_date,
            'maxDate': kv_object.check_end_date,
            'largeDefault': 1,
            preset: false,

            'format': 'd/m/Y',
            eventSelector: 'click',
            onChange: function (res) {
                console.log('onChange');
                if(kv_object.date_dropper_content){
                    jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                }
                let minDate = res.date.m + '/' + res.date.d + '/' + res.date.Y;
                if(kv_object.check_min_days_option == '1' && kv_object.check_min_days != ""){
                    let dt = new Date(minDate);
                    dt.setDate(dt.getDate() + (parseInt(kv_object.check_min_days)))
                    minDate = (dt.getMonth() + 1)  + '/' + dt.getDate() + '/' + dt.getFullYear();
                    $('input#input_1_6').attr('disabled', true).addClass('dateDropper').dateDropper('destroy');
                    $('input#input_1_6').removeAttr('disabled', true).dateDropper({
                        'large': 1,
                        'minDate': minDate,
                        'maxDate': kv_object.check_end_date,
                        'largeDefault': 1,
                        eventSelector: 'click',
                        'format': 'd/m/Y',
                        onchange: function(rs){
                            console.log('ch')
                            if(kv_object.date_dropper_content){
                                jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                            }
                        }
                    });
                }
                $('input#input_1_6').removeAttr('disabled').dateDropper('set', {
                    minDate,
                    eventSelector: 'click',
                    onchange: function(res){
                        console.log('ch')
                        if(kv_object.date_dropper_content){
                            jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                        }
                    }
                });
            }
        });

        $(document).on('mousedown', '.pick-lg li.pick-v', function (e) {
            $('input#input_1_5').dateDropper('hide');
            $('input#input_1_6').dateDropper('hide');
        })
    }

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

    if ($('input#input_1_6').length == 1) {
        $('input#input_1_6').attr('disabled', true).addClass('dateDropper').dateDropper({
            'large': 1,
            'minDate': kv_object.check_start_date,
            'maxDate': kv_object.check_end_date,
            'largeDefault': 1,
            eventSelector: 'click',
            'format': 'd/m/Y',
            onchange: function(rs){
                console.log('ch')
                if(kv_object.date_dropper_content){
                    jQuery('.datedropper .picker').find('.pick-lg').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`)
                }
            }
        });
    }

    $(document).on('click', '.fm_open', function () {
        $('.more_text').fadeIn('slow');
    });

    $(document).on('click', '.fm_close', function () {
        $('.more_text').fadeOut('slow');
    });

    $('ul.cus-menu , ul.cus-second-menu').find('li.menu-item > a').hover(function () {
        $(this).parents('li.menu-item').addClass('is_hover');
        $(this).parents('li.menu-item').find('ul.sub-menu').stop(true, true).delay(200).fadeIn(200);
    }, function () {
        $(this).parents('li.menu-item').removeClass('is_hover');
        $(this).parents('li.menu-item').find('ul.sub-menu').stop(true, true).delay(200).fadeOut(200);
    });

    // Enquire Script
    $(window).on('scroll', function () {
        var height = $(window).scrollTop().valueOf();
        if (height > 200) {
            $('.enquire-btn').css('right', '-25px');
        } else {
            $('.enquire-btn').css('right', '-50%');
        }

        if (height > 1000) {
            $('.jumping').css('left', '10px');
        } else {
            $('.jumping').css('left', '-50%');
        }
        // Sticky Header
        if (height > 50) {
            $('.navigation-wrapper').addClass('sticky');
        } else {
            $('.navigation-wrapper').removeClass('sticky');
        }

        // Jumplinks
        var section1 = $('.top_banner').innerHeight();
        var section2 = $('.overview_with_jumplinks').innerHeight();
        var calculate = section1 + section2;
        if ($(this).scrollTop() > calculate) {
            $('.jumplinks').addClass('scrolling');
        } else {
            $('.jumplinks').removeClass('scrolling');
        }

    });

    // reviews-carousel
    if ($('.reviews-carousel').length == 1) {
        $('.reviews-carousel').slick({
            infinite: true,
            slidesToShow: 1,
            slidesToScroll: 1,
            draggable: true,
            autoplay: true,
            autoplaySpeed: 2000,
            dots: false,
            arrows: true,
            adaptiveHeight: true,
            prevArrow: '<i class="fa fa-long-arrow-left"></i>',
            nextArrow: '<i class="fa fa-long-arrow-right"></i>',
            responsive: [{
                breakpoint: 991,
                settings: {
                    slidesToShow: 2,
                    slidesToScroll: 2
                }
            },
            {
                breakpoint: 767,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    dots: true,
                    arrows: false
                }
            }
            ]
        });
    }

    if ($('.map_image').length == 1) {
        // map slider
        $('.map_image').slick({
            infinite: true,
            slidesToShow: 1,
            slidesToScroll: 1,
            draggable: false,
            autoplay: true,
            autoplaySpeed: 2000,
            dots: false,
            prevArrow: '<i class="fa fa-long-arrow-left"></i>',
            nextArrow: '<i class="fa fa-long-arrow-right"></i>'
        });
    }

    if ($('.tc_carousel').length == 1) {
        $('.tc_carousel').slick({
            infinite: true,
            slidesToShow: 2,
            slidesToScroll: 2,
            draggable: false,
            // autoplay: true,
            // autoplaySpeed: 2000,
            dots: false,
            prevArrow: '<i class="fa fa-long-arrow-left"></i>',
            nextArrow: '<i class="fa fa-long-arrow-right"></i>',
            responsive: [{
                breakpoint: 1024,
                settings: {
                    slidesToShow: 2,
                    slidesToScroll: 2
                }
            },
            {
                breakpoint: 600,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 2
                }
            },
            {
                breakpoint: 480,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1
                }
            }
            ]
        });
    }

    // Responsive Menu
    $('.menu-button').click(function () {
        $('.mobile-menu').toggleClass('active');
        $('.mobile-menu').toggle();
        $('.menu-button').toggleClass('open');
    });
    $('.menu-item-has-children').click(function () {
        $(this).toggleClass('active');
    });

    $(".accordion_list > li:first-child").addClass("active");
    $(".accordion_list > li:first-child.active").find(".answer").show();
    $(".accordion_list > li:first-child.active").find(".answer").slideDown();
    $('.accordion_list > li > .question').click(function () {
        if ($(this).parents('.accordion_list > li').hasClass("active")) {
            $(this).parents('.accordion_list > li').removeClass("active").find(".answer").slideUp();
            $('.accordion_list > li:first-child > .answer').slideUp();
        } else {
            $(".accordion_list > li.active .answer").slideUp();
            $(".accordion_list > li.active").removeClass("active");
            $(this).parents('.accordion_list > li').addClass("active").find(".answer").slideDown();
        }
        return false;
    });

    // Jumplinks Active
    $('.jumplinks').find('ul li').on('click', function () {
        $(this).siblings().removeClass('active').end().toggleClass('active');
    });

    // GF 1
    $(document).on('gform_confirmation_loaded', function (event, formId) {
        if (formId == 1) {
            $('.enquire_form a.btn').hide();
        }
    });

    $(document).on('click', '.search > a , .mob-search > a', function (e) {
        e.preventDefault();
        $('.searchbox').addClass('active');
    })

    $(document).on('click', '.searchbox .close', function (e) {
        e.preventDefault();
        $('.searchbox').removeClass('active');
    });

    // Mobile Menu
    $('.menu-humberger').on('click', function () {

        if ($(this).hasClass('active')) {
            $(this).removeClass('active');
            $(this).html('<i class="fa fa-bars" aria-hidden="true"></i>');
        } else {
            $(this).addClass('active');
            $(this).html('<i class="fa fa-times" aria-hidden="true"></i>');
        }
        $('.mobile-menu').slideToggle();
    });

    // Child Age Work
    $(document).on('change', '#input_1_10, #input_4_10 ', function (e) {
        console.log( 'change child' );
        let noOfChilds = $(this).val();
        if (noOfChilds == '')
            return;

        if (noOfChilds == '0') {
            $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {
                let val = $(value).val();
                let child = $(value).data('child');
                $('#gform_1 .' + child + ' input').val(val);
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
            $('#gform_1 .' + child + ' input').val(val);
            $('#gform_4 .' + child + ' input').val(val);
        });
        $('section.child_age').removeClass('active');
    });

    //Smooth Scroll
    $('a[href*="#"]').on('click', function (e) {
        if ($($(this).attr('href')).offset() != undefined) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $($(this).attr('href')).offset().top - 140
            }, 2000)
        }
    });

    function onLoadScroll() {
        // target element id
        let id = window.location.href;
        let indexOf = id.indexOf("#");
        if (indexOf < 0)
            return;
        id = id.substr(indexOf, id.length - 1);
        // target element
        let $id = $(document).find(id);
        if ($id.length === 0) {
            return;
        }
        // top position relative to the document
        let pos = $id.offset().top - 150;
        // animated top scrolling
        $('body, html').animate({
            scrollTop: pos
        });
    }

    setTimeout(function () {
        onLoadScroll();
    }, 1000);


    $('.cwvp_fancybox').fancybox({
        buttons: [
            'share',
            'fullScreen',
            'close'
        ]
    });

    if ($(".content_with_video_popup").length > 0) {
        $(".content_with_video_popup").each(function (i) {
            $(this).removeAttr('id').attr('id', 'videoid_' + i);
        });
    }

    $(document).on('click', 'a[href="#top_sec"]', function (e) {
        if ($('#top_sec').length < 1) {
            e.preventDefault();
            window.location.replace("https://japanskiexperience.com/get-a-quote/");
        }
    });

    // $(document).on('click', '.quote_form', function(){
    //     $('.enquire_form').css({"visibility": "visible", "opacity": "1"});
    //     $('.enquire_form').addClass('active');
    // });
    // $(document).on('click', '.enquire_close', function(){
    //     $('.enquire_form ').css({"visibility": "hidden", "opacity": "0"});
    //     $('.enquire_form').removeClass('active');
    // });
    $(document).on('click', '.form_toggle', function () {
        $('.enquire_form').slideToggle();
    });

    $(document).on('click', '.close_pop', function () {
        $('.site_popup').removeClass('active');
    });

    // Site Popup cookies and open popup script
    let sitepop = getCookie('site_pop_cookies');
    if (sitepop == '' || sitepop == undefined) {
        setTimeout(function() {
            $('.site_popup').addClass('active');
        }, 5000);
    }

    $(document).on('click', '.close_pop', function () {
        $('.site_popup').removeClass('active');
        setCookie('site_pop_cookies', 'site_pop', 1);
    });

    $(document).on("click", function (e) {
        const isClosest = e.target.closest('.site_popup')
        if (!isClosest && $('.site_popup').hasClass("active")) {
            $(".site_popup").removeClass('active')
            setCookie('site_pop_cookies', 'site_pop', 1)
        }
    });

    $(document).on('click', 'section.vote_for_us .vfu_inn a.vote_close', function () {
        var parent = $( this ).parents('section.vote_for_us');
        parent.hide();
    });

});