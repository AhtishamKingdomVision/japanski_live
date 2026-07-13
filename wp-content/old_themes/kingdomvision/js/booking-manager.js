/**
 * Booking Manager - Modular booking system for reusable cart and payment handling
 * Features: Cart management, DOM rendering, payment scheduling, currency formatting
 */

const BookingManager = (function() {
    'use strict';

    // ============================================================================
    // CONFIGURATION OBJECT - Centralize all settings
    // ============================================================================
    const CONFIG = {
        storage: {
            cartKey: 'rb_cart',
            checkinKey: 'niseko_checkin',
            checkoutKey: 'niseko_checkout'
        },
        payment: {
            depositPercentage: 0.25,
            defaultPaymentRequired: true,
            balanceDueDaysBefore: 30
        },
        currency: {
            locale: 'ja-JP',
            code: 'JPY',
            symbol: '¥'
        },
        selectors: {
            bookingContainer: '#booking-container',
            roomCardTemplate: '#rb-room-card-template',
            summary: {
                bookings: '#summary-bookings',
                guests: '#summary-guests',
                checkin: '#summary-checkin',
                checkout: '#summary-checkout',
                duration: '#summary-duration',
                totalPrice: '.room-total-price',
                totalDeposit: '#summary-total-deposit',
                totalBalance: '#summary-total-balance'
            },
            payment: {
                fullPaymentRequired: '#full-payment-required',
                fullPaymentAmount: '#full-payment-amount',
                depositSchedule: '#deposit-schedule',
                depositAmount: '#deposit-amount',
                balanceAmount: '#balance-amount',
                balanceDueDate: '#balance-due-date',
                fullPaymentDeadline: '#full-payment-deadline'
            },
            dates: {
                wrapper: '.booking_dates',
                checkinDisplay: '.check-in p',
                checkoutDisplay: '.check-out p'
            }
        },
        ajax: {
            action: 'fetch_room_details',
            paramName: 'room_tid'
        }
    };

    // ============================================================================
    // DOM CACHE - Avoid repeated DOM queries
    // ============================================================================
    const DOMCache = {
        elements: {},
        
        init: function() {
            // Cache common elements
            this.elements.bookingContainer = document.querySelector(CONFIG.selectors.bookingContainer);
            this.elements.summaryBookings = document.querySelector(CONFIG.selectors.summary.bookings);
            this.elements.summaryGuests = document.querySelector(CONFIG.selectors.summary.guests);
            this.elements.summaryCheckin = document.querySelector(CONFIG.selectors.summary.checkin);
            this.elements.summaryCheckout = document.querySelector(CONFIG.selectors.summary.checkout);
            this.elements.summaryDuration = document.querySelector(CONFIG.selectors.summary.duration);
            this.elements.totalPrice = document.querySelector(CONFIG.selectors.summary.totalPrice);
            this.elements.totalDeposit = document.querySelector(CONFIG.selectors.summary.totalDeposit);
            this.elements.totalBalance = document.querySelector(CONFIG.selectors.summary.totalBalance);
            
            return !!this.elements.bookingContainer;
        },
        
        get: function(selector) {
            return document.querySelector(selector);
        },
        
        getAll: function(selector) {
            return document.querySelectorAll(selector);
        }
    };

    // ============================================================================
    // STORAGE UTILITIES - Cart & localStorage Management
    // ============================================================================
    const Storage = {
        getCart: function() {
            try {
                const data = localStorage.getItem(CONFIG.storage.cartKey);
                // Handle both { items: [] } and direct array formats
                const parsed = data ? JSON.parse(data) : null;
                if (Array.isArray(parsed)) {
                    return { items: parsed };
                }
                return parsed && parsed.items ? parsed : { items: [] };
            } catch (e) {
                console.error('Failed to parse cart data:', e);
                return { items: [] };
            }
        },

        setCart: function(cart) {
            try {
                localStorage.setItem(CONFIG.storage.cartKey, JSON.stringify(cart));
                return true;
            } catch (e) {
                console.error('Failed to save cart data:', e);
                return false;
            }
        },

        getCheckin: function() {
            return localStorage.getItem(CONFIG.storage.checkinKey) || '';
        },

        getCheckout: function() {
            return localStorage.getItem(CONFIG.storage.checkoutKey) || '';
        },

        removeItemByIndex: function(index) {
            const cart = this.getCart();
            if (cart.items && typeof cart.items[index] !== 'undefined') {
                cart.items.splice(index, 1);
                return this.setCart(cart);
            }
            return false;
        }
    };

    // ============================================================================
    // FORMATTING UTILITIES
    // ============================================================================
    const Formatter = {
        toCurrency: function(amount) {
            return new Intl.NumberFormat(CONFIG.currency.locale, {
                style: 'currency',
                currency: CONFIG.currency.code,
                maximumFractionDigits: 0
            }).format(amount);
        },

        parseDate: function(dateString) {
            // Expected format: "10/04/2026" (DD/MM/YYYY)
            if (!dateString) return null;
            const parts = dateString.split('/');
            if (parts.length !== 3) return null;
            return new Date(parts[2], parts[1] - 1, parts[0]);
        },

        formatDateGB: function(date) {
            return date?.toLocaleDateString('en-GB') || '';
        },

        formatDateUS: function(date, options = {}) {
            const defaultOptions = {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            };
            return date?.toLocaleDateString('en-US', { ...defaultOptions, ...options }) || '';
        },

        calculateDurationDays: function(durationMs) {
            return Math.floor(durationMs / (1000 * 60 * 60 * 24));
        }
    };

    // ============================================================================
    // AJAX UTILITIES - Centralized AJAX calls with error handling
    // ============================================================================
    const Ajax = {
        fetchRoomDetails: function(roomTypeId, propertyId) {
            return new Promise((resolve, reject) => {
                if (typeof kv_object === 'undefined' || !kv_object.ajaxurl) {
                    reject(new Error('AJAX URL not configured'));
                    return;
                }

                jQuery.ajax({
                    url: kv_object.ajaxurl,
                    method: 'POST',
                    data: {
                        action: CONFIG.ajax.action,
                        [CONFIG.ajax.paramName]: roomTypeId,
                        property_id: propertyId
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            resolve(response.data);
                        } else {
                            reject(new Error('Invalid response from server'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        reject(new Error(`AJAX request failed: ${status}`));
                    }
                });
            });
        }
    };

    // ============================================================================
    // ROOM RENDERING - Build room cards with cart widget design
    // ============================================================================
    const RoomRenderer = {

        /**
         * Render the right-column Booking Details widget.
         * Matches the reference image design:
         *   - Property header (image + name + "View Details")
         *   - Booking Ref
         *   - Check In / Check Out dates + times
         *   - Room types listed
         *   - Guest counts (Adults, Children, Infants, Total Pax)
         *   - Booking Summary (property name, deposit, remaining, total)
         */
        renderRightWidget: function(cart) {
            const widget = document.getElementById('rb-booking-details-widget');
            if (!widget) return;

            if (!cart?.items?.length) {
                widget.innerHTML = '<p style="padding: 20px; text-align: center; color: #bfcfdf;">No booking data found.</p>';
                return;
            }

            const items = cart.items;
            const first = items[0];

            // --- Property Header ---
            const pImg = first.property_image
                ? `<img src="${first.property_image}" alt="${first.property_name || ''}" class="rb-widget-property-img">`
                : `<div class="rb-widget-property-img rb-widget-property-img--placeholder"></div>`;

            const propertyLink = first.hotel_type_id
                ? `<a href="javascript:;" class="rb-widget-view-details rb-widget-view-accommodation-details" data-property-id="${first.hotel_type_id}"><i class="fa-regular fa-eye"></i> View Details</a>`
                : '';

            let html = `<div class="rb-widget-property-header">
                <div class="rb-widget-property-header-top">
                    ${pImg}
                    <div class="rb-widget-property-meta">
                        <div class="rb-widget-property-label">Resort</div>
                        <div class="rb-widget-property-name">${first.property_name || '–'}</div>
                        ${propertyLink}
                    </div>
                </div>
            </div>`;

            // --- Booking Ref ---
            html += `<div class="rb-widget-section rb-widget-divider">
                <div class="rb-widget-meta-row">
                    <span class="rb-widget-meta-label">Booking Ref:</span>
                    <span class="rb-widget-meta-value rb-widget-na" id="rb-widget-booking-ref">Pending</span>
                </div>
            </div>`;

            // --- Dates: earliest check-in and latest check-out across all items ---
            let earliestCheckin  = null;
            let latestCheckout   = null;
            let checkinDisplay   = '–';
            let checkoutDisplay  = '–';
            let checkinTime      = '15:00';
            let checkoutTime     = '11:00';

            items.forEach((it, idx) => {
                const ciRaw = it.dates?.check_in  || '';
                const coRaw = it.dates?.check_out || '';
                if (ciRaw) {
                    const d = new Date(ciRaw);
                    if (!earliestCheckin || d < earliestCheckin) {
                        earliestCheckin = d;
                        checkinDisplay  = it.dates?.checkinDisplay  || ciRaw;
                        checkinTime     = it.dates?.checkinTime || '15:00';
                    }
                }
                if (coRaw) {
                    const d = new Date(coRaw);
                    if (!latestCheckout || d > latestCheckout) {
                        latestCheckout  = d;
                        checkoutDisplay = it.dates?.checkoutDisplay || coRaw;
                        checkoutTime    = it.dates?.checkoutTime || '11:00';
                    }
                }
            });

            html += `<div class="rb-widget-section rb-widget-divider rb-widget-dates-row">
                <div class="rb-widget-date-col">
                    <div class="rb-widget-date-label">Check In</div>
                    <div class="rb-widget-date-value">${checkinDisplay}</div>
                    <!-- <div class="rb-widget-date-time">From ${checkinTime}</div> -->
                </div>
                <div class="rb-widget-date-col">
                    <div class="rb-widget-date-label">Check Out</div>
                    <div class="rb-widget-date-value">${checkoutDisplay}</div>
                    <!-- <div class="rb-widget-date-time">Until ${checkoutTime}</div> -->
                </div>
            </div>`;

            // --- Room Types (with per-room dates and guests when multiple rooms) ---
            const multiRoom = items.length > 1;

            items.forEach((it, idx) => {
                const roomLink = it.room_url
                    ? `<a href="${it.room_url}" class="rb-widget-view-details" target="_blank"><i class="fa-regular fa-eye"></i> View Details</a>`
                    : '';

                let extraHtml = '';
                if (multiRoom) {
                    const ciDisplay = it.dates?.checkinDisplay  || it.dates?.check_in  || '–';
                    const coDisplay = it.dates?.checkoutDisplay || it.dates?.check_out || '–';

                    const adults   = Number(it.guests?.adults   || 0);
                    const children = Number(it.guests?.children || 0);
                    const infants  = Number(it.guests?.infants  || 0);
                    const pax      = adults + children + infants;

                    let guestParts = [];
                    if (adults)   guestParts.push(`${adults} Adult${adults   !== 1 ? 's' : ''}`);
                    if (children) guestParts.push(`${children} Child${children !== 1 ? 'ren' : ''}`);
                    if (infants)  guestParts.push(`${infants} Infant${infants  !== 1 ? 's' : ''}`);
                    const guestSummary = guestParts.length ? guestParts.join(', ') : '–';

                    extraHtml = `
                    <div class="rb-widget-room-dates">
                        <div class="rb-widget-room-date-col">
                            <div class="rb-widget-date-label">Check In</div>
                            <div class="rb-widget-room-date-value">${ciDisplay}</div>
                        </div>
                        <div class="rb-widget-room-date-col">
                            <div class="rb-widget-date-label">Check Out</div>
                            <div class="rb-widget-room-date-value">${coDisplay}</div>
                        </div>
                    </div>
                    <div class="rb-widget-room-guests">
                        <span class="rb-widget-date-label">Guests:</span>
                        <span class="rb-widget-room-guest-value">${guestSummary}</span>
                        <span class="rb-widget-room-pax">(${pax} ${pax > 1 ? 'guests' : 'guest'})</span>
                    </div>`;
                }

                const viewDetailsLink = (it.room_type_id && it.hotel_type_id)
                    ? `<a href="javascript:;" class="rb-widget-view-room-details" data-room-id="${it.room_type_id}" data-property-id="${it.hotel_type_id}"><i class="fa-regular fa-eye"></i> View Details</a>`
                    : '';

                html += `<div class="rb-widget-section rb-widget-divider rb-widget-room-block">
                    <div class="rb-widget-room-header">
                        <div>
                            <div class="rb-widget-room-label">Room Type:</div>
                            <div class="rb-widget-room-name">${it.room_name || '–'}</div>
                            ${viewDetailsLink}
                            <a href="javascript:;" class="rb-widget-remove-item" data-idx="${idx}" title="Remove room" aria-label="Remove room"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </div>
                    ${extraHtml}
                </div>`;
            });

            // --- Guest Counts: group by date range, sum per group, display highest ---
            const _guestsByDateRange = {};
            items.forEach(it => {
                const key = (it.dates?.check_in || '') + '|' + (it.dates?.check_out || '');
                if (!_guestsByDateRange[key]) _guestsByDateRange[key] = { adults: 0, children: 0, infants: 0 };
                _guestsByDateRange[key].adults   += Number(it.guests?.adults   || 0);
                _guestsByDateRange[key].children += Number(it.guests?.children || 0);
                _guestsByDateRange[key].infants  += Number(it.guests?.infants  || 0);
            });
            const _groups = Object.values(_guestsByDateRange);
            const totalAdults   = _groups.length ? Math.max(..._groups.map(g => g.adults))   : 0;
            const totalChildren = _groups.length ? Math.max(..._groups.map(g => g.children)) : 0;
            const totalInfants  = _groups.length ? Math.max(..._groups.map(g => g.infants))  : 0;
            const totalPax      = _groups.length ? Math.max(..._groups.map(g => g.adults + g.children + g.infants)) : 0;

            html += `<div class="rb-widget-section rb-widget-divider rb-widget-guests-grid">
                <div class="rb-widget-guest-cell">
                    <div class="rb-widget-guest-label">No. Adults</div>
                    <div class="rb-widget-guest-value">${totalAdults}</div>
                </div>
                <div class="rb-widget-guest-cell">
                    <div class="rb-widget-guest-label">No. Children</div>
                    <div class="rb-widget-guest-value">${totalChildren}</div>
                </div>
                <div class="rb-widget-guest-cell">
                    <div class="rb-widget-guest-label">No. Infants</div>
                    <div class="rb-widget-guest-value">${totalInfants}</div>
                </div>
                <div class="rb-widget-guest-cell">
                    <div class="rb-widget-guest-label">Total Guests</div>
                    <div class="rb-widget-guest-value">${totalPax}</div>
                </div>
            </div>`;

            widget.innerHTML = html;

            // --- Next Steps (dynamic based on property type) ---
            this.renderNextSteps(cart);

            // --- Booking Summary (separate card below widget) ---
            this.renderBookingSummaryCard(cart);
        },

        /**
         * Render the Next Steps section dynamically based on property type.
         * 1) RoomBoss – Reservation Access
         * 2) RoomBoss – Request Access
         * 3) BedBank Properties with Rates
         */
        renderNextSteps: function(cart) {
            const container = document.getElementById('rb-next-steps-container');
            if (!container) return;

            if (!cart?.items?.length) {
                container.style.display = 'none';
                container.innerHTML = '';
                return;
            }

            const firstItem = cart.items[0];
            const isBedBank = firstItem?.is_bedbank || firstItem?.isBedBank || false;
            const permission = (firstItem?.property_permission || firstItem?.accommodation_permission || '').toLowerCase();

            let heading = 'Next Steps:';
            let steps = [];

            if (isBedBank) {
                // BedBank flow
                heading = 'Please Note – Availability Still Needs to Be Confirmed';
                steps = [
                    'Submit your booking request below.',
                    `We will: <br>
                     &nbsp; &nbsp; - Reserve the accommodation and send a booking invoice if it is available.<br>
                     &nbsp; &nbsp; - If it is unavailable, we will recommend suitable alternatives.`,
                    // 'Recommend suitable alternatives if it is unavailable.',
                    'No payment or commitment is required at this stage.'
                ];
            } else if (permission === 'request') {
                // RoomBoss – Request Access
                steps = [
                    'Enter your details below, agree to the Terms &amp; Conditions, then click <strong>"Book Now and Pay"</strong>.',
                    'We will request confirmation from the supplier and send your booking confirmation as soon as it is received.',
                    'If your booking cannot be confirmed, your card will not be charged, and we will recommend suitable alternatives.'
                ];
            } else {
                // RoomBoss – Reservation Access (default)
                steps = [
                    'Enter your details below, agree to the Terms &amp; Conditions, then click <strong>"Book Now and Pay"</strong>.',
                    'We will process your payment and send your booking confirmation.'
                ];
            }

            const stepsHtml = steps.map((step, idx) => `<li>${step}</li>`).join('');

            container.innerHTML = `
                <div style="display: flex; align-items: flex-start; gap: 16px;">
                    <div style="flex-shrink: 0; width: 32px; height: 32px; border: 2px solid #dd363e; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #dd363e; font-weight: bold; font-size: 18px; margin-top: 2px;">i</div>
                    <div>
                        <h4 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 600; color: #fff;">${heading}</h4>
                        <ol style="margin: 0; padding-left: 20px; font-size: 15px; line-height: 1.6; color: #e8e0e6;">
                            ${stepsHtml}
                        </ol>
                    </div>
                </div>`;
            container.style.display = '';
        },

        /**
         * Render the Booking Summary card — separate box below the widget.
         * Shows: items count, property rows with deposit/remaining, total button.
         */
        renderBookingSummaryCard: function(cart) {
            // Append or find existing summary card container
            let summaryCard = document.getElementById('rb-booking-summary-card');
            if (!summaryCard) {
                summaryCard = document.createElement('div');
                summaryCard.id = 'rb-booking-summary-card';
                summaryCard.className = 'rb-summary-box rb-booking-summary-card';
                const widget = document.getElementById('rb-booking-details-widget');
                if (widget && widget.parentNode) {
                    widget.parentNode.insertBefore(summaryCard, widget.nextSibling);
                }
            }

            if (!cart?.items?.length) {
                summaryCard.innerHTML = '';
                return;
            }

            const items = cart.items;
            let grandTotal     = 0;
            let grandDeposit   = 0;
            let grandRemaining = 0;

            items.forEach(it => {
                const price    = Number(it.price || 0);
                const deposit  = Number(it.payment?.depositAmount    || 0);
                const balance  = Number(it.payment?.balanceDueAmount || (price - deposit));
                grandTotal     += price;
                grandDeposit   += deposit;
                grandRemaining += balance;
            });

            let html = `<div class="rb-booking-summary-header">
                <h3 class="rb-booking-summary-title">Booking Summary</h3>
                <span class="rb-booking-summary-count">${items.length} item${items.length !== 1 ? 's' : ''}</span>
            </div>`;

            if( items.length ) {
            html += `<div class="rb-bsummary-property-row">
                        <div class="rb-bsummary-property-info">
                            <span class="rb-bsummary-property-name">${items[0].property_name || ''}</span>
                            <span class="rb-bsummary-property-type">Accommodation</span>
                        </div>
                    </div>`;
            }

            // Property rows
            items.forEach((it) => {
                const price   = Number(it.price || 0);
                const deposit = Number(it.payment?.depositAmount    || 0);
                const balance = Number(it.payment?.balanceDueAmount || (price - deposit));
                const depositDueDate  = it.payment?.depositDueDate
                    ? new Date(it.payment.depositDueDate).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'})
                    : '';
                const balanceDueDate  = it.payment?.balanceDueDate
                    ? new Date(it.payment.balanceDueDate).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'})
                    : '';

                // const permissionLabel = it.permission_type || 'Accommodation';
                const permissionLabel = 'Room';

                if( items.length > 1 ) {
                    html += `<div class="rb-bsummary-property-row">
                        <div class="rb-bsummary-property-info">
                            <span class="rb-bsummary-property-name">${it.room_name || '–'}</span>
                            <span class="rb-bsummary-property-type">${permissionLabel}</span>
                        </div>`;
                }
                else {
                    html += `<div class="rb-bsummary-property-row">`;
                }
                
                html += `<div class="rb-bsummary-payment-lines">`;

                if (deposit > 0) {
                    html += `<div class="rb-bsummary-pay-line">
                        <div>
                            <span class="rb-bsummary-pay-label">Deposit</span>
                            <span class="rb-bsummary-pay-due">Due Now</span>
                        </div>
                        <span class="rb-bsummary-pay-amount">¥${deposit.toLocaleString('ja-JP')}</span>
                    </div>`;
                    if (balance > 0) {
                        html += `<div class="rb-bsummary-pay-line">
                            <div>
                                <span class="rb-bsummary-pay-label">Remaining</span>
                                ${balanceDueDate ? `<span class="rb-bsummary-pay-due">Due by ${balanceDueDate}</span>` : ''}
                            </div>
                            <span class="rb-bsummary-pay-amount">¥${balance.toLocaleString('ja-JP')}</span>
                        </div>`;
                    }
                } else {
                    html += `<div class="rb-bsummary-pay-line">
                        <div>
                            <span class="rb-bsummary-pay-label">Full Payment</span>
                            ${balanceDueDate ? `<span class="rb-bsummary-pay-due">Due by ${balanceDueDate}</span>` : ''}
                        </div>
                        <span class="rb-bsummary-pay-amount">¥${price.toLocaleString('ja-JP')}</span>
                    </div>`;
                }

                html += `</div></div>`;
            });

            html += `<div class="rb-bsummary-total-bar">
                <span>Total</span>
                <span>¥${grandTotal.toLocaleString('ja-JP')}</span>
            </div>`;

            summaryCard.innerHTML = html;
        },

        /**
         * Render the Accommodations table in #booking-container (left column).
         * Columns: Name | Check In | Check Out | Payment Type | Payable Amount | Due Date
         */
        renderAccommodationsTable: function(cart) {
            const container = DOMCache.elements.bookingContainer;
            if (!container) return;

            if (!cart?.items?.length) {
                container.innerHTML = '';
                return;
            }

            let rows = '';
            cart.items.forEach((it) => {
                const price   = Number(it.price || 0);
                const deposit = Number(it.payment?.depositAmount || 0);
                const balance = Number(it.payment?.balanceDueAmount || (price - deposit));

                const checkin  = it.dates?.checkinDisplay  || it.dates?.check_in  || '–';
                const checkout = it.dates?.checkoutDisplay || it.dates?.check_out || '–';

                const hasDeposit = deposit > 0;
                const paymentType   = hasDeposit ? 'Deposit'   : 'Full Payment';
                const payableAmount = hasDeposit ? deposit      : price;
                const dueDate       = it.payment?.depositDueDate || it.payment?.balanceDueDate || '';
                const dueDateFmt    = dueDate
                    ? new Date(dueDate).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'})
                    : '–';

                rows += `<tr>
                    <td class="rb-accom-td">${it.room_name || '–'}</td>
                    <td class="rb-accom-td">${checkin}</td>
                    <td class="rb-accom-td">${checkout}</td>
                    <td class="rb-accom-td">${paymentType}</td>
                    <td class="rb-accom-td rb-accom-amount">¥${payableAmount.toLocaleString('ja-JP')}</td>
                    <td class="rb-accom-td">${dueDateFmt}</td>
                </tr>`;
            });

            container.innerHTML = `<div class="rb-accom-table-wrap">
                <h3 class="rb-accom-title">Accommodations</h3>
                <div class="rb-accom-table-scroll">
                    <table class="rb-accom-table">
                        <thead>
                            <tr>
                                <th class="rb-accom-th">Name</th>
                                <th class="rb-accom-th">Check In</th>
                                <th class="rb-accom-th">Check Out</th>
                                <th class="rb-accom-th">Payment Type</th>
                                <th class="rb-accom-th">Payable Amount</th>
                                <th class="rb-accom-th">Due Date</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`;
        },

        renderCartWidget: function(cart) {
            this.renderRightWidget(cart);
            // this.renderAccommodationsTable(cart);
        },

        renderRooms: function(cart) {
            this.renderCartWidget(cart);
        }
    };

    // ============================================================================
    // SUMMARY UPDATES - Booking summary calculations (Right column)
    // ============================================================================
    const Summary = {
        updateDates: function(cart) {
            let checkin = Storage.getCheckin();
            let checkout = Storage.getCheckout();

            // Fall back to cart item dates if localStorage dates aren't available
            if (!checkin && cart?.items?.[0]?.dates?.checkinDisplay) {
                checkin = cart.items[0].dates.checkinDisplay;
            }
            if (!checkout && cart?.items?.[0]?.dates?.checkoutDisplay) {
                checkout = cart.items[0].dates.checkoutDisplay;
            }

            const datesWrapper = DOMCache.get(CONFIG.selectors.dates.wrapper);
            if (datesWrapper) {
                const checkinDisplay = datesWrapper.querySelector(CONFIG.selectors.dates.checkinDisplay);
                const checkoutDisplay = datesWrapper.querySelector(CONFIG.selectors.dates.checkoutDisplay);
                
                if (checkinDisplay) checkinDisplay.textContent = checkin || '–';
                if (checkoutDisplay) checkoutDisplay.textContent = checkout || '–';
            }
        },

        parseDisplayDate: function(displayDate) {
            // Parse formatted date like "19 Dec, 2026" to Date object
            if (!displayDate) return null;
            try {
                const date = new Date(displayDate);
                return isNaN(date.getTime()) ? null : date;
            } catch (e) {
                return null;
            }
        },

        parseDateString: function(dateStr) {
            // Parse ISO date format "YYYY-MM-DD" to Date object
            if (!dateStr) return null;
            try {
                const parts = dateStr.split('-');
                if (parts.length !== 3) return null;
                const date = new Date(parts[0], parts[1] - 1, parts[2]);
                return isNaN(date.getTime()) ? null : date;
            } catch (e) {
                return null;
            }
        },

        getMinDate: function(dates) {
            // Get the earliest date from array
            if (!dates || !dates.length) return null;
            const validDates = dates.filter(d => d !== null && !isNaN(d.getTime()));
            if (!validDates.length) return null;
            return new Date(Math.min(...validDates.map(d => d.getTime())));
        },

        getMaxDate: function(dates) {
            // Get the latest date from array
            if (!dates || !dates.length) return null;
            const validDates = dates.filter(d => d !== null && !isNaN(d.getTime()));
            if (!validDates.length) return null;
            return new Date(Math.max(...validDates.map(d => d.getTime())));
        },

        formatDisplayDate: function(date) {
            // Format date as "DD Mon, YYYY" (e.g., "19 Dec, 2026")
            if (!date || isNaN(date.getTime())) return '–';
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            return date.toLocaleDateString('en-GB', options);
        },

        calculateDurationDays: function(checkinDate, checkoutDate) {
            // Calculate number of nights between two dates
            if (!checkinDate || !checkoutDate) return 0;
            const timeMs = checkoutDate.getTime() - checkinDate.getTime();
            return Math.floor(timeMs / (1000 * 60 * 60 * 24));
        },

        updateMetadata: function(cart) {
            if (!cart?.items?.length) return;

            // Update booking count
            if (DOMCache.elements.summaryBookings) 
                DOMCache.elements.summaryBookings.textContent = cart.items.length;

            // Calculate maximum guests from all items (highest occupancy)
            let totalGuests = 0;
            let checkinDates = [];
            let checkoutDates = [];

            cart.items.forEach(item => {
                // Get maximum guest count from all rooms
                const adults = Number(item.guests?.adults || 0);
                const children = Number(item.guests?.children || 0);
                const infants = Number(item.guests?.infants || 0);
                const roomGuests = adults + children + infants;
                totalGuests = Math.max(totalGuests, roomGuests);

                // Collect check-in dates
                if (item.dates?.check_in) {
                    const date = this.parseDateString(item.dates.check_in);
                    if (date) checkinDates.push(date);
                }

                // Collect check-out dates
                if (item.dates?.check_out) {
                    const date = this.parseDateString(item.dates.check_out);
                    if (date) checkoutDates.push(date);
                }
            });

            // Update total guests display
            if (DOMCache.elements.summaryGuests) {
                DOMCache.elements.summaryGuests.textContent = totalGuests > 0 ? totalGuests + ' guest' + (totalGuests !== 1 ? 's' : '') : '–';
            }

            // Update check-in (minimum date)
            if (DOMCache.elements.summaryCheckin) {
                const minCheckin = this.getMinDate(checkinDates);
                DOMCache.elements.summaryCheckin.textContent = minCheckin ? this.formatDisplayDate(minCheckin) : '–';
            }

            // Update check-out (maximum date)
            if (DOMCache.elements.summaryCheckout) {
                const maxCheckout = this.getMaxDate(checkoutDates);
                DOMCache.elements.summaryCheckout.textContent = maxCheckout ? this.formatDisplayDate(maxCheckout) : '–';
            }

            // Update duration (nights between earliest check-in and latest check-out)
            if (DOMCache.elements.summaryDuration) {
                const minCheckin = this.getMinDate(checkinDates);
                const maxCheckout = this.getMaxDate(checkoutDates);
                if (minCheckin && maxCheckout) {
                    const nights = this.calculateDurationDays(minCheckin, maxCheckout);
                    DOMCache.elements.summaryDuration.textContent = nights > 0 ? nights + ' night' + (nights !== 1 ? 's' : '') : '–';
                } else {
                    DOMCache.elements.summaryDuration.textContent = '–';
                }
            }
        },

        calculateTotal: function(cart) {
            // Calculate from cart items directly
            let total = 0;
            if (cart?.items?.length) {
                total = cart.items.reduce((sum, item) => sum + (Number(item.price) || 0), 0);
            }

            if (DOMCache.elements.totalPrice) {
                DOMCache.elements.totalPrice.dataset.price = total;
                DOMCache.elements.totalPrice.textContent = Formatter.toCurrency(total);
            }

            return total;
        },

        updatePaymentBreakdown: function(cart) {
            // Calculate combined totals from all cart items
            let totalDeposit = 0;
            let totalBalance = 0;

            if (cart?.items?.length) {
                cart.items.forEach(item => {
                    const deposit = Number(item.payment?.depositAmount || 0);
                    const balance = Number(item.payment?.balanceDueAmount || 0);
                    totalDeposit += deposit;
                    totalBalance += balance;
                });
            }

            // Update total deposit element
            if (DOMCache.elements.totalDeposit) {
                DOMCache.elements.totalDeposit.textContent = Formatter.toCurrency(totalDeposit);
            }

            // Update total deposit display bar (new element above buttons)
            const totalDepositDisplay = document.getElementById('rb-total-deposit-display');
            if (totalDepositDisplay) {
                totalDepositDisplay.textContent = Formatter.toCurrency(totalDeposit);
            }

            // Update total balance element
            if (DOMCache.elements.totalBalance) {
                DOMCache.elements.totalBalance.textContent = Formatter.toCurrency(totalBalance);
            }
        },

        update: function(cart) {
            this.updateDates(cart);
            this.updateMetadata(cart);
            this.calculateTotal(cart);
            this.updatePaymentBreakdown(cart);
        }
    };

    // ============================================================================
    // PAYMENT SCHEDULE - Handle deposit/full payment logic
    // ============================================================================
    const PaymentSchedule = {
        update: function(cart) {
            const total = Summary.calculateTotal(cart);
            
            if (!total || total <= 0) {
                this.hide();
                return;
            }

            const firstItem = cart?.items?.[0];
            const paymentInfo = firstItem?.payment;

            // Use actual payment data from cart if available
            if (paymentInfo && paymentInfo.depositAmount !== undefined) {
                const deposit = Number(paymentInfo.depositAmount) || 0;
                const balance = Number(paymentInfo.balanceDueAmount) || 0;
                const isFullPayment = deposit >= total || balance === 0;

                if (isFullPayment) {
                    this.showFullPayment(total);
                } else {
                    this.showDepositSchedule(deposit, balance);
                }
            } else {
                // Fallback to percentage-based calculation
                const fullRequiredFlag = !!paymentInfo?.full_required;
                const depositPct = fullRequiredFlag ? 1 : CONFIG.payment.depositPercentage;
                
                const deposit = Math.round(total * depositPct);
                const balance = Math.max(0, total - deposit);
                const isFullPayment = deposit >= total || balance === 0;

                if (isFullPayment) {
                    this.showFullPayment(total);
                } else {
                    this.showDepositSchedule(deposit, balance);
                }
            }

            this.setFullPaymentDeadline();
        },

        showFullPayment: function(total) {
            const fullWrap = DOMCache.get(CONFIG.selectors.payment.fullPaymentRequired);
            const depositWrap = DOMCache.get(CONFIG.selectors.payment.depositSchedule);

            if (fullWrap) fullWrap.style.display = '';
            if (depositWrap) depositWrap.style.display = 'none';

            const amountEl = DOMCache.get(CONFIG.selectors.payment.fullPaymentAmount);
            if (amountEl) amountEl.textContent = Formatter.toCurrency(total);
        },

        showDepositSchedule: function(deposit, balance) {
            const fullWrap = DOMCache.get(CONFIG.selectors.payment.fullPaymentRequired);
            const depositWrap = DOMCache.get(CONFIG.selectors.payment.depositSchedule);

            if (fullWrap) fullWrap.style.display = 'none';
            if (depositWrap) depositWrap.style.display = '';

            const depositEl = DOMCache.get(CONFIG.selectors.payment.depositAmount);
            const balanceEl = DOMCache.get(CONFIG.selectors.payment.balanceAmount);

            if (depositEl) {
                depositEl.textContent = Formatter.toCurrency(deposit);
                depositEl.dataset.price = deposit;
            }

            if (balanceEl) {
                balanceEl.textContent = Formatter.toCurrency(balance);
                balanceEl.dataset.price = balance;
            }

            this.updateBalanceDueDate();
        },

        updateBalanceDueDate: function() {
            const dueDateEl = DOMCache.get(CONFIG.selectors.payment.balanceDueDate);
            if (!dueDateEl) return;

            // Try to get balance due date from cart first
            const cart = Storage.getCart();
            const balanceDueDate = cart?.items?.[0]?.payment?.balanceDueDate;

            if (balanceDueDate) {
                try {
                    const dueDate = new Date(balanceDueDate);
                    dueDateEl.textContent = `(Due by ${Formatter.formatDateGB(dueDate)})`;
                    return;
                } catch (e) {
                    console.warn('Could not parse balance due date:', balanceDueDate);
                }
            }

            const checkin = Storage.getCheckin();
            if (!checkin) {
                dueDateEl.textContent = '';
                return;
            }

            const checkinDate = Formatter.parseDate(checkin);
            if (!checkinDate) return;

            checkinDate.setDate(checkinDate.getDate() - CONFIG.payment.balanceDueDaysBefore);
            dueDateEl.textContent = `(Due by ${Formatter.formatDateGB(checkinDate)})`;
        },

        setFullPaymentDeadline: function() {
            const el = DOMCache.get(CONFIG.selectors.payment.fullPaymentDeadline);
            if (!el) return;

            // Try to get check-in from cart first
            const cart = Storage.getCart();
            let checkin = Storage.getCheckin();
            
            if (!checkin && cart?.items?.[0]?.dates?.check_in) {
                checkin = cart.items[0].dates.check_in;
            }

            if (!checkin) return;

            // If checkin is in ISO format (YYYY-MM-DD), parse it directly
            let checkinDate;
            if (checkin.includes('-')) {
                const parts = checkin.split('-');
                checkinDate = new Date(parts[0], parts[1] - 1, parts[2]);
            } else {
                checkinDate = Formatter.parseDate(checkin);
            }

            if (!checkinDate) return;

            checkinDate.setDate(checkinDate.getDate() - CONFIG.payment.balanceDueDaysBefore);
            el.textContent = Formatter.formatDateUS(checkinDate);
        },

        hide: function() {
            const fullWrap = DOMCache.get(CONFIG.selectors.payment.fullPaymentRequired);
            const depositWrap = DOMCache.get(CONFIG.selectors.payment.depositSchedule);
            
            if (fullWrap) fullWrap.style.display = 'none';
            if (depositWrap) depositWrap.style.display = 'none';
        }
    };

    // ============================================================================
    // EVENT HANDLERS
    // ============================================================================
    const EventHandlers = {
        init: function() {
            jQuery(document)
                .off('click.rbRemove')
                .on('click.rbRemove', '.rb-remove, .rb-widget-remove-item', this.onRemoveItem.bind(this))
                .off('click.rbTerms')
                .on('click.rbTerms', '.terms_popup', this.onTermsPopup.bind(this))
                .off('click.rbTermsHide')
                .on('click.rbTermsHide', '.terms-modal-close', this.onTermsPopupHIde.bind(this))
                .off('click.rbViewRoomDetails')
                .on('click.rbViewRoomDetails', '.rb-widget-view-room-details', this.onViewRoomDetails.bind(this))
                .off('click.rbViewAccommodationDetails')
                .on('click.rbViewAccommodationDetails', '.rb-widget-view-accommodation-details', this.onViewAccommodationDetails.bind(this));

            // Close modal on backdrop or close button click
            jQuery(document)
                .off('click.rbRoomModalClose')
                .on('click.rbRoomModalClose', '#rb-room-modal, .rb-room-modal-close', function(e) {
                    if (jQuery(e.target).is('#rb-room-modal') || jQuery(e.target).is('.rb-room-modal-close') || jQuery(e.target).closest('.rb-room-modal-close').length) {
                        jQuery('#rb-room-modal').parent('.room-listing').fadeOut(200);
                    }
                });
        },

        onRemoveItem: function(e) {
            if (!window.confirm('Are you sure you want to remove this room?')) {
                return;
            }

            const $target = jQuery(e.currentTarget);
            const dataIdx = $target.data('idx');
            const $item = jQuery(e.target).closest('.rb-room-card, .rb-summary-card');
            const idx = Number(typeof dataIdx !== 'undefined' ? dataIdx : $item.data('idx'));

            if (Number.isNaN(idx)) {
                return;
            }

            if (Storage.removeItemByIndex(idx)) {
                const cart = Storage.getCart();
                RoomRenderer.renderRooms(cart);
                PaymentSchedule.update(cart);
                document.dispatchEvent(new CustomEvent('rb:cart-updated', { detail: { cart: cart } }));
            }
        },

        onTermsPopup: function(e) {
            e.preventDefault();

            const cart = Storage.getCart();
            if (!cart || !cart.items || cart.items.length === 0) {
                alert('Please select a room first to view terms.');
                return;
            }

            // Extract hotel_type_id from the first item in the cart
            const propertyId = cart.items[0].hotel_type_id;
            const $modal = jQuery('#terms-modal');
            const $modalBody = jQuery('#terms-modal-body');

            if (!$modal.length) return;

            $modalBody.html('<div class="rb-loading">Loading Terms & Conditions…</div>');
            $modal.fadeIn(200);

            jQuery.ajax({
                url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                method: 'POST',
                data: {
                    action: 'load_property_terms',
                    property_id: propertyId
                },
                success: function(res) {
                    if (res.success) {
                        $modalBody.html(res.data.html || res.data);
                    } else {
                        $modalBody.html('<p class="rb-error">Terms and conditions could not be loaded for this property.</p>');
                    }
                },
                error: function() {
                    $modalBody.html('<p class="rb-error">Failed to load terms information.</p>');
                }
            });
        }, onTermsPopupHIde: function(e) {

            const $modal = jQuery('#terms-modal');
            $modal.fadeOut(200);

        },

        onViewRoomDetails: function(e) {
            e.preventDefault();

            const $link = jQuery(e.currentTarget);
            const roomId     = $link.data('room-id');
            const propertyId = $link.data('property-id');

            if (!roomId || !propertyId) return;

            // Ensure modal exists in DOM
            if (!jQuery('#rb-room-modal').length) {
                jQuery('body').append(`
                    <div id="rb-room-modal" class="room-modal" style="display:none;">
                        <div class="room-modal-content">
                            <button class="rb-room-modal-close room-modal-close">&times;</button>
                            <div id="rb-room-modal-body"></div>
                        </div>
                    </div>`);
            }

            const $modal     = jQuery('#rb-room-modal');
            const $modalBody = jQuery('#rb-room-modal-body');

            $modalBody.html('<div class="rb-loader"><div class="rb-spinner"></div><p>Loading room details…</p></div>');
            $modal.parent('.room-listing').fadeIn(200);

            const originalText = $link.html();
            $link.addClass('loading').html('<i class="fa-regular fa-eye"></i> Loading…');

            jQuery.ajax({
                url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                method: 'POST',
                data: {
                    action: 'niseko_load_room_details',
                    room_id: roomId,
                    hotel_id: propertyId,
                    page: 'booking'
                },
                success: function(res) {
                    if (res.success) {
                        $modalBody.html(res.data.html || res.data);
                        $modal.parent('.room-listing').fadeIn(200, function() {
                            // Init slick gallery if available
                            const $gallery = $modalBody.find('.js-room-gallery');
                            if ($gallery.length && typeof jQuery.fn.slick === 'function') {
                                if ($gallery.hasClass('slick-initialized')) $gallery.slick('unslick');
                                const $slides = $gallery.find('.room-slide');
                                if ($slides.length >= 3) {
                                    $gallery.slick({
                                        infinite: true,
                                        slidesToShow: 3,
                                        slidesToScroll: 1,
                                        arrows: true,
                                        dots: false,
                                        adaptiveHeight: true,
                                        prevArrow: '<button type="button" class="slick-prev"><img src="' + base_url + '/wp-content/themes/kingdomvision/images/left_arrow.svg" alt="Previous"></button>',
                                        nextArrow: '<button type="button" class="slick-next"><img src="' + base_url + '/wp-content/themes/kingdomvision/images/right_arrow.svg" alt="Next"></button>',
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
                                    window.dispatchEvent(new Event('resize'));
                                }
                            }
                        });
                    } else {
                        $modalBody.html('<p class="rb-error">Could not load room details.</p>');
                    }
                },
                error: function() {
                    $modalBody.html('<p class="rb-error">Failed to load room details.</p>');
                },
                complete: function() {
                    $link.removeClass('loading').html(originalText);
                }
            });
        },

        onViewAccommodationDetails: function(e) {
            e.preventDefault();

            const $link = jQuery(e.currentTarget);
            const propertyId = $link.data('property-id');

            if (!propertyId) return;

            // Ensure modal exists in DOM
            if (!jQuery('#rb-room-modal').length) {
                jQuery('body').append(`
                    <div id="rb-room-modal" class="room-modal" style="display:none;">
                        <div class="room-modal-content">
                            <button class="rb-room-modal-close room-modal-close">&times;</button>
                            <div id="rb-room-modal-body"></div>
                        </div>
                    </div>`);
            }

            // const $modal     = jQuery('#rb-room-modal');
            const $modal     = jQuery('.room-listing');
            const $modalBody = jQuery('#rb-room-modal-body');

            $modalBody.html('<div class="rb-loader"><div class="rb-spinner"></div><p>Loading accommodation details…</p></div>');
            $modal.fadeIn(200);

            const originalText = $link.html();
            $link.addClass('loading').html('<i class="fa-regular fa-eye"></i> Loading…');

            jQuery.ajax({
                url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                method: 'POST',
                data: {
                    action: 'kv_load_accommodation_details',
                    property_id: propertyId
                },
                success: function(res) {
                    if (res.success) {
                        $modalBody.html(res.data.html || res.data);
                        $modal.fadeIn(200, function() {
                            // Init slick gallery if available
                            const $gallery = $modalBody.find('.js-room-gallery');
                            if ($gallery.length && typeof jQuery.fn.slick === 'function') {
                                if ($gallery.hasClass('slick-initialized')) $gallery.slick('unslick');
                                const $slides = $gallery.find('.room-slide');
                                if ($slides.length >= 3) {
                                    $gallery.slick({
                                        infinite: true,
                                        slidesToShow: 3,
                                        slidesToScroll: 1,
                                        arrows: true,
                                        dots: false,
                                        adaptiveHeight: true,
                                        prevArrow: '<button type="button" class="slick-prev"><img src="' + base_url + '/wp-content/themes/kingdomvision/images/left_arrow.svg" alt="Previous"></button>',
                                        nextArrow: '<button type="button" class="slick-next"><img src="' + base_url + '/wp-content/themes/kingdomvision/images/right_arrow.svg" alt="Next"></button>',
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
                        });
                    } else {
                        $modalBody.html('<p class="rb-error">Could not load accommodation details.</p>');
                    }
                },
                error: function() {
                    $modalBody.html('<p class="rb-error">Failed to load accommodation details.</p>');
                },
                complete: function() {
                    $link.removeClass('loading').html(originalText);
                }
            });
        },

    };

    // ============================================================================
    // PUBLIC API
    // ============================================================================
    return {
        init: function() {
            if (!DOMCache.init()) {
                console.warn('BookingManager: Required DOM elements not found');
                return false;
            }

            const cart = Storage.getCart();
            RoomRenderer.renderRooms(cart);
            Summary.update(cart);
            PaymentSchedule.update(cart);
            EventHandlers.init();

            return true;
        },

        refresh: function() {
            const cart = Storage.getCart();
            Summary.update(cart);
            PaymentSchedule.update(cart);
        },

        removeItem: function(index) {
            return Storage.removeItemByIndex(index);
        },

        getCart: function() {
            return Storage.getCart();
        },

        setConfig: function(overrides) {
            Object.assign(CONFIG, overrides);
        }
    };
})();

// ============================================================================
// INITIALIZATION
// ============================================================================
jQuery(document).ready(function($) {
    if ($('.booking_page').length > 0) {
        BookingManager.init();
    }
});