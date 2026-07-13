jQuery(document).ready(function ($) {



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

    // GF INIT

    // ---------------------------

    $(document).on('gform_post_render', function (event, form_id) {



        if (parseInt(form_id) !== FORM_ID) return;



        const $pop = $('#eq-guests-popover');

        if (!$pop.length) return;



        updateSummaryLabel($pop);

    });



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