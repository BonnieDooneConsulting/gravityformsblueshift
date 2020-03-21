<table class="" id="segment-codes-form">
    <thead>
    <tr class="label-heading">
        <td>
            <div class="segment-codes-header">
                Segment Name
            </div>
            <div class="segment-codes-header">
                Segment UUID
            </div>
        </td>
    </tr>
    </thead>
    <tbody>
    <?php foreach($segment_settings as $index=>$segment_map) : ?>
        <tr>
            <td>
                <?php
                $this->settings_text(
                    array(
                        'label'         => 'Segment Name',
                        'name'          => "blueshift_segment_map[$index][name]",
                        'default_value' => 'Segment Name',
                    )
                );
                $this->settings_text(
                    array(
                        'label'         => 'Segment uuid',
                        'name'          => "blueshift_segment_map[$index][segmentid]",
                        'class'         => 'medium',
                        'default_value' => 'Segment uuid',
                    )
                );
                ?>
            </td>
            <td>
                <a class="delete-segment"><span class="dashicons dashicons-trash"></span> </a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<button id="add-new-segment" class="button button-primary">Add Segment</span></button>
