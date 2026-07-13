<?php
/**
 * Template Name: Booking Confirmation
 * 
 * Cart data is managed entirely via JavaScript and localStorage.
 * The cart is populated during the room selection flow and displayed here
 * via booking-manager.js which reads from localStorage key 'rb_cart'.
 */
get_header();
?>
<link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/css/room-booking-manager.css?v=<?php echo filemtime(get_stylesheet_directory() .'/css/room-booking-manager.css'); ?>">
<style>
    #gform_submit_button_3 { display: none; }
    /* .rb-pay-btn, .rb-book-now-btn { border-radius: 0px 0px 20px 20px; width: 100%; } */
    /* .rb-book-now-btn { width: 100%; } */
</style>
<div class="content-wrapper full-section transparent">
<section class="full-section booking_page">
    <div class="container booking_page">

        <div class="rb-booking-wrapper">

            <!-- LEFT COLUMN -->
            <div class="rb-left-column">
                <!-- ACCOMMODATIONS TABLE -->
                <div id="booking-container"></div>

                <!-- NEXT STEPS (dynamically populated by JS based on property type) -->
                <div id="rb-next-steps-container" class="rb-next-steps-box" style="background: #2d1f2e; border: 1px solid #dd363e; border-radius: 16px; padding: 24px 28px; margin-bottom: 24px; color: #fff; display: none;">
                    <!-- Content injected by booking-manager.js -->
                </div>

                <!-- FORM -->
                <div class="booking-confirmation-form">
                    <h3>Enter your details</h3>
                    <?php echo do_shortcode('[gravityform id="3" title="false" description="false" ajax="true"]'); ?>
                    <div class="rb-total-deposit-bar">
                        <span>Total Deposit to Pay</span>
                        <span id="rb-total-deposit-display">¥0</span>
                    </div>
                    <button class="rb-pay-btn">Proceed to Payment</button>
                    <button class="rb-book-now-btn" style="display: none;">Enquire Now</button>
                </div>
                <!-- <div id="flywire_box"></div> -->

            </div>

            <!-- RIGHT COLUMN -->
            <div class="rb-right-column">

                <div class="rb-summary-box" id="rb-booking-details-widget">
                    <!-- Dynamically populated by booking-manager.js -->
                </div>

                <!-- Hidden span targets kept for legacy JS compatibility -->
                <span id="summary-bookings" style="display:none;">1</span>
                <span id="summary-guests" style="display:none;">–</span>
                <span id="summary-checkin" style="display:none;">–</span>
                <span id="summary-checkout" style="display:none;">–</span>
                <span id="summary-duration" style="display:none;">–</span>
                <span class="room-total-price" data-price="0" style="display:none;">¥0</span>
                <span id="summary-total-deposit" style="display:none;">¥0</span>
                <span id="summary-total-balance" style="display:none;">¥0</span>

            </div>

        </div>

    </div>
</section>

<!-- Room Details Modal -->
<section class="room-listing" style="display:none;">
    <div id="rb-room-modal" class="room-modal">
        <div class="room-modal-content">
            <button class="rb-room-modal-close room-modal-close">&times;</button>
            <div id="rb-room-modal-body"></div>
        </div>
    </div>
</section>

<script>
(function() {
    'use strict';
    
    /**
     * Check if cart is empty and redirect to home page
     */
    const checkCartAndRedirect = () => {
        try {
            const cartData = localStorage.getItem('rb_cart');
            const cart = cartData ? JSON.parse(cartData) : null;
            const items = cart && Array.isArray(cart.items) ? cart.items : [];
            
            if (items.length === 0) {
                console.log('Cart is empty, redirecting to home page');
                window.location.href = '/';
                return true;
            }
            console.log('Cart has items, staying on confirmation page: ', items);
            return false;
        } catch (e) {
            console.error('Error checking cart:', e);
            return false;
        }
    };
    
    // Check on page load - redirect if cart is empty
    if (checkCartAndRedirect()) {
        return;
    }
    
    // Listen for cart updates and redirect if cart becomes empty
    document.addEventListener('rb:cart-updated', function(e) {
        const cart = e.detail?.cart;
        if (!cart || !cart.items || cart.items.length === 0) {
            console.log('Cart emptied, redirecting to home page');
            setTimeout(() => {
                window.location.href = '/';
            }, 500);
        }
    });
})();
</script>

<?php get_footer(); ?>