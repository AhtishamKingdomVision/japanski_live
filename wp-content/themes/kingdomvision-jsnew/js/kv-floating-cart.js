/**
 * KV Floating Cart Icon + Mobile Popup
 *
 * Provides a sticky cart icon visible on every page of the website.
 *
 * - Desktop: clicking the icon redirects the customer to the Property
 *   Details page of the property currently in the cart.
 * - Mobile:  clicking the icon opens a popup/modal styled like the existing
 *   cart widget (property header, room cards, dates, payments, total,
 *   Proceed button) on the current page (no redirect).
 * - When the cart is empty the icon is hidden.
 * - The icon updates dynamically as items are added or removed.
 *
 * Mobile popup layout matches the reference design:
 *   - Top dark bar with item count + totals
 *   - "Booking Summary" header with close button
 *   - Property image + name + resort
 *   - Room card(s) with image, name, rate, price, delete
 *   - Guest summary
 *   - Check-in / check-out dates
 *   - Deposit / Balance / Total rows
 *   - Proceed button at the bottom
 */
(function () {
    'use strict';

    const STORAGE_KEY     = 'rb_cart';
    const ICON_SELECTOR   = '.kv-floating-cart';
    const BACKDROP_SEL    = '.kv-floating-cart__backdrop';
    const POPUP_SEL       = '.kv-floating-cart__popup';

    // Cache the resolved property URL so we only do one AJAX lookup per
    // property during a session.
    const urlCache = Object.create(null);

    function bookingPageUrl() {
        const home = (typeof kv_object !== 'undefined' && kv_object.homeUrl)
            ? String(kv_object.homeUrl)
            : '/';
        return home.replace(/\/?$/, '/') + 'booking/';
    }

    /**
     * Read the cart from localStorage.
     */
    function readCart() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return { items: [] };
            const parsed = JSON.parse(raw);
            if (parsed && Array.isArray(parsed.items)) return parsed;
            if (Array.isArray(parsed)) return { items: parsed };
            return { items: [] };
        } catch (e) {
            return { items: [] };
        }
    }

    function getPrimaryItem(cart) {
        if (!cart || !Array.isArray(cart.items) || !cart.items.length) return null;
        return cart.items[0];
    }

    function getItemCount(cart) {
        if (!cart || !Array.isArray(cart.items)) return 0;
        return cart.items.length;
    }

    function getItemTotal(cart) {
        if (!cart || !Array.isArray(cart.items)) return 0;
        let total = 0;
        for (let i = 0; i < cart.items.length; i++) {
            const it = cart.items[i];
            const price = Number(it?.price || it?.amount || it?.total_price || it?.total_amount || 0);
            total += isNaN(price) ? 0 : price;
        }
        return total;
    }

    function isMobile() {
        return typeof window.matchMedia === 'function'
            && window.matchMedia('(max-width: 767px)').matches;
    }

    /**
     * Build the floating cart icon markup. Created once and reused; the
     * existing node is updated in place to avoid duplicate DOM.
     */
    function ensureIconNode() {
        let node = document.querySelector(ICON_SELECTOR);
        if (node) return node;

        node = document.createElement('button');
        node.type = 'button';
        node.className = 'kv-floating-cart';
        node.setAttribute('aria-label', 'View cart');
        node.setAttribute('data-state', 'hidden');
        node.innerHTML = `
            <span class="kv-floating-cart__icon" aria-hidden="true">
                <i class="fa-solid fa-bag-shopping"></i>
            </span>
            <span class="kv-floating-cart__count" data-role="count">0</span>
            <span class="kv-floating-cart__total" data-role="total">¥0</span>
        `;

        // Backdrop for the mobile popup (one-time creation, hidden by default).
        let backdrop = document.querySelector(BACKDROP_SEL);
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'kv-floating-cart__backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.appendChild(backdrop);

            backdrop.addEventListener('click', closeMobilePopup);
        }

        node.addEventListener('click', onIconClick);
        document.body.appendChild(node);
        return node;
    }

    /**
     * Update the icon UI based on the current cart state.
     */
    function updateIcon() {
        const cart = readCart();
        const count = getItemCount(cart);
        const node = ensureIconNode();

        // Cache the primary property id so we don't have to re-read it on every
        // click; useful especially for desktop redirects.
        const primary = getPrimaryItem(cart);
        const propertyId = primary
            ? (primary.hotel_type_id || primary.property_id || primary.hotelId || '')
            : '';

        if (count <= 0 || !propertyId) {
            node.setAttribute('data-state', 'hidden');
            node.style.display = 'none';
            // Always close the mobile popup when the cart empties.
            closeMobilePopup();
            return;
        }

        node.setAttribute('data-state', 'visible');
        node.setAttribute('data-property-id', propertyId);
        node.style.display = '';

        const countEl = node.querySelector('[data-role="count"]');
        if (countEl) countEl.textContent = String(count);

        const totalEl = node.querySelector('[data-role="total"]');
        if (totalEl) {
            const total = getItemTotal(cart);
            totalEl.textContent = '¥' + total.toLocaleString('ja-JP');
        }
    }

    /**
     * Resolve the property URL for a given booking-system property ID.
     * Cached per session.
     */
    function fetchPropertyUrl(propertyId) {
        if (!propertyId) return Promise.resolve(null);
        if (urlCache[propertyId]) return Promise.resolve(urlCache[propertyId]);

        return new Promise((resolve) => {
            if (typeof kv_object === 'undefined' || !kv_object.ajaxurl) {
                resolve(null);
                return;
            }

            try {
                const body = new URLSearchParams({
                    action: 'kv_get_property_url',
                    property_id: String(propertyId),
                });

                fetch(kv_object.ajaxurl + '?v=' + new Date().getTime(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                })
                .then((r) => r.json())
                .then((res) => {
                    if (res && res.success && res.data && res.data.url) {
                        urlCache[propertyId] = res.data.url;
                        resolve(res.data.url);
                    } else {
                        resolve(null);
                    }
                })
                .catch(() => resolve(null));
            } catch (e) {
                resolve(null);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Mobile popup
    //
    // Renders a full Booking Summary modal that matches the reference design:
    //   - Dark header bar with item count + totals
    //   - "Booking Summary" header with close button
    //   - Property header (image + name + resort)
    //   - Room cards (image, name, rate, price, delete)
    //   - Guest summary
    //   - Check-in / Check-out dates
    //   - Deposit / Balance due / Total rows
    //   - Proceed button at the bottom
    // -------------------------------------------------------------------------

    function ensurePopupNode() {
        let popup = document.querySelector(POPUP_SEL);
        if (popup) return popup;

        popup = document.createElement('div');
        popup.className = 'kv-floating-cart__popup';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-modal', 'true');
        popup.setAttribute('aria-label', 'Booking Summary');
        // popup.innerHTML = `
        //     <div class="kv-floating-cart__popup-summary">
        //         <div class="kv-floating-cart__popup-summary-cell">
        //             <span data-role="popup-count">0</span>
        //             <small>Items</small>
        //         </div>
        //         <div class="kv-floating-cart__popup-summary-cell">
        //             <span data-role="popup-deposit">¥0</span>
        //             <small>Deposit</small>
        //         </div>
        //         <div class="kv-floating-cart__popup-summary-cell">
        //             <span data-role="popup-balance">¥0</span>
        //             <small>Balance</small>
        //         </div>
        //     </div>`;
        
        popup.innerHTML = `
            <div class="kv-floating-cart__popup-shell">
                <div class="kv-floating-cart__popup-header">
                    <h3>Booking Summary</h3>
                    <button type="button" class="kv-floating-cart__popup-close" aria-label="Close cart">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="kv-floating-cart__popup-body" data-role="popup-body"></div>
                <div class="kv-floating-cart__popup-footer" data-role="popup-footer"></div>
            </div>
        `;
        document.body.appendChild(popup);

        const closeBtn = popup.querySelector('.kv-floating-cart__popup-close');
        if (closeBtn) closeBtn.addEventListener('click', closeMobilePopup);

        return popup;
    }

    function formatDate(d) {
        // Accepts "2026-12-01" or "01/12/2026" or "01 Dec, 2026" style strings
        // and returns "01 Dec, 2026" format.
        if (!d) return '';
        try {
            const dt = new Date(d);
            if (!isNaN(dt.getTime())) {
                const day = String(dt.getDate()).padStart(2, '0');
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sept','Oct','Nov','Dec'];
                const month = months[dt.getMonth()];
                return `${day} ${month}, ${dt.getFullYear()}`;
            }
        } catch (e) { /* fallthrough */ }
        return String(d);
    }

    function renderPopupBody() {
        const popup = ensurePopupNode();
        const body = popup.querySelector('[data-role="popup-body"]');
        const footer = popup.querySelector('[data-role="popup-footer"]');
        const countEl = popup.querySelector('[data-role="popup-count"]');
        const depositEl = popup.querySelector('[data-role="popup-deposit"]');
        const balanceEl = popup.querySelector('[data-role="popup-balance"]');

        const cart = readCart();
        const items = Array.isArray(cart.items) ? cart.items : [];
        const count = items.length;

        if (countEl) countEl.textContent = String(count);

        let totalDeposit = 0;
        let totalBalance = 0;
        for (let i = 0; i < items.length; i++) {
            const it = items[i];
            const price = Number(it?.price || 0);
            const dep = Number(it?.payment?.depositAmount || 0);
            totalDeposit += isNaN(dep) ? 0 : dep;
            totalBalance += isNaN(price - dep) ? 0 : (price - dep);
        }
        if (depositEl) depositEl.textContent = '¥' + totalDeposit.toLocaleString('ja-JP');
        if (balanceEl) balanceEl.textContent = '¥' + totalBalance.toLocaleString('ja-JP');

        if (!count) {
            body.innerHTML = `<p class="kv-floating-cart__empty">Your cart is empty.</p>`;
            footer.innerHTML = '';
            return;
        }

        // Property header (from first cart item)
        const first = items[0];
        let propertyHtml = '';
        if (first.property_name) {
            const pImg = first.property_image
                ? `<img src="${escapeHtml(first.property_image)}" alt="${escapeHtml(first.property_name)}" class="kv-floating-cart__property-img">`
                : '';
            propertyHtml = `
                <div class="kv-floating-cart__property">
                    ${pImg}
                    <div class="kv-floating-cart__property-meta">
                        <div class="kv-floating-cart__property-name">${escapeHtml(first.property_name)}</div>
                        ${first.resort_name ? `<div class="kv-floating-cart__property-resort">${escapeHtml(first.resort_name)}</div>` : ''}
                    </div>
                </div>
            `;
        }

        // Room cards
        let cardsHtml = '';
        for (let idx = 0; idx < items.length; idx++) {
            const it = items[idx];
            const price = Number(it?.price || 0);
            const dep = Number(it?.payment?.depositAmount || 0);
            const bal = Number(it?.payment?.balanceDueAmount || (price - dep));

            const adults   = Number(it?.guests?.adults || 0);
            const children = Number(it?.guests?.children || 0);
            const infants  = Number(it?.guests?.infants || 0);
            const totalPax = adults + children + infants;

            const ciRaw = it?.dates?.check_in || '';
            const coRaw = it?.dates?.check_out || '';
            const ciDisplay = ciRaw ? formatDate(ciRaw) : (it?.dates?.checkinDisplay || '');
            const coDisplay = coRaw ? formatDate(coRaw) : (it?.dates?.checkoutDisplay || '');

            const balanceDate = it?.payment?.balanceDueDate
                ? formatDate(it.payment.balanceDueDate)
                : '';

            const roomImgHtml = it?.room_image
                ? `<div class="kv-floating-cart__room-img-wrap"><img src="${escapeHtml(it.room_image)}" alt="${escapeHtml(it.room_name || '')}" class="kv-floating-cart__room-img"></div>`
                : '';

            let paymentHtml = '';
            if (dep > 0) {
                paymentHtml += `
                    <div class="kv-floating-cart__row">
                        <span>Deposit on booking</span>
                        <strong>¥${dep.toLocaleString('ja-JP')}</strong>
                    </div>`;
                if (bal > 0) {
                    paymentHtml += `
                        <div class="kv-floating-cart__row">
                            <span>Balance due${balanceDate ? ' (' + balanceDate + ')' : ''}</span>
                            <strong>¥${bal.toLocaleString('ja-JP')}</strong>
                        </div>`;
                }
            } else {
                paymentHtml += `
                    <div class="kv-floating-cart__row">
                        <span>Full amount${balanceDate ? ' due ' + balanceDate : ' due on booking'}</span>
                        <strong>¥${price.toLocaleString('ja-JP')}</strong>
                    </div>`;
            }

            cardsHtml += `
                <div class="kv-floating-cart__card" data-idx="${idx}">
                    ${roomImgHtml}
                    <div class="kv-floating-cart__card-body">
                        <div class="kv-floating-cart__card-head">
                            <div class="kv-floating-cart__card-info">
                                <div class="kv-floating-cart__card-room">${escapeHtml(it?.room_name || '')}</div>
                                <div class="kv-floating-cart__card-rate">${escapeHtml(it?.rateplan_name || '')}</div>
                            </div>
                            <div class="kv-floating-cart__card-right">
                                <div class="kv-floating-cart__card-price">¥${price.toLocaleString('ja-JP')}</div>
                                <button type="button" class="kv-floating-cart__remove" data-role="remove" data-idx="${idx}" aria-label="Remove item">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                        <div class="kv-floating-cart__row kv-floating-cart__row--pax">
                            <span>Total Guests: ${totalPax}</span>
                        </div>
                        ${adults ? `<div class="kv-floating-cart__row"><span>Adults</span><strong>${adults}</strong></div>` : ''}
                        ${children ? `<div class="kv-floating-cart__row"><span>Children</span><strong>${children}</strong></div>` : ''}
                        ${infants ? `<div class="kv-floating-cart__row"><span>Infants</span><strong>${infants}</strong></div>` : ''}
                        <div class="kv-floating-cart__dates">
                            <div class="kv-floating-cart__date">
                                <span>Check in</span>
                                <strong>${ciDisplay}</strong>
                            </div>
                            <div class="kv-floating-cart__date-sep"></div>
                            <div class="kv-floating-cart__date">
                                <span>Check out</span>
                                <strong>${coDisplay}</strong>
                            </div>
                        </div>
                        <div class="kv-floating-cart__payment">${paymentHtml}</div>
                    </div>
                </div>
            `;
        }

        body.innerHTML = `
            ${propertyHtml}
            ${cardsHtml}
        `;

        // Footer with totals + proceed button
        const total = getItemTotal(cart);
        footer.innerHTML = `
            <div class="kv-floating-cart__footer-rows">
                ${count > 1 ? `
                    <div class="kv-floating-cart__row">
                        <span>Subtotal</span>
                        <strong>¥${total.toLocaleString('ja-JP')}</strong>
                    </div>
                    <div class="kv-floating-cart__row">
                        <span>Total deposit</span>
                        <strong>¥${totalDeposit.toLocaleString('ja-JP')}</strong>
                    </div>
                    <div class="kv-floating-cart__row">
                        <span>Total balance</span>
                        <strong>¥${totalBalance.toLocaleString('ja-JP')}</strong>
                    </div>
                ` : ''}
                <div class="kv-floating-cart__total-row" ${count === 1 ? `style="border-top: none;"` : ''}>
                    <strong>Total</strong>
                    <strong>¥${total.toLocaleString('ja-JP')}</strong>
                </div>
            </div>
            <button type="button" class="kv-floating-cart__proceed" data-role="proceed">
                Proceed
            </button>
        `;

        // Wire up interactions inside the popup
        const removeButtons = popup.querySelectorAll('[data-role="remove"]');
        removeButtons.forEach((btn) => {
            btn.addEventListener('click', onPopupRemove);
        });

        const proceedBtn = popup.querySelector('[data-role="proceed"]');
        if (proceedBtn) proceedBtn.addEventListener('click', onProceedClick);
    }

    function onPopupRemove(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const idx = Number(btn.getAttribute('data-idx'));
        if (!window.confirm('Are you sure you want to remove this room?')) {
            return;
        }

        const cart = readCart();
        if (!cart.items || typeof cart.items[idx] === 'undefined') return;

        cart.items.splice(idx, 1);
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
        } catch (err) { /* ignore */ }

        // Notify all listeners (cart timer, booking manager, etc.)
        document.dispatchEvent(new CustomEvent('rb:cart-updated', { detail: { cart: cart } }));

        // Re-render
        if (!cart.items.length) {
            closeMobilePopup();
            return;
        }
        renderPopupBody();
    }

    function openMobilePopup() {
        ensurePopupNode();
        renderPopupBody();

        const popup = document.querySelector(POPUP_SEL);
        if (popup) popup.classList.add('is-visible');

        const backdrop = document.querySelector(BACKDROP_SEL);
        if (backdrop) backdrop.classList.add('is-visible');

        document.body.classList.add('cart-active');
    }

    function closeMobilePopup() {
        const popup = document.querySelector(POPUP_SEL);
        if (popup) popup.classList.remove('is-visible');

        const backdrop = document.querySelector(BACKDROP_SEL);
        if (backdrop) backdrop.classList.remove('is-visible');

        document.body.classList.remove('cart-active');
    }

    // -------------------------------------------------------------------------
    // Click handlers
    // -------------------------------------------------------------------------

    function onIconClick(e) {
        e.preventDefault();
        const node = e.currentTarget;
        const propertyId = node.getAttribute('data-property-id');

        if (isMobile()) {
            // Mobile: open the cart popup without redirecting.
            openMobilePopup();
            return;
        }

        // Desktop: redirect to the property page of the cart's property.
        if (!propertyId) {
            return;
        }

        // fetchPropertyUrl(propertyId).then((url) => {
        //     if (url) {
        //         window.location.href = url;
        //     } else {
        //         // Fallback: home page rather than a dead-end.
        //         window.location.href = '/';
        //     }
        // });
        window.location.href = bookingPageUrl();
    }

    function onProceedClick(e) {
        e.preventDefault();
        const node = e.currentTarget;
        const cart = readCart();
        const primary = getPrimaryItem(cart);
        const propertyId = primary ? (primary.hotel_type_id || primary.property_id || '') : '';

        if (!propertyId) {
            return;
        }

        if (node) {
            node.disabled = true;
            node.textContent = 'Loading…';
        }

        // fetchPropertyUrl(propertyId).then((url) => {
        //     if (url) {
        //         window.location.href = url;
        //     } else {
        //         window.location.href = '/';
        //     }
        // });
        window.location.href = bookingPageUrl();
    }

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // -------------------------------------------------------------------------
    // Wiring
    // -------------------------------------------------------------------------

    let resizeRaf = null;
    function onResize() {
        if (resizeRaf) return;
        resizeRaf = requestAnimationFrame(() => {
            resizeRaf = null;
            // The icon's behavior depends on viewport; just update so the
            // visual state stays in sync if it changes between mobile/desktop.
            updateIcon();
        });
    }

    function bindEvents() {
        // Re-render whenever the cart changes.
        document.addEventListener('rb:cart-updated', updateIcon);
        document.addEventListener('storage', (e) => {
            if (e.key === STORAGE_KEY) updateIcon();
        });

        // React to viewport changes (e.g. orientation changes).
        window.addEventListener('resize', onResize);

        // Close popup with Escape key on mobile.
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMobilePopup();
            }
        });
    }

    function init() {
        bindEvents();
        updateIcon();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for testing and external triggering.
    window.KVFloatingCart = {
        update: updateIcon,
        openMobilePopup,
        closeMobilePopup,
        readCart,
    };
})();
