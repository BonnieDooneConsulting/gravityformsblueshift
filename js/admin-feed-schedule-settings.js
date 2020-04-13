jQuery(function() {
    jQuery('.mailing-type').change(function() {
        updateMailingDelayVis();
    });

    function updateMailingDelayVis() {
        $mailingDelay = jQuery('.mailing-delay').closest('tr');

        if(jQuery('.mailing-type').val() === 'scheduled') {
            $mailingDelay.show(500);
        } else {
            $mailingDelay.hide(500);
        }
    }

    updateMailingDelayVis();
});