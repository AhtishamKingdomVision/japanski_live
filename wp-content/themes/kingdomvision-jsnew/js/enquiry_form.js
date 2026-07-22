jQuery(document).on('submit', '.gform_wrapper form', function(e) {
    var $form = jQuery(this);
    var $submitButton = $form.find('input[type="submit"], button[type="submit"]');
    
    if ($submitButton.prop('disabled')) return;

    // 1. Save original text/value so we can restore it later
    var isInput = $submitButton.is('input');
    var originalText = isInput ? $submitButton.val() : $submitButton.html();
    $submitButton.data('original-text', originalText);

    // 2. Clear text, lock button, and add loading class
    $submitButton.prop('disabled', true).addClass('button-loading');
    
    if (isInput) {
        $submitButton.val('Please wait...');
    } else {
        $submitButton.html('Please wait...');
    }

    // 3. Inject Font Awesome spinner inside the button
    var faLoader = '<img src="'+base_url+'/wp-content/themes/kingdomvision-jsnew/images/spinning-dots.svg" class="kv_gform_loader">';
    $submitButton.append(faLoader);
});

// 4. Fully restore the button if validation fails or form reloads
jQuery(document).on('gform_post_render', function(e, formId) {
    var $form = '#gform_' + formId;
    var $submitButton = jQuery($form).find('input[type="submit"], button[type="submit"]');
    var originalText = $submitButton.data('original-text');

    if (originalText) {
        $submitButton.prop('disabled', false).removeClass('button-loading');
        
        if ($submitButton.is('input')) {
            $submitButton.val(originalText);
        } else {
            $submitButton.html(originalText);
        }
    }
});
