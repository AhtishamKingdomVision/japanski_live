jQuery(document).ready(function ($) {



    // inject close button styles once

    if (!$('#eq-pop-close-style').length) {

        $('<style id="eq-pop-close-style">' +

            '.eq-guests-popover { position: relative; }' +

            '.eq-pop-close {' +

                'position: absolute; top: 8px; right: 10px;' +

                'background: none; border: none; cursor: pointer;' +

                'font-size: 18px; line-height: 1; color: #555; padding: 0;' +

            '}' +

            '.eq-pop-close:hover { color: #000; }' +

        '</style>').appendTo('head');

    }



    const FORM_ID = 1;



    const guestMapping = {

        adults:   { input: '.rec_adults select',  min: 1, max: 30 },

        children: { input: '.rec_children select', min: 0, max: 15 },

        infants:  { input: '.rec_infants select',  min: 0, max: 15 }

    };



    // ---------------------------

    // SUMMARY

    // ---------------------------

    function updateSummaryLabel($pop) {



        const adults   = parseInt($pop.find('.eq-adults').val()) || 0;

        const children = parseInt($pop.find('.eq-children').val()) || 0;

        const infants  = parseInt($pop.find('.eq-infants').val()) || 0;



        const total = adults + children;



        let label = total > 0

            ? `${total} Guest${total !== 1 ? 's' : ''}`

            : 'Guests';



        if (infants > 0) {

            label += `, ${infants} Infant${infants !== 1 ? 's' : ''}`;

        }



        $('.eq-sb-guests-display').text(label);

    }



    // ---------------------------

    // CORE UPDATE

    // ---------------------------

    function setValue(type, $input, value) {



        const config = guestMapping[type];

        const $row = $input.closest('.g-counter');

        const $pop = $input.closest('#eq-guests-popover');



        let val = parseInt(value) || config.min;



        if (val < config.min) val = config.min;

        if (val > config.max) val = config.max;



        $input.val(val);



        // sync GF

        $(config.input).val(val).trigger('change');



        updateSummaryLabel($pop);

    }



    // ---------------------------

    // INPUT HANDLER

    // ---------------------------

    $(document).on('input change', '#eq-guests-popover .g-val', function () {



        const $input = $(this);



        let type =

            $input.hasClass('eq-adults') ? 'adults' :

            $input.hasClass('eq-children') ? 'children' :

            'infants';



        setValue(type, $input, $input.val());

    });



    // ---------------------------

    // KEYBOARD STEP CONTROL (UP/DOWN)

    // ---------------------------

    $(document).on('keydown', '#eq-guests-popover .g-val', function (e) {



        const $input = $(this);



        let type =

            $input.hasClass('eq-adults') ? 'adults' :

            $input.hasClass('eq-children') ? 'children' :

            'infants';



        const config = guestMapping[type];



        let current = parseInt($input.val()) || config.min;



        if (e.key === 'ArrowUp') {

            e.preventDefault();

            setValue(type, $input, current + 1);

        }



        if (e.key === 'ArrowDown') {

            e.preventDefault();

            setValue(type, $input, current - 1);

        }

    });



    // ---------------------------

    // INJECT CLOSE BUTTON

    // ---------------------------

    function injectCloseButton() {

        $('.eq-guests-popover').each(function () {

            if (!$(this).find('.eq-pop-close').length) {

                $(this).prepend('<button type="button" class="eq-pop-close" aria-label="Close">&times;</button>');

            }

        });

    }



    // ---------------------------

    // CLOSE BUTTON CLICK

    // ---------------------------

    $(document).on('click', '.eq-pop-close', function (e) {

        e.stopPropagation();

        $(this).closest('.eq-guests-popover').removeClass('open');

    });



    // ---------------------------

    // GF INIT

    // ---------------------------

    $(document).on('gform_post_render', function (event, form_id) {



        if (parseInt(form_id) !== FORM_ID) return;



        const $pop = $('.eq-guests-popover');

        if (!$pop.length) return;

        injectCloseButton();

        updateSummaryLabel($pop);

    });



    // run on DOM ready too (for non-ajax GF renders)

    injectCloseButton();



    // ---------------------------

    // OPEN POPUP

    // ---------------------------

    $(document).on('click', '.eq-sb-guests-display', function (e) {

        console.log('click');
        e.preventDefault();
        let parent = $(this).parents('.eq_guests'),
            popover = parent.find('#eq-guests-popover');

        console.log(parent);
        console.log(popover);
        
        popover.addClass('open');

    });



    // ---------------------------

    // CLOSE POPUP

    // ---------------------------

    $(document).on('click', function (e) {



        if (

            $(e.target).closest('#eq-guests-popover').length ||

            $(e.target).closest('.eq-sb-guests-display').length

        ) {

            return;

        }



        $('#eq-guests-popover').removeClass('open');

    });



});