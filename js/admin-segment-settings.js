jQuery(function(){
    jQuery('#add-new-segment').click(function(event) {
        event.preventDefault();
        $count = jQuery('#segment-codes-form tbody tr').length;
        $newElement = jQuery('#new-form-row').html();
        jQuery('#segment-codes-form tbody').append('<tr> <td>' +
            '<input type="text" name="_gaddon_setting_blueshift_segment_map[' + $count + '][name]" value="" class="gaddon-setting gaddon-text" id="blueshift_segment_map[' + $count + '][name]">\
            <input type="text" name="_gaddon_setting_blueshift_segment_map[' + $count + '][segmentid]" value="" class="medium gaddon-setting gaddon-text" id="blueshift_segment_map[' + $count + '][segmentid]">\
            </td> <td> <a class="delete-segment"><span class="dashicons dashicons-trash"></span> </a> </td> </tr>');
    });

    jQuery('.delete-segment span').click(function(event) {
        event.preventDefault();
        $count = jQuery('#segment-codes-form tbody tr').length;
        //need to clean up validation here and behavior of icons
        if ($count != 1 ) {
            jQuery(this).closest('tr').remove();
        }
    });
});


