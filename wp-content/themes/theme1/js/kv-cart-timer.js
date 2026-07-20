/**
 * KV Cart Timer
 *
 * Manages the cart expiration timer for the booking flow.
 * - Persists `expires_at` (epoch ms) inside the rb_cart session storage
 * - Renders a countdown in the cart widget on both Cart and Booking pages
 * - On expiration, behavior depends on the property type:
 *     - RoomBoss: hides Proceed/Pay/Book, shows "Refresh Availability",
 *       keeps the cart items so the user can re-verify availability.
 *     - BedBank: clears the cart, hides the Proceed buttons, shows a
 *       reservation-expired message. Refresh Availability is NEVER shown
 *       for BedBank.
 * - Timer state survives page refreshes (driven by wall-clock time)
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'rb_cart';
    const TIMER_META_KEY = 'rb_cart_timer';
    const DEFAULTS = {
        roomboss_seconds: 10 * 60,
        bedbank_seconds:  6  * 60 * 60,
    };

    // Merge window.kvCartTimer (PHP defaults) if provided.
    if (typeof window.kvCartTimer === 'object' && window.kvCartTimer) {
        if (typeof window.kvCartTimer.roomboss_seconds === 'number') {
            DEFAULTS.roomboss_seconds = window.kvCartTimer.roomboss_seconds;
        }
        if (typeof window.kvCartTimer.bedbank_seconds === 'number') {
            DEFAULTS.bedbank_seconds = window.kvCartTimer.bedbank_seconds;
        }
    }

    const TIMER_SELECTOR = '.rb-cart-timer';
    // Action button selectors split by property type.
    //
    // - The sidebar "Proceed" (.rb-proceed-btn) on Property Details is a
    //   SHARED button shown for BOTH property types (it just sends the
    //   customer to /booking/).
    // - On the booking confirmation page:
    //     - RoomBoss shows "Proceed to Payment" (.rb-pay-btn)
    //     - BedBank shows "Enquire Now" (.rb-book-now-btn)
    //     - These two are mutually exclusive.
    const PROCEED_SELECTORS = ['.rb-proceed-btn', '.rb-pay-btn', '.rb-book-now-btn'];
    const ROOMBOSS_BTN_SELECTORS = ['.rb-proceed-btn', '.rb-pay-btn'];
    const BEDBANK_BTN_SELECTORS  = ['.rb-proceed-btn', '.rb-book-now-btn'];
    const ROOMBOSS_ONLY_BTN_SELECTORS = ['.rb-pay-btn'];
    const BEDBANK_ONLY_BTN_SELECTORS  = ['.rb-book-now-btn'];
    const REFRESH_BTN_CLASS = 'rb-cart-refresh-btn';
    const EXPIRED_MSG_CLASS = 'rb-cart-expired-msg';

    // -------------------------------------------------------------------------
    // Storage helpers
    // -------------------------------------------------------------------------
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

    function writeCart(cart) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
        } catch (e) { /* ignore */ }
    }

    function readTimerMeta() {
        try {
            const raw = localStorage.getItem(TIMER_META_KEY);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function writeTimerMeta(meta) {
        try {
            localStorage.setItem(TIMER_META_KEY, JSON.stringify(meta));
        } catch (e) { /* ignore */ }
    }

    function clearTimerMeta() {
        try { localStorage.removeItem(TIMER_META_KEY); } catch (e) { /* ignore */ }
    }

    // -------------------------------------------------------------------------
    // Cart property type detection
    // -------------------------------------------------------------------------
    function isBedbankItem(item) {
        if (!item) return false;
        const v = item.is_bedbank ?? item.isBedBank;
        if (typeof v === 'string') {
            return v === '1' || v.toLowerCase() === 'true';
        }
        return !!v;
    }

    function isRoombossItem(item) {
        if (!item) return false;
        return !isBedbankItem(item);
    }

    function getPrimaryItem(cart) {
        if (!cart || !Array.isArray(cart.items) || !cart.items.length) return null;
        return cart.items[0];
    }

    // -------------------------------------------------------------------------
    // Duration resolution
    // -------------------------------------------------------------------------
    function getDurationSeconds(propertyType /* 'roomboss' | 'bedbank' */) {
        if (propertyType === 'bedbank') return DEFAULTS.bedbank_seconds;
        return DEFAULTS.roomboss_seconds;
    }

    /**
     * Ask the backend for the dynamic timer duration when we have a property ID.
     * Falls back to the JS defaults if the request fails.
     */
    function fetchDurationForProperty(propertyId, fallbackSeconds) {
        return new Promise((resolve) => {
            if (!propertyId || typeof kv_object === 'undefined' || !kv_object.ajaxurl) {
                resolve(fallbackSeconds);
                return;
            }

            try {
                const body = new URLSearchParams({
                    action: 'kv_get_cart_timer_config',
                    property_id: String(propertyId),
                });

                fetch(kv_object.ajaxurl + '?v=' + new Date().getTime(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                })
                .then((r) => r.json())
                .then((res) => {
                    if (res && res.success && res.data && typeof res.data.duration === 'number') {
                        resolve(res.data.duration);
                    } else {
                        resolve(fallbackSeconds);
                    }
                })
                .catch(() => resolve(fallbackSeconds));
            } catch (e) {
                resolve(fallbackSeconds);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Timer init / persist
    // -------------------------------------------------------------------------
    /**
     * Initialize (or reset) the timer for the current cart.
     * Stores expires_at = Date.now() + duration on localStorage.
     */
    async function startTimer(cart) {
        const item = getPrimaryItem(cart);
        if (!item) {
            clearTimerMeta();
            return;
        }

        const propertyId = item.hotel_type_id || item.property_id || item.hotelId || '';
        const isBedbank = isBedbankItem(item);
        const propertyType = isBedbank ? 'bedbank' : 'roomboss';
        const fallbackSeconds = getDurationSeconds(propertyType);

        const durationSeconds = await fetchDurationForProperty(propertyId, fallbackSeconds);

        const meta = {
            property_id:    propertyId,
            property_type:  propertyType,
            duration:       durationSeconds,
            duration_ms:    durationSeconds * 1000,
            expires_at:     Date.now() + (durationSeconds * 1000),
            is_bedbank:     isBedbank,
            is_roomboss:    !isBedbank,
        };

        writeTimerMeta(meta);
    }

    function isExpired(meta) {
        if (!meta || !meta.expires_at) return true;
        return Date.now() >= meta.expires_at;
    }

    function remainingMs(meta) {
        if (!meta || !meta.expires_at) return 0;
        return Math.max(0, meta.expires_at - Date.now());
    }

    // -------------------------------------------------------------------------
    // UI helpers
    // -------------------------------------------------------------------------
    function formatRemaining(ms) {
        if (ms <= 0) return '00:00';
        const totalSeconds = Math.floor(ms / 1000);
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        const pad = (n) => String(n).padStart(2, '0');
        if (hours > 0) {
            return `${hours}:${pad(minutes)}:${pad(seconds)}`;
        }
        return `${pad(minutes)}:${pad(seconds)}`;
    }

    function ensureTimerNode(cart) {
        // Always clean up any duplicates before doing anything else. This
        // prevents multiple timer banners if the cart widget is refreshed
        // multiple times via AJAX.
        const existingAll = document.querySelectorAll(TIMER_SELECTOR);
        if (existingAll.length > 1) {
            for (let i = 1; i < existingAll.length; i++) {
                existingAll[i].remove();
            }
        }

        let $node = document.querySelector(TIMER_SELECTOR);
        if ($node) {
            // If the node lost its parent (e.g. summary-box was re-rendered),
            // re-parent it into the correct container.
            if (!$node.parentElement || !findCartContainer()) {
                const inserted = insertTimerNode($node);
                if (!inserted) {
                    // No container yet; the timer stays detached and will be
                    // mounted once the cart widget appears.
                    return null;
                }
            }
            return $node;
        }

        // Create a fresh timer banner.
        const wrapper = document.createElement('div');
        wrapper.className = 'rb-cart-timer';
        wrapper.setAttribute('role', 'status');
        wrapper.setAttribute('aria-live', 'polite');
        wrapper.innerHTML = `
            <div class="rb-cart-timer__icon" aria-hidden="true">
                <i class="fa-regular fa-clock"></i>
            </div>
            <div class="rb-cart-timer__text">
                <span class="rb-cart-timer__label">Time remaining</span>
                <span class="rb-cart-timer__countdown" data-role="countdown">--:--</span>
            </div>
            <div class="rb-cart-timer__refresh" data-role="refresh-wrap" style="display:none;">
                <button type="button" class="rb-cart-refresh-btn" data-role="refresh">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    Refresh Availability
                </button>
            </div>
        `;

        const inserted = insertTimerNode(wrapper);
        if (!inserted) {
            // No container yet - keep the node detached. It will be mounted
            // by mount() once the cart widget appears.
            return null;
        }
        return wrapper;
    }

    function insertTimerNode(node) {
        // Locate a real container inside the cart widget. We deliberately do
        // NOT fall back to <body> - if the cart widget hasn't been rendered
        // yet (e.g. on a Property Details page where it loads via AJAX),
        // return false so the caller can defer until the container appears.
        const container = findCartContainer();
        if (container) {
            container.parentNode.insertBefore(node, container);
            return true;
        }
        return false;
    }

    /**
     * Find the best DOM location for the timer banner.
     *
     * Priority order (most specific first):
     *   1. `#rbCartFooter` / `.rb-cart-footer`  - cart sidebar footer
     *   2. `#rbCartBody` / `.rb-cart-body`     - cart body (fallback)
     *   3. `.rb-cart-wrap`                     - cart wrapper
     *   4. `aside.rb-cart`                     - cart aside
     *   5. `.rb-summary-box` / `#rb-booking-details-widget` - booking page widget
     *
     * Returns the element BEFORE which the timer should be inserted, or
     * `null` if no container is available yet.
     */
    function findCartContainer() {
        const selectors = [
            '#rbCartFooter',
            '.rb-cart-footer',
            '#rbCartBody',
            '.rb-cart-body',
            '.rb-cart-wrap',
            'aside.rb-cart',
            '.rb-summary-box',
            '#rb-booking-details-widget',
        ];

        for (let i = 0; i < selectors.length; i++) {
            const el = document.querySelector(selectors[i]);
            if (el && el.parentElement) {
                return el;
            }
        }
        return null;
    }

    /**
     * Wait for any cart container to appear in the DOM.
     *
     * Resolves with the container element as soon as it's found, or rejects
     * after `timeoutMs` (default 10 seconds). Uses MutationObserver for
     * robustness against AJAX-loaded markup.
     */
    function waitForCartContainer(timeoutMs = 10000) {
        return new Promise((resolve, reject) => {
            const existing = findCartContainer();
            if (existing) {
                resolve(existing);
                return;
            }

            let timeoutId = null;
            const observer = new MutationObserver(() => {
                const el = findCartContainer();
                if (el) {
                    observer.disconnect();
                    if (timeoutId) clearTimeout(timeoutId);
                    resolve(el);
                }
            });

            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
            });

            timeoutId = setTimeout(() => {
                observer.disconnect();
                reject(new Error('cart_container_not_found'));
            }, timeoutMs);
        });
    }

    function getProceedButtons() {
        const all = [];
        PROCEED_SELECTORS.forEach((sel) => {
            document.querySelectorAll(sel).forEach((el) => all.push(el));
        });
        // De-duplicate.
        const seen = new Set();
        return all.filter((el) => {
            if (!el || seen.has(el)) return false;
            seen.add(el);
            return true;
        });
    }

    function collectBySelectors(selectors) {
        const all = [];
        selectors.forEach((sel) => {
            document.querySelectorAll(sel).forEach((el) => all.push(el));
        });
        const seen = new Set();
        return all.filter((el) => {
            if (!el || seen.has(el)) return false;
            seen.add(el);
            return true;
        });
    }

    function getRoomBossButtons()      { return collectBySelectors(ROOMBOSS_BTN_SELECTORS); }
    function getBedBankButtons()       { return collectBySelectors(BEDBANK_BTN_SELECTORS); }
    function getRoomBossOnlyButtons()  { return collectBySelectors(ROOMBOSS_ONLY_BTN_SELECTORS); }
    function getBedBankOnlyButtons()   { return collectBySelectors(BEDBANK_ONLY_BTN_SELECTORS); }

    /**
     * Resolve the booking mode from the cart or stored timer metadata.
     * Returns one of: 'roomboss' | 'bedbank' | 'unknown'.
     */
    function resolveBookingMode() {
        try {
            const metaRaw = localStorage.getItem(TIMER_META_KEY);
            if (metaRaw) {
                const meta = JSON.parse(metaRaw);
                if (meta && meta.is_bedbank === true) return 'bedbank';
                if (meta && meta.is_roomboss === true) return 'roomboss';
                if (meta && meta.property_type === 'bedbank') return 'bedbank';
            }
        } catch (e) { /* ignore */ }

        try {
            const cartRaw = localStorage.getItem(STORAGE_KEY);
            if (cartRaw) {
                const cart = JSON.parse(cartRaw);
                if (cart && Array.isArray(cart.items) && cart.items.length) {
                    const primary = cart.items[0];
                    if (primary && isBedbankItem(primary)) return 'bedbank';
                }
            }
        } catch (e) { /* ignore */ }

        return 'unknown';
    }

    function setButtonVisible(btn, visible) {
        if (!btn) return;
        if (visible) {
            btn.removeAttribute('data-rb-timer-hidden');
            btn.style.removeProperty('display');
            btn.disabled = false;
        } else {
            btn.setAttribute('data-rb-timer-hidden', '1');
            btn.style.display = 'none';
            btn.disabled = true;
        }
    }

    /**
     * Show / hide the action buttons according to the property type.
     *
     * Button groups:
     *   - Shared: .rb-proceed-btn (sidebar Proceed to Booking - shown for
     *     BOTH RoomBoss and BedBank)
     *   - RoomBoss-only: .rb-pay-btn (Flywire Proceed to Payment)
     *   - BedBank-only:  .rb-book-now-btn (Enquire Now)
     *
     * Behavior:
     *   - `visible === false`: hide every action button.
     *   - `visible === true` + mode 'roomboss': show shared + RoomBoss-only,
     *     hide BedBank-only.
     *   - `visible === true` + mode 'bedbank': show shared + BedBank-only,
     *     hide RoomBoss-only.
     *   - `mode` omitted: auto-resolve from timer meta or cart.
     */
    function setProceedVisible(visible, mode) {
        if (!visible) {
            // Hide everything (timer expired, cart cleared, etc.)
            getProceedButtons().forEach((btn) => setButtonVisible(btn, false));
            return;
        }

        const effectiveMode = mode || resolveBookingMode();

        if (effectiveMode === 'bedbank') {
            // Sidebar Proceed + Enquire Now visible; Proceed to Payment hidden.
            getBedBankButtons().forEach((btn) => setButtonVisible(btn, true));
            getRoomBossOnlyButtons().forEach((btn) => setButtonVisible(btn, false));
        } else if (effectiveMode === 'roomboss') {
            // Sidebar Proceed + Proceed to Payment visible; Enquire Now hidden.
            getRoomBossButtons().forEach((btn) => setButtonVisible(btn, true));
            getBedBankOnlyButtons().forEach((btn) => setButtonVisible(btn, false));
        } else {
            // Unknown - safest default: RoomBoss layout.
            getRoomBossButtons().forEach((btn) => setButtonVisible(btn, true));
            getBedBankOnlyButtons().forEach((btn) => setButtonVisible(btn, false));
        }
    }

    function showRefresh(show) {
        const node = document.querySelector(TIMER_SELECTOR);
        if (!node) return;
        const wrap = node.querySelector('[data-role="refresh-wrap"]');
        if (wrap) {
            wrap.style.display = show ? '' : 'none';
        }
    }

    function showExpiredMessage(message) {
        if (!message) message = 'Your reservation period has expired.';
        // Remove any existing.
        document.querySelectorAll('.' + EXPIRED_MSG_CLASS).forEach((el) => el.remove());

        const node = document.createElement('div');
        node.className = EXPIRED_MSG_CLASS;
        node.setAttribute('role', 'alert');
        node.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i><span>${message}</span>`;

        // Insert at the top of the booking wrapper or cart.
        const target = document.querySelector('.rb-booking-wrapper, .rb-cart, #rb-booking-details-widget, body');
        if (target) {
            target.prepend(node);
        }
    }

    // -------------------------------------------------------------------------
    // Expiration flows
    // -------------------------------------------------------------------------
    /**
     * RoomBoss expiration: keep the cart items, hide Proceed/Pay/Book, and
     * show "Refresh Availability" so the user can re-verify availability
     * before continuing.
     */
    function handleRoombossExpiration(cart) {
        setProceedVisible(false);
        showRefresh(true);
        const node = document.querySelector(TIMER_SELECTOR);
        if (node) node.classList.add('is-expired');

        document.dispatchEvent(new CustomEvent('rb:cart-timer-expired', {
            detail: { cart, type: 'roomboss' }
        }));
    }

    /**
     * BedBank expiration: clear the cart, hide all Proceed/Pay/Book buttons,
     * and show a reservation-expired message. Refresh Availability is NEVER
     * shown for BedBank - the user must re-select their rooms from scratch.
     */
    function handleBedbankExpiration(cart) {
        // 1) Clear the cart unconditionally.
        writeCart({ items: [] });
        clearTimerMeta();

        // 2) Hide every action button.
        setProceedVisible(false);

        // 3) Ensure Refresh Availability is NOT shown for BedBank.
        showRefresh(false);

        // 4) Show the reservation-expired message.
        showExpiredMessage('Your reservation period has expired. Please re-select your rooms to continue.');

        // 5) Notify any listening UI to re-render.
        document.dispatchEvent(new CustomEvent('rb:cart-timer-expired', {
            detail: { cart: { items: [] }, type: 'bedbank' }
        }));
        document.dispatchEvent(new CustomEvent('rb:cart-updated', {
            detail: { cart: { items: [] }, expired: true }
        }));
    }

    // -------------------------------------------------------------------------
    // Refresh flow (RoomBoss)
    // -------------------------------------------------------------------------
    function collectRefreshItems(cart) {
        return (cart.items || []).map((it) => ({
            property_id:  it.hotel_type_id || it.property_id,
            room_id:      it.room_type_id || 0,
            rate_plan_id: it.rateplan_id || it.ratePlanId || '',
            check_in:     it.dates?.check_in || it.check_in || it.dates?.checkin || '',
            check_out:    it.dates?.check_out || it.check_out || it.dates?.checkout || '',
            adults:       it.guests?.adults || 1,
            children:     it.guests?.children || 0,
            infants:      it.guests?.infants || 0,
        }));
    }

    function refreshAvailability(button) {
        const cart = readCart();
        if (!cart.items || !cart.items.length) return;

        if (button) {
            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Refreshing…';

            const restoreButton = () => {
                button.disabled = false;
                button.innerHTML = originalHtml;
            };

            runRefresh(cart, restoreButton);
        } else {
            runRefresh(cart, null);
        }
    }

    function runRefresh(cart, restoreButton) {
        const items = collectRefreshItems(cart);
        if (typeof kv_object === 'undefined' || !kv_object.ajaxurl) {
            if (restoreButton) restoreButton();
            return;
        }

        const body = new URLSearchParams({
            action: 'kv_refresh_cart_availability',
            items:   JSON.stringify(items),
        });

        fetch(kv_object.ajaxurl + '?v=' + new Date().getTime(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
        })
        .then((r) => r.json())
        .then((res) => {
            if (!res || !res.success || !res.data) {
                throw new Error('invalid_response');
            }
            const data = res.data;
            const unavailable = Array.isArray(data.unavailable) ? data.unavailable : [];

            if (unavailable.length === 0) {
                // All still available - reset the timer and re-enable Proceed.
                startTimer(cart).then(() => {
                    setProceedVisible(true);
                    showRefresh(false);
                    // Restart the periodic tick so the countdown keeps moving
                    // after the timer is reset. Without this, the displayed
                    // value would freeze because updateTimerUI() only runs
                    // once here. startTick() is idempotent.
                    startTick();
                    updateTimerUI();
                    if (restoreButton) restoreButton();
                });
                return;
            }

            // Remove unavailable items by index.
            const removedIndexes = unavailable.map((u) => Number(u.index)).filter((n) => !isNaN(n));
            removedIndexes.sort((a, b) => b - a);
            const remaining = (cart.items || []).filter((_, idx) => !removedIndexes.includes(idx));

            const newCart = { items: remaining };
            writeCart(newCart);

            if (!remaining.length) {
                clearTimerMeta();
                showExpiredMessage('Unfortunately, the rooms in your cart are no longer available. Please re-select your rooms.');
                document.dispatchEvent(new CustomEvent('rb:cart-timer-expired', {
                    detail: { cart: newCart, type: 'roomboss' }
                }));
                document.dispatchEvent(new CustomEvent('rb:cart-updated', {
                    detail: { cart: newCart, refreshed: true }
                }));
            } else {
                // Reset the timer using the remaining cart.
                startTimer(newCart).then(() => {
                    setProceedVisible(true);
                    showRefresh(false);
                    // Same as above - restart the tick loop after reset.
                    startTick();
                    updateTimerUI();
                    document.dispatchEvent(new CustomEvent('rb:cart-updated', {
                        detail: { cart: newCart, refreshed: true }
                    }));
                });
            }

            if (restoreButton) restoreButton();
        })
        .catch(() => {
            if (restoreButton) restoreButton();
        });
    }

    // -------------------------------------------------------------------------
    // UI update loop
    // -------------------------------------------------------------------------
    let tickInterval = null;

    function updateTimerUI() {
        const cart = readCart();
        const item = getPrimaryItem(cart);
        if (!item) {
            hideTimerNode();
            return;
        }

        const meta = readTimerMeta();
        if (!meta) {
            // No meta yet - this happens during the async duration fetch
            // right after a room was added. Don't hide the node in that
            // case; just leave a placeholder countdown and try again on
            // the next tick. If the fetch never resolves, the timer still
            // shows "--:--" instead of disappearing.
            const placeholder = ensureTimerNode(cart);
            if (placeholder) {
                const cd = placeholder.querySelector('[data-role="countdown"]');
                if (cd && cd.textContent === '--:--') {
                    cd.textContent = '--:--';
                }
                placeholder.classList.remove('is-expired');
            }
            // Kick off (or resume) the duration fetch if it isn't already in
            // flight - this is idempotent because the timer dedupes itself.
            startTimer(cart);
            return;
        }

        const node = ensureTimerNode(cart);
        if (!node) {
            // Cart widget not rendered yet. Defer until mount() is called
            // (either by MutationObserver in init() or by rooms-section.js
            // after AJAX cart render).
            return;
        }

        const countdownEl = node.querySelector('[data-role="countdown"]');
        if (countdownEl) {
            countdownEl.textContent = formatRemaining(remainingMs(meta));
        }

        // console.log('Cart timer update: remaining ms =', remainingMs(meta), 'expired =', isExpired(meta));
        if (isExpired(meta)) {
            if (meta.is_bedbank) {
                handleBedbankExpiration(cart);
            } else {
                console.log('Cart timer expired (RoomBoss). Proceed buttons hidden, Refresh Availability shown.');
                handleRoombossExpiration(cart);
            }
            stopTick();
        } else {
            // Active countdown.
            node.classList.remove('is-expired');
            // If we were previously expired but now valid (e.g. after refresh),
            // restore the Proceed button.
            setProceedVisible(true);
            showRefresh(false);
        }
    }

    function hideTimerNode() {
        const node = document.querySelector(TIMER_SELECTOR);
        if (node) node.style.display = 'none';
    }

    /**
     * Mount the timer into the cart widget.
     *
     * Idempotent. Safe to call multiple times - duplicates are removed and
     * the timer node is re-parented into the correct container if needed.
     *
     * Call this after the cart widget has been rendered (e.g. from AJAX).
     */
    function mount() {
        const cart = readCart();
        if (!cart.items || !cart.items.length) {
            // No items yet - keep the timer hidden but ensure no <body>
            // fallback happened.
            hideTimerNode();
            return false;
        }

        const node = ensureTimerNode(cart);
        if (!node) {
            return false;
        }

        if (node.style) {
            node.style.display = '';
        }

        // Show the correct action button(s) for the current property type
        // (RoomBoss vs BedBank). Safe to call repeatedly.
        setProceedVisible(true);

        // Start the tick loop so the countdown updates each second.
        startTick();
        return true;
    }

    /**
     * Observe the DOM for the cart widget to appear, then mount the timer.
     *
     * Returns a `disconnect` function so callers can stop observing (e.g.
     * when navigating away from a property page).
     */
    function mountWhenReady(timeoutMs = 15000) {
        // Already mounted? Nothing to do.
        if (findCartContainer()) {
            mount();
            return () => {};
        }

        let timeoutId = null;
        const observer = new MutationObserver(() => {
            if (findCartContainer()) {
                observer.disconnect();
                if (timeoutId) clearTimeout(timeoutId);
                mount();
            }
        });
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });

        timeoutId = setTimeout(() => {
            observer.disconnect();
        }, timeoutMs);

        // Return a teardown function.
        return () => {
            observer.disconnect();
            if (timeoutId) clearTimeout(timeoutId);
        };
    }

    function startTick() {
        if (tickInterval) return;
        updateTimerUI();
        tickInterval = setInterval(updateTimerUI, 1000);
    }

    function stopTick() {
        if (tickInterval) {
            clearInterval(tickInterval);
            tickInterval = null;
        }
    }

    // -------------------------------------------------------------------------
    // Event wiring
    // -------------------------------------------------------------------------
    let mountObserverTeardown = null;

    function bindGlobalEvents() {
        // Refresh button click (delegated).
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-role="refresh"]');
            if (!target) return;
            e.preventDefault();
            refreshAvailability(target);
        });

        // When the cart is updated (item added/removed), re-evaluate the timer.
        document.addEventListener('rb:cart-updated', (e) => {
            const cart = (e && e.detail && e.detail.cart) ? e.detail.cart : readCart();
            if (!cart.items || !cart.items.length) {
                clearTimerMeta();
                hideTimerNode();
                setProceedVisible(false);
                return;
            }

            // Show the Proceed button(s) immediately so the customer can
            // continue without waiting on the (async) backend duration fetch.
            setProceedVisible(true);

            // Ensure the timer node exists in the DOM right away, even
            // before the async meta fetch completes. This prevents the
            // "timer doesn't appear until refresh" issue.
            const node = ensureTimerNode(cart);
            if (node && node.style) node.style.display = '';
            startTick();

            // Restart the timer (preserves duration for the same property_type).
            startTimer(cart).then(() => {
                updateTimerUI();
                startTick();
            });
        });

        // When the booking UI (cart widget) is freshly rendered via AJAX,
        // re-mount the timer into the new container.
        document.addEventListener('rb:booking-ui-ready', () => {
            mount();
        });

        // When items are programmatically removed via storage.
        window.addEventListener('storage', (e) => {
            if (e.key === STORAGE_KEY || e.key === TIMER_META_KEY) {
                updateTimerUI();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------
    function init() {
        bindGlobalEvents();

        const cart = readCart();
        if (!cart.items || !cart.items.length) {
            clearTimerMeta();
            hideTimerNode();
            return;
        }

        // If the cart widget is already in the DOM, mount immediately.
        if (findCartContainer()) {
            const meta = readTimerMeta();
            if (!meta) {
                startTimer(cart).then(() => mount());
            } else if (isExpired(meta)) {
                if (meta.is_bedbank) {
                    handleBedbankExpiration(cart);
                } else {
                    mount();
                }
            } else {
                mount();
            }
            return;
        }

        // Cart widget not yet in the DOM (e.g. Property Details page where
        // it loads via AJAX). Wait for it to appear, then mount. Never fall
        // back to <body>.
        if (mountObserverTeardown) {
            mountObserverTeardown();
            mountObserverTeardown = null;
        }
        mountObserverTeardown = mountWhenReady();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for testing and other modules.
    window.KVCartTimer = {
        init,
        mount,
        mountWhenReady,
        startTimer,
        refreshAvailability,
        isExpired,
        remainingMs,
        formatRemaining,
        DEFAULTS,
    };
})();
