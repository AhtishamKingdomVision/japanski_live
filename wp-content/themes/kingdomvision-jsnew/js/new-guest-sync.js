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

    // Class-only GF selectors — duplicate #input_1_* IDs exist across modal + listing forms
    const guestMapping = {
        adults:   { input: '.rec_adults select',  min: 1, max: 30 },
        children: { input: '.rec_children select', min: 0, max: 15 },
        infants:  { input: '.rec_infants select',  min: 0, max: 15 }
    };

    function getFormScope($el) {
        const $scope = $el.closest('.gform_wrapper, .mob_quote_form1, .Enquiry-modal-content, .acc_enquiry_form');
        return $scope.length ? $scope : $();
    }

    function getPopover($el) {
        // Prefer class; id is duplicated when both forms are on the page
        const $pop = $el.closest('.eq-guests-popover');
        return $pop.length ? $pop : $el.closest('#eq-guests-popover');
    }

    function normalizeGuests(state) {
        state = state || {};

        let adults = parseInt(state.adults, 10);
        let children = parseInt(state.children, 10);

        if (isNaN(adults)) adults = parseInt(localStorage.getItem('sb_adults'), 10);
        if (isNaN(children)) children = parseInt(localStorage.getItem('sb_children'), 10);

        return {
            adults: isNaN(adults) || adults < 1 ? 2 : Math.min(30, adults),
            children: isNaN(children) || children < 0 ? 0 : Math.min(15, children),
            infants: 0
        };
    }

    function enquiryLabel(guests) {
        const total = guests.adults + guests.children;
        return total > 0
            ? `${total} Guest${total !== 1 ? 's' : ''}`
            : 'Guests';
    }

    function searchLabel(guests) {
        return `${guests.adults + guests.children} Guest${guests.adults + guests.children !== 1 ? 's' : ''}`;
    }

    function writeEnquiryGuests(guests, $triggerScope, changedType) {
        $('.eq-guests-popover').each(function () {
            const $pop = $(this);
            const $scope = getFormScope($pop);
            const shouldTrigger = $triggerScope && $scope.length && $scope[0] === $triggerScope[0];
            const $childrenInput = $scope.length ? $scope.find(guestMapping.children.input).first() : $();
            const previousChildren = $childrenInput.length
                ? (parseInt($childrenInput.val(), 10) || 0)
                : (parseInt($pop.find('.eq-children').val(), 10) || 0);
            const childrenIncreased = guests.children > previousChildren;

            $pop.find('.eq-adults').val(guests.adults);
            $pop.find('.eq-children').val(guests.children);
            $pop.find('.eq-infants').val(0);
            $pop.find('.eq-infants').closest('.g-row').hide();

            if ($scope.length) {
                $scope.find(guestMapping.adults.input).first().val(String(guests.adults)).trigger('change');
                $childrenInput.val(String(guests.children));
                $scope.find(guestMapping.infants.input).first().val('0');
                $scope.find('.rec_infants').closest('.gfield, .gfield_html, li, .guest_search').hide();

                // Age popup only when children count increases.
                if (shouldTrigger && changedType === 'children' && childrenIncreased && guests.children > 0) {
                    $childrenInput.trigger('change');
                } else if (typeof window.kvWriteStoredChildAgesToScope === 'function') {
                    window.kvWriteStoredChildAgesToScope($scope, guests.children);
                }

                $scope.find('.eq-sb-guests-display').text(enquiryLabel(guests));
            } else {
                $pop.closest('.eq_guests').find('.eq-sb-guests-display').text(enquiryLabel(guests));
            }
        });
    }

    function writeSearchGuests(guests) {
        const label = searchLabel(guests);

        $('.search-card.js-search-card').each(function () {
            const $card = $(this);

            $card.find('.js-v-adults').text(guests.adults);
            $card.find('.js-v-children').text(guests.children);
            $card.find('.js-v-infants').text(guests.infants);

            $card.find('.js-m-adults').val(guests.adults);
            $card.find('.js-m-children').val(guests.children);
            $card.find('.js-m-infants').val(guests.infants);

            $card.find('.js-sb-guests-display, .js-guests-display').html(label).removeClass('empty');
            $card.find('.js-btn-adults-minus').prop('disabled', guests.adults <= 1);
            $card.find('.js-btn-children-minus').prop('disabled', guests.children <= 0);
            $card.find('.js-btn-infants-minus').prop('disabled', guests.infants <= 0);
        });

        // Accommodation room-listing / change-guests popup
        $('.room-filter-guests-popover').each(function () {
            const $pop = $(this);
            $pop.find('.js-v-adults').text(guests.adults);
            $pop.find('.js-v-children').text(guests.children);
            $pop.find('.js-btn-adults-minus').prop('disabled', guests.adults <= 1);
            $pop.find('.js-btn-children-minus').prop('disabled', guests.children <= 0);
        });
        $('.sv-guests').val(label).removeClass('empty');
    }

    window.kvApplySharedGuests = function (state, options) {
        options = options || {};
        const guests = normalizeGuests(state);
        const $triggerScope = options.$triggerScope || $();

        localStorage.setItem('sb_adults', String(guests.adults));
        localStorage.setItem('sb_children', String(guests.children));
        localStorage.setItem('sb_infants', '0');

        writeEnquiryGuests(guests, $triggerScope, options.changedType || '');

        if (!options.skipSearch) {
            writeSearchGuests(guests);
        }
    };

    window.kvRefreshEnquiryGuestLabels = function () {
        window.kvApplySharedGuests(normalizeGuests(), { skipSearch: false });
    };

    // ---------------------------
    // CORE UPDATE — shared across search + both enquiry forms
    // ---------------------------
    function setValue(type, $input, value) {
        const config = guestMapping[type];
        if (!config) return;

        const $pop = getPopover($input);
        const $scope = getFormScope($input);
        const currentGuests = normalizeGuests({
            adults: $pop.find('.eq-adults').val(),
            children: $pop.find('.eq-children').val(),
            infants: $pop.find('.eq-infants').val()
        });

        let val = parseInt(value, 10);
        if (isNaN(val)) val = config.min;
        if (val < config.min) val = config.min;
        if (val > config.max) val = config.max;

        currentGuests[type] = val;

        window.kvApplySharedGuests(currentGuests, {
            $triggerScope: $scope,
            changedType: type
        });
    }

    // ---------------------------
    // INPUT HANDLER
    // ---------------------------
    $(document).on('input change', '.eq-guests-popover .g-val, #eq-guests-popover .g-val', function () {
        const $input = $(this);

        let type =
            $input.hasClass('eq-adults') ? 'adults' :
            $input.hasClass('eq-children') ? 'children' :
            null;

        if (!type) return;

        setValue(type, $input, $input.val());
    });

    // ---------------------------
    // KEYBOARD STEP CONTROL (UP/DOWN)
    // ---------------------------
    $(document).on('keydown', '.eq-guests-popover .g-val, #eq-guests-popover .g-val', function (e) {
        const $input = $(this);

        let type =
            $input.hasClass('eq-adults') ? 'adults' :
            $input.hasClass('eq-children') ? 'children' :
            null;

        if (!type) return;

        const config = guestMapping[type];
        let current = parseInt($input.val(), 10);
        if (isNaN(current)) current = config.min;

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

    $(document).on('click', '.eq-pop-close', function (e) {
        e.stopPropagation();
        $(this).closest('.eq-guests-popover').removeClass('open');
    });

    // ---------------------------
    // GF INIT
    // ---------------------------
    $(document).on('gform_post_render', function (event, form_id) {
        if (parseInt(form_id, 10) !== FORM_ID) return;

        if (!$('.eq-guests-popover').length) return;

        injectCloseButton();
        window.kvRefreshEnquiryGuestLabels();
    });

    injectCloseButton();
    if (typeof window.kvRefreshEnquiryGuestLabels === 'function') {
        window.kvRefreshEnquiryGuestLabels();
    }

    // ---------------------------
    // OPEN POPUP — only the clicked form's popover
    // ---------------------------
    $(document).on('click', '.eq-sb-guests-display', function (e) {
        e.preventDefault();

        if ($(this).closest('.bbf-fields-locked').length || $(this).attr('aria-disabled') === 'true') {
            return;
        }

        const parent = $(this).closest('.eq_guests');
        const popover = parent.find('.eq-guests-popover').first();

        $('.eq-guests-popover').not(popover).removeClass('open');
        popover.addClass('open');
    });

    // ---------------------------
    // CLOSE POPUP
    // ---------------------------
    $(document).on('click', function (e) {
        if (
            $(e.target).closest('.eq-guests-popover').length ||
            $(e.target).closest('.eq-sb-guests-display').length
        ) {
            return;
        }

        $('.eq-guests-popover').removeClass('open');
    });

});
