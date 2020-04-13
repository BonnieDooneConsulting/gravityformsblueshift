<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
    die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms Blueshift Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Bonnie Doone Consulting
 */
class GFBlueshift extends GFFeedAddOn {

    /**
     * Contains an instance of this class, if available.
     *
     * @since  1.0
     * @access private
     * @var    object $_instance If available, contains an instance of this class.
     */
    private static $_instance = null;

    /**
     * Defines the version of the Blueshift Add-On.
     *
     * @since  1.0
     * @access protected
     * @var    string $_version Contains the version, defined from blueshift.php
     */
    protected $_version = GF_BLUESHIFT_VERSION;

    /**
     * Defines the minimum Gravity Forms version required.
     *
     * @since  1.0
     * @access protected
     * @var    string $_min_gravityforms_version The minimum version required.
     */
    protected $_min_gravityforms_version = '1.9.14.26';

    /**
     * Defines the plugin slug.
     *
     * @since  1.0
     * @access protected
     * @var    string $_slug The slug used for this plugin.
     */
    protected $_slug = 'gravityformsblueshift';

    /**
     * Defines the main plugin file.
     *
     * @since  1.0
     * @access protected
     * @var    string $_path The path to the main plugin file, relative to the plugins folder.
     */
    protected $_path = 'gravityformsblueshift/blueshift.php';

    /**
     * Defines the full path to this class file.
     *
     * @since  1.0
     * @access protected
     * @var    string $_full_path The full path.
     */
    protected $_full_path = __FILE__;

    /**
     * Defines the URL where this Add-On can be found.
     *
     * @since  1.0
     * @access protected
     * @var    string The URL of the Add-On.
     */
    protected $_url = 'http://www.oxfordclub.com';

    /**
     * Defines the title of this Add-On.
     *
     * @since  1.0
     * @access protected
     * @var    string $_title The title of the Add-On.
     */
    protected $_title = 'Gravity Forms Blueshift Feed Add-On';

    /**
     * Defines the short title of the Add-On.
     *
     * @since  1.0
     * @access protected
     * @var    string $_short_title The short title.
     */
    protected $_short_title = 'Blueshift';

    /**
     * Defines if Add-On should use Gravity Forms servers for update data.
     *
     * @since  1.0
     * @access protected
     * @var    bool
     */
    protected $_enable_rg_autoupgrade = false;
    /**
     * Defines the capability needed to access the Add-On settings page.
     *
     * @since  1.0
     * @access protected
     * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
     */
    protected $_capabilities_settings_page = 'gravityforms_blueshift';

    /**
     * Defines the capability needed to access the Add-On form settings page.
     *
     * @since  1.0
     * @access protected
     * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
     */
    protected $_capabilities_form_settings = 'gravityforms_blueshift';

    /**
     * Defines the capability needed to uninstall the Add-On.
     *
     * @since  1.0
     * @access protected
     * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
     */
    protected $_capabilities_uninstall = 'gravityforms_blueshift_uninstall';

    /**
     * Defines the capabilities needed for the Add-On
     *
     * @since  1.0
     * @access protected
     * @var    array $_capabilities The capabilities needed for the Add-On
     */
    protected $_capabilities = array( 'gravityforms_blueshift', 'gravityforms_blueshift_uninstall' );

    /**
     * Stores an instance of the Blueshift API library, if initialized.
     *
     * @since  1.0
     * @access protected
     * @var    object $api If initialized, an instance of the Blueshift API library.
     */
    public $api = null;

    /**
     * Stores an instance of the current mailing object from Blueshift
     *
     * @since  1.0
     * @access protected
     * @var    object $api If initialized, an instance of the Blueshift API library.
     */
    protected $current_mailing = null;

    /**
     * Get an instance of this class.
     *
     * @return GFBlueshift
     */
    public static function get_instance() {

        if ( null === self::$_instance ) {
            self::$_instance = new GFBlueshift();
        }

        return self::$_instance;

    }


    // # FEED PROCESSING -----------------------------------------------------------------------------------------------


    /**
     * Process the feed, send the mailing to Blueshift.
     *
     * @param array $feed The feed object to be processed.
     * @param array $entry The entry object currently being processed.
     * @param array $form The form object currently being processed.
     *
     * @return bool|void
     */
    public function process_feed( $feed, $entry, $form ) {

        $this->log_debug(__METHOD__ . '(): Processing feed.');

        /* If API instance is not initialized, exit. */
        if (!$this->initialize_api()) {
            $this->log_error(__METHOD__ . '(): Failed to set up the API.');
            return;
        }

        // Assemble the email content from the entry values and feed settings
        $template_uuid = $this->create_or_update_template($feed, $entry, $form);

        if ($template_uuid) {
            update_post_meta($feed['meta']['contentTemplate'], '_blueshift_template_uuid', $template_uuid);
            //$feed['blueshiftaddon_template_uuid'] = $template_uuid;
        }

        // Create a new mailing from the new content
        $campaign_uuid = $this->create_mailing_campaign($template_uuid, $feed, $entry, $form);

        if (isset($campaign_uuid)) {
            gform_update_meta($entry['id'], 'blueshiftaddon_campaign_uuid', $campaign_uuid);
            $entry['blueshiftaddon_campaign_uuid'] = $campaign_uuid;
        }

        //log the mailing we just kicked off
        if ($campaign_uuid) {
            GFCommon::log_debug(__METHOD__ . "(): Mailing " . print_r($campaign_uuid, true));
            return true;
        }

        return false;
    }

    /**
     * @param $feed
     * @param $entry
     * @param $form
     * @return bool
     */
    public function create_or_update_template($feed, $entry, $form) {
        // Get settings for content from feed settings and entry
        $content_html  = $this->get_filtered_field_value('contentBody',$feed, $entry, $form);
        $content_html  = $this->get_html_body($content_html, $feed, $entry, $form);
        $template_name = $this->get_filtered_field_value('contentName',$feed, $entry, $form);
        $subject       = $this->get_filtered_field_value('subjectLine',$feed, $entry, $form);

        //do we need to update or create?
        $template_uuid = get_post_meta($feed['meta']['contentTemplate'], '_blueshift_template_uuid', true);

        if ($template_uuid) {
            $template = $this->api->update_email_template($template_uuid, $template_name, $subject, $content_html);
        } else {
            $template = $this->api->create_email_template($template_name, $subject, $content_html);
        }

        if(!is_wp_error($template)) {
            return $template->uuid;
        } else {
            return false;
        }
    }

    /**
     * Create a campaign and attribute the template to it
     *
     * @param $template_uuid
     * @param $feed
     * @param $entry
     * @param $form
     * @return bool
     */
    public function create_mailing_campaign($template_uuid, $feed, $entry, $form) {
        $mailing_name = $this->get_filtered_field_value('mailingName',$feed, $entry, $form);

        $campaign_params = array(
            'name' => $mailing_name . '-' . strtotime('now'),
            'startdate' => date('c', strtotime(  'now +' . $feed['meta']['mailingDelay'] . ' minute')),
            'segment_uuid' => $feed['meta']['mailingSegment'],
            'triggers' => array(array(
                'template_uuid' => $template_uuid
            ))
        );

        //update this to create a blueshift campaign
        $mailing = $this->api->create_campaign($campaign_params);

        if(!is_wp_error($mailing)) {
            return $mailing->campaign->uuid;
        } else {
            return false;
        }
    }

    /**
     * @param $content_html
     * @param $feed
     * @param $entry
     * @param $form
     * @return mixed
     */
    public function get_html_body($content_html, $feed, $entry, $form) {
        // Return unaltered $content_html if no template is selected
        if(rgblank($feed['meta']['contentTemplate'])) {
            $this->log_debug( __METHOD__ . '(): No template is set. Return content unaltered.' );
            return $content_html;
        } else {

            $template_post_id = $feed['meta']['contentTemplate'];
            $this->log_debug( __METHOD__ . '(): $template_post_id ' . print_r($template_post_id, true) );
            $template_post = get_post($template_post_id);
            $content = $template_post->post_content;
            $combined_content = str_ireplace("[FEEDCONTENT]",$content_html,$content);
            $content =  preg_replace( '/(^|[^\n\r])[\r\n](?![\n\r])/', '$1 ', $combined_content);
            return $content;
        }
    }

    /**
     * @param $name
     * @param $feed
     * @param $entry
     * @param $form
     * @return string
     */
    public function get_filtered_field_value($name, $feed, $entry, $form ) {
        return do_shortcode(GFCommon::replace_variables( $feed['meta'][$name], $form, $entry, false, false, false, 'text' ));
    }

    // # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

    /**
     * Plugin starting point. Handles hooks, loading of language files.
     */
    public function init() {
        parent::init();
        add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'register_meta_box' ), 10, 3 );
    }

    /**
     * Get entry meta from the
     * @param array $entry_meta
     * @param int $form_id
     * @return array
     */
    public function get_entry_meta( $entry_meta, $form_id ) {
        $entry_meta['blueshiftaddon_template_uuid']   = array(
            'label'                      => 'Blueshift Template ID',
            'is_numeric'                 => true,
            'is_default_column'          => true,
            'update_entry_meta_callback' => array( $this, 'update_entry_meta' ),
            'filter'         => array(
                'operators' => array( 'is', 'isnot', '>', '<' )
            )
        );

        $entry_meta['blueshiftaddon_campaign_uuid']   = array(
            'label'                      => 'Blueshift Campaign ID',
            'is_numeric'                 => true,
            'is_default_column'          => true,
            'update_entry_meta_callback' => array( $this, 'update_entry_meta' ),
            'filter'         => array(
                'operators' => array( 'is', 'isnot', '>', '<' )
            )
        );
        return $entry_meta;
    }

    public function update_entry_meta( $key, $lead, $form ) {
        return "";
    }

    /**
     * Add a meta box to the entry detail page.
     *
     * @param array $meta_boxes The properties for the meta boxes.
     * @param array $entry The entry currently being viewed/edited.
     * @param array $form The form object used to process the current entry.
     *
     * @return array
     */

    function register_meta_box($feed, $entry, $form) {
        // If the form has an active feed belonging to this add-on and the API can be initialized, add the meta box.
        if ( $this->get_active_feeds( $form['id'] ) && $this->initialize_api() ) {
            $meta_boxes[ $this->_slug ] = array(
                'title'    => $this->get_short_title(),
                'callback' => array( $this, 'add_details_meta_box' ),
                'context'  => 'side',
                'callback_args' => array($feed, $entry, $form)
            );
        }

        return $meta_boxes;
    }

    /**
     * The callback used to echo the content to the meta blueshift box.
     *
     * @param array $args An array containing the form and entry objects.
     */
    public function add_details_meta_box( $args ) {

        $form  = $args['form'];
        $entry = $args['entry'];

        $html   = '';
        $action = $this->_slug . '_process_feeds';

        // Retrieve the content id from the current entry, if available.
        //$content_id = rgar( $entry, 'blueshiftaddon_template_uuid' );
        $mailing_id = rgar( $entry, 'blueshiftaddon_campaign_uuid' );

        if ( (empty( $content_id ) || empty( $mailing_id )) && rgpost( 'action' ) == $action ) {
            check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

            // Because the entry doesn't already have a content id and the 'Process Feeds' button was clicked process the feeds.
            $entry = $this->maybe_process_feed( $entry, $form );

            // Retrieve the content id from the updated entry.
            $content_id = rgar( $entry, 'blueshiftaddon_template_uuid' );
            $mailing_id = rgar( $entry, 'blueshiftaddon_campaign_uuid' );

            $html .= esc_html__( 'Feeds Processed.', 'gravityformsblueshift' ) . '</br></br>';
        }

        if ( empty( $content_id ) || empty( $mailing_id ) ) {

            // Add the 'Process Feeds' button.
            $html .= sprintf( '<input type="submit" value="%s" class="button" onclick="jQuery(\'#action\').val(\'%s\');" />', esc_attr__( 'Process Feeds', 'gravityformsblueshift' ), $action );

        } else {

            // Display the content ID and mailing ID.

//            $html .= '<p><a href="https://mc2.agora-inc.com/content/preview?contentid=' . $content_id .'" target="_blank">';
//            $html .= esc_html__( 'Content ID', 'gravityformsblueshift' ) . ': ' . $content_id;
//            $html .= "</a></p>";

            // Link to the content aand mailing in Blueshift
//            $html .= '<p><a href="https://mc2.agora-inc.com/mailings/overview?mailingid=' . $mailing_id .'" target="_blank">';
//            $html .= esc_html__( 'Mailing ID', 'gravityformsblueshift' ) . ': ' . $mailing_id;
//            $html .= "</a></p>";

        }
        echo $html;
    }

    /**
     * Return the stylesheets which should be enqueued.
     *
     * @return array
     */
    public function styles() {
        $styles = array(
            array(
                'handle'  => 'gform_blueshift_form_settings_css',
                'src'     => $this->get_base_url() . "/css/admin-segment-settings.css",
                'version' => $this->_version,
                'enqueue' => array(
                    array( 'admin_page' => array( 'plugin_settings' ) ),
                ),
            ),
        );
        return array_merge( parent::styles(), $styles );
    }

    public function scripts() {
        $scripts = array(
            array(
                'handle'    => 'admin_segment_settings',
                'src'       => $this->get_base_url() . '/js/admin-segment-settings.js',
                'version'   => $this->_version,
                'deps'      => array( 'jquery' ),
                'in_footer' => false,
                'enqueue'   => array(
                    array(
                        'admin_page' => array( 'plugin_settings' ),
                    )
                )
            ),
            array(
                'handle'    => 'admin_feed_schedule_settings',
                'src'       => $this->get_base_url() . '/js/admin-feed-schedule-settings.js',
                'version'   => $this->_version,
                'deps'      => array( 'jquery' ),
                'in_footer' => false,
                'enqueue'   => array(
                    array(
                        'admin_page' => array( 'form_settings' ),
                    )
                )
            ),
        );
        return array_merge( parent::scripts(), $scripts );
    }

    /**
     * Configures the settings which should be rendered on the add-on settings tab.
     *
     * @return array
     */
    public function plugin_settings_fields() {
        return array(
            array(
                'title'       => 'Blueshift API Settings',
                'description' => $this->plugin_settings_description(),
                'fields'      => array(
                    array(
                        'name'              => 'api_url',
                        'label'             => esc_html__( 'API URL', 'gravityformsblueshift' ),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array( $this, 'has_valid_api_url' ),
                        'default_value'     => 'https://api.getblueshift.com/api/v1'
                    ),
                    //we may have to account for the event and user api keys here at some point
                    array(
                        'name'              => 'api_key',
                        'label'             => esc_html__( 'API Key', 'gravityformsblueshift' ),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array( $this, 'initialize_api' )
                    ),
                    array(
                        'type'     => 'save',
                        'messages' => array(
                            'success' => esc_html__( 'Blueshift settings have been updated.', 'gravityformsblueshift' )
                        ),
                    ),
                ),
            ),
            array(
                'title'       => 'Blueshift Segment Settings',
                'description' => 'Add in segment uuids to be used in the Blueshift form feed',
                'fields'      => array(
                    array(
                        //add in a callback to validate the strings
                        'type'              => 'blueshift_segment_map_field_type',
                        'name'              => 'blueshift_segment_map'
                    ),
                    array(
                        'type'     => 'save',
                        'messages' => array(
                            'success' => esc_html__( 'Blueshift settings have been updated.', 'gravityformsblueshift' )
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Get the dynamic segment settings and admin form
     */
    public function settings_blueshift_segment_map_field_type(){
        $segment_settings = $this->get_plugin_setting('blueshift_segment_map');

        if (!$segment_settings) {
            $segment_settings = array();
        }
        include('views/admin-segment-settings.php');
    }

    /**
     * Prepare plugin settings description.
     *
     * @return string
     */
    public function plugin_settings_description() {

        $description = '<p>';
        $description .=
            esc_html__( 'Blueshift makes it easy to send email newsletters to your customers, manage your subscriber segments, and track campaign performance. Use Gravity Forms to send email broadcasts to your Blueshift segments.', 'gravityformsblueshift' );
        $description .= '</p>';

        if ( ! $this->initialize_api() ) {

            $description .= '<p>';
            $description .= esc_html__( 'Gravity Forms Blueshift Add-On requires your API URL and API Key. Contact 14 West Support to obtain API credentials.', 'gravityformsblueshift' );
            $description .= '</p>';

        }

        return $description;

    }

    /**
     * Checks validity of Blueshift API credentials and initializes API if valid.
     *
     * @return bool|null
     */
    public function initialize_api() {

        if ( ! is_null( $this->api ) ) {
            return true;
        }

        /* Load the Blueshift API library. */
        require_once 'classes/class-gf-blueshift-api.php';

        /* Get the plugin settings */
        $settings = $this->get_plugin_settings();

        /* If any of the account information fields are empty, return null. */
        if ( rgblank( $settings['api_url'] ) || rgblank( $settings['api_key']) ) {
            return null;
        }

        // Test API URL.
        $valid_api_url = $this->has_valid_api_url( $settings['api_url'] );
        if ( ! $valid_api_url ) {
            return false;
        }

        $this->log_debug( __METHOD__ . "(): Validating API info for {$settings['api_url']} / {$settings['api_key']}." );
        $blueshift = new GF_Blueshift_API( $settings['api_url'], $settings['api_key']);

        try {
            /* Run API test. */
            $blueshift->auth_test();
            /* Log that test passed. */
            $this->log_debug( __METHOD__ . '(): API credentials are valid.' );
            /* Assign Blueshift object to the class. */
            $this->api = $blueshift;
            return true;
        } catch ( Exception $e ) {
            /* Log that test failed. */
            $this->log_error( __METHOD__ . '(): API credentials are invalid; ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Checks if API URL is valid.
     *
     * @param string $api_url The API URL.
     *
     * @return bool|null
     */
    public function has_valid_api_url( $api_url ) {

        /* If no API URL is set, return null. */
        if ( rgblank( $api_url ) ) {
            return null;
        }

        $this->log_debug( __METHOD__ . "(): Validating API url {$api_url}." );

        /* Get the trimmed up url */
        $request_url = untrailingslashit( $api_url );

        /* Just validate that it's RFC compliant and has a hostname, we can't really ping the client */
        $is_valid_url = filter_var($request_url, FILTER_VALIDATE_URL);

        /* If there was a failure on the request, return false. */
        if ($is_valid_url) {
            return true;
        }
        return false;
    }

    // ------- Feed page -------

    /**
     * Prevent feeds being listed or created if the api key isn't valid.
     *
     * @return bool
     */
    public function can_create_feed() {
        return $this->initialize_api();
    }

    /**
     * Enable feed duplication.
     *
     * @access public
     * @return bool
     */
    public function can_duplicate_feed( $id ) {
        return true;
    }

    /**
     * If the api keys are invalid or empty return the appropriate message.
     *
     * @return string
     */
    public function configure_addon_message() {

        $settings_label = sprintf( esc_html__( '%s Settings', 'gravityforms' ), $this->get_short_title() );
        $settings_link  = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

        if ( is_null( $this->initialize_api() ) ) {

            return sprintf( esc_html__( 'To get started, please configure your %s.', 'gravityforms' ), $settings_link );
        }
        return sprintf( esc_html__( 'Please make sure you have entered valid API credentials on the %s page.', 'gravityformsblueshift' ), $settings_link );
    }

    /**
     * Configures which columns should be displayed on the feed list page.
     *
     * @return array
     */
    public function feed_list_columns() {
        return array(
            'feed_name' => esc_html__( 'Name', 'gravityformsblueshift' ),
            'mailingSegment'      => esc_html__( 'Blueshift Segment', 'gravityformsblueshift' ),
        );
    }

    /**
     * Configures the settings which should be rendered on the feed edit page.
     *
     * @return array The feed settings.
     */
    public function feed_settings_fields() {
        /* Build fields array. */
        $base_fields = array(
            array(
                'name'          => 'feed_name',
                'label'         => esc_html__( 'Feed Name', 'gravityformsblueshift' ),
                'type'          => 'text',
                'required'      => true,
                'default_value' => $this->get_default_feed_name(),
                'class'         => 'medium',
                'tooltip'       => '<h6>' . esc_html__( 'Name', 'gravityformsblueshift' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsblueshift' ),
            ),
        );

        // Build conditional logic fields.
        $conditional_fields = array(
            array(
                'fields' => array(
                    array(
                        'name'           => 'feedCondition',
                        'type'           => 'feed_condition',
                        'label'          => esc_html__( 'Conditional Logic', 'gravityformsblueshift' ),
                        'checkbox_label' => esc_html__( 'Enable', 'gravityformsblueshift' ),
                        'instructions'   => esc_html__( 'Create if', 'gravityformsblueshift' ),
                        'tooltip'        => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Conditional Logic', 'gravityformsblueshift' ),
                            esc_html__( 'When conditional logic is enabled, form submissions will only be created when the condition is met. When disabled, all form submissions will be created.', 'gravityformsblueshift' )
                        ),
                    ),
                ),
            ),
        );
        $create_post_fields = $this->feed_settings_fields_create_post();
        return array_merge( $base_fields, $create_post_fields, $conditional_fields );
    }

    /**
     * Setup fields for post creation feed settings.
     *
     * @since  1.0
     * @access public
     *
     * @uses GFAddOn::get_current_settings()
     * @uses GFAddOn::add_field_after()
     *
     * @return array
     */
    public function feed_settings_fields_create_post() {

        // Get current feed settings and form object.
        $settings = $this->get_current_settings();
        $form     = $this->get_current_form();

        // Prepare post date setting choices.
        $post_date_choices = array(
            array(
                'label' => esc_html__( 'Entry Date', 'gravityformsblueshift' ),
                'value' => 'entry',
            ),
            array(
                'label' => esc_html__( 'Date & Time Fields', 'gravityformsblueshift' ),
                'value' => 'field',
            ),
            array(
                'label' => esc_html__( 'Custom Date & Time', 'gravityformsblueshift' ),
                'value' => 'custom',
            ),
        );

        // Remove Date & Time Fields choice if no Date or Time fields are found.
        if ( ! GFAPI::get_fields_by_type( $form, array( 'date', 'time' ) ) ) {
            unset( $post_date_choices[1] );
        }

        // Setup fields array.
        $fields = array(
            'feed'  => array(
                'title'  => esc_html__( 'Feed Settings', 'gravityformsblueshift' ),
                'fields' => array(
                    array(
                        'name'          => 'feed_name',
                        'label'         => esc_html__( 'Feed Name', 'gravityformsblueshift' ),
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'medium',
                    ),
                    array(
                        'name'     => 'mailingSegment',
                        'label'    => esc_html__( 'Blueshift Segment', 'gravityformsblueshift' ),
                        'type'     => 'select',
                        'required' => true,
                        'choices'  => $this->segments_for_feed_setting(),
                        'onchange' => "jQuery(this).parents('form').submit();",
                        'tooltip'  => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Blueshift Segment', 'gravityformsblueshift' ),
                            esc_html__( 'Select one of the defined Blueshift segments for the mailing.', 'gravityformsblueshift' )
                        ),
                    )
                ),
            ),
            'content'  => array(
                'title'  => esc_html__( 'Content Settings', 'gravityformsblueshift' ),
                'fields' => array(
                    array(
                        'name'                => 'contentName',
                        'label'               => esc_html__( 'Name', 'gravityformsblueshift' ),
                        'type'                => 'text',
                        'required'            => true,
                        'class'               => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                        'validation_callback' => array($this, 'check_if_template_exists')
                    ),
                    array(
                        'name'          => 'contentDescription',
                        'label'         => esc_html__( 'Description', 'gravityformsblueshift' ),
                        'type'          => 'textarea',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                    ),
                    array(
                        'name'          => 'subjectLine',
                        'label'         => esc_html__( 'Subject', 'gravityformsblueshift' ),
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                    ),
                    array(
                        'name'     => 'contentTemplate',
                        'label'    => esc_html__( 'Content Template', 'gravityformsblueshift' ),
                        'type'     => 'select',
                        'required' => true,
                        'choices'  => $this->get_templates_for_feed_setting(),
                        'tooltip'  => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Content Template', 'gravityformsblueshift' ),
                            esc_html__( 'Select an email template to use for this feed.', 'gravityformsblueshift' )
                        ),
                    ),
                    array(
                        'name'          => 'contentBody',
                        'label'         => esc_html__( 'HTML Content', 'gravityformsblueshift' ),
                        'type'          => 'textarea',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right',
                        'tooltip'       => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Content Body', 'gravityformsblueshift' ),
                            esc_html__( "Define the content body for the email message.", 'gravityformsblueshift' )
                        ),

                    ),
                    array(
                        'name'          => 'contentPlaintextBody',
                        'label'         => esc_html__( 'Plaintext Content', 'gravityformsblueshift' ),
                        'type'          => 'textarea',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right',
                        'tooltip'       => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Plaintext Content', 'gravityformsblueshift' ),
                            esc_html__( "Define the Plaintext content for the email message.", 'gravityformsblueshift' )
                        ),

                    )
                ),
            ),
            'settings' => array(
                'id'     => 'mailingSettings',
                'title'  => esc_html__( 'Mailing Settings', 'gravityformsblueshift' ),
                'fields' => array(
                    array(
                        'name'          => 'mailingName',
                        'label'         => esc_html__( 'Mailing Name', 'gravityformsblueshift' ),
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                    ),
                    array(
                        'name'          => 'mailingDescription',
                        'label'         => esc_html__( 'Description', 'gravityformsblueshift' ),
                        'type'          => 'textarea',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                    ),
                    array(
                        'name'     => 'mailingType',
                        'label'    => esc_html__( 'Type', 'gravityformsblueshift' ),
                        'type'     => 'select',
                        'required' => true,
                        'class'    => 'mailing-type',
                        'choices'  => array(
                            array(
                                'label' => esc_html__( 'Immediate', 'gravityformsblueshift' ),
                                'value' => 'immediate',
                            ),
                            array(
                                'label' => esc_html__( 'Scheduled', 'gravityformsblueshift' ),
                                'value' => 'scheduled',
                            ),
                        ),
                        'tooltip'  => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Mailing Type', 'gravityformsblueshift' ),
                            esc_html__( 'Select one of the defined Blueshift mailing types for the mailing.', 'gravityformsblueshift' )
                        ),
                    ),
                    array(
                        'name'          => 'mailingDelay',
                        'label'         => esc_html__( 'Mailing Delay in Minutes', 'gravityformsblueshift' ),
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'small mailing-delay',
                        'hidden'        => true,
                        'default_value' => 0,
                        'tooltip'  => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Mailing Delay in Minutes', 'gravityformsblueshift' ),
                            esc_html__( 'Enter the number of minutes to delay the mailing before sending.', 'gravityformsblueshift' )
                        )
                    ),
                ),
            ),
        );
        return $fields;
    }

    /**
     * Check to make sure no other feed and template are using this name
     *
     * @param $template_name
     *
     * @return void
     */
    public function check_if_template_exists($field, $template_name) {
        $list = $this->api->list_email_templates();
        $settings = $this->get_posted_settings();

        $current_template_uuid = get_post_meta($settings['contentTemplate'], '_blueshift_template_uuid', true);

        if (is_wp_error($list)) {
            //we can't check because the api is down, the template name may not be valid?
            $this->set_field_error( array('name' => 'contentName'), sprintf(esc_html__( 'The blueshift API is unavailable, please check back later for validation errors', 'gravityformsblueshift')));
            return;
        }

        foreach($list->templates as $template) {
            if ($template->uuid == $current_template_uuid) {
                continue;
            }

            if (isset($template->name) && $template->name == $template_name) {
                $this->set_field_error( array('name' => 'contentName'), sprintf(esc_html__( 'This template name is already in use, please use a different one.', 'gravityformsblueshift')));
                return;
            }
        }
        return;
    }

    /**
     * Fork of maybe_save_feed_settings to create new Blueshift custom fields.
     *
     * @param int $feed_id The current Feed ID.
     * @param int $form_id The current Form ID.
     *
     * @return int
     */
    public function maybe_save_feed_settings( $feed_id, $form_id ) {

        if ( ! rgpost( 'gform-settings-save' ) ) {
            return $feed_id;
        }

        // store a copy of the previous settings for cases where action would only happen if value has changed
        $feed = $this->get_feed( $feed_id );
        $this->set_previous_settings( $feed['meta'] );

        $settings = $this->get_posted_settings();
        $sections = $this->get_feed_settings_fields();
        $settings = $this->trim_conditional_logic_vales( $settings, $form_id );

        $is_valid = $this->validate_settings( $sections, $settings );

        if ( $is_valid ) {
            $feed_id = $this->save_feed_settings( $feed_id, $form_id, $settings );
            if ( $feed_id ) {
                GFCommon::add_message( $this->get_save_success_message( $sections ) );
            } else {
                GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
            }
        } else {
            GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
        }

        return $feed_id;
    }

    /**
     * Prepare Blueshift segments for feed field
     *
     * @return array
     */
    public function segments_for_feed_setting() {

        $segments = array(
            array(
                'label' => esc_html__( 'Select a Segement', 'gravityformsblueshift' ),
                'value' => ''
            )
        );

        /* Get available Blueshift segments. */
        $blueshift_segments = $this->get_plugin_setting('blueshift_segment_map');
        /* Add Blueshift segments to array and return it. */
        foreach ( $blueshift_segments as $segment ) {

            $segments[] = array(
                'label' => $segment['name'],
                'value' => $segment['segmentid']
            );

        }
        return $segments;
    }

    /**
     * Prepare Wordpress Blueshift Email Templates for feed field
     *
     * @return array
     */
    public function get_templates_for_feed_setting() {

        $this->log_debug( __METHOD__ . '(): Query wordpress for gfblueshifttemplate post_type entries' );

        $templates = array(
            array(
                'label' => esc_html__( 'Select a Template', 'gravityformsblueshift' ),
                'value' => ''
            )
        );

        $args = array(
            'post_type' => 'gfblueshifttemplate'
        );

        $query = new WP_Query($args);

        // $this->log_debug( __METHOD__ . '(): $query'. print_r($query, true) );

        if($query->have_posts()) {
            while ($query->have_posts()){
                $query->the_post();
                $templates[] = array(
                    'label' => get_the_title(),
                    'value' => get_the_ID(),
                );
            }
        }

        wp_reset_postdata();

        return $templates;

    }
}

add_action('init', 'register_blueshift_post_type', 1);

function register_blueshift_post_type() {
    $post_type = 'gfblueshifttemplate';

    $args = array(
        'label' => 'Blueshift Templates',
        'exclude_from_search' => true,
        'public' => false,
        'publicly_queryable' => false,
        'query_var' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'has_archive' => false,
        'register_meta_box_cb' => 'blueshift_template_uuid_meta_box'
    );

    if(!post_type_exists($post_type)) {
        register_post_type($post_type, $args);
    }
}

function blueshift_template_uuid_meta_box() {

    add_meta_box(
        'blueshift-template-uuid',
        __( 'Blueshift Template uuid', 'gravityformsblueshift' ),
        'blueshift_template_uuid_meta_box_callback',
        'gfblueshifttemplate',
        'side'
    );
}

add_action( 'add_meta_boxes', 'template_uuid_meta_box' );

function blueshift_template_uuid_meta_box_callback( $post ) {

    // Add a nonce field so we can check for it later.
    wp_nonce_field( 'blueshift_template_uuid_nonce', 'blueshift_template_uuid_nonce' );
    $value = get_post_meta( $post->ID, '_blueshift_template_uuid', true );
    echo '<input type="text" style="width:100%" id="blueshift_template_uuid" name="blueshift_template_uuid" disabled value="' . esc_attr( $value ). '">';
}

function save_blueshift_template_uuid_meta_box_data( $post_id ) {

    // Check if our nonce is set.
    if ( !isset( $_POST['blueshift_template_uuid_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_POST['blueshift_template_uuid_nonce'], 'blueshift_template_uuid_nonce' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( isset( $_POST['post_type'] ) && 'gfblueshifttemplate' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }
    }
    else {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }

    $content = sanitize_text_field($_POST['content']);
    $template_name = sanitize_text_field($_POST['post_title']);
    $subject = 'New Template Validation';
    $blueshift = GFBlueshift::get_instance();
    $blueshift->initialize_api();

    if (isset( $_POST['blueshift_template_uuid'] ) ) {
        //create it in blueshift
        $template = $blueshift->api->update_email_template(sanitize_text_field($_POST['blueshift_template_uuid']), $template_name, $subject, $content);
    } else {
        //update it in blueshift
        $template = $blueshift->api->create_email_template($template_name, $subject, $content);
    }

    if (isset($template->uuid)) {
        update_post_meta( $post_id, '_blueshift_template_uuid', $template->uuid );
    } elseif (is_wp_error($template)) {
        //we have validation issues, set an error
        set_transient("blueshift_template_validation_errors_{$post_id}", json_encode($template->errors, JSON_PRETTY_PRINT), 45);
    }
}
add_action( 'save_post', 'save_blueshift_template_uuid_meta_box_data' );

function blueshift_template_validation_error() {
    $post = get_post();
    if ( $error = get_transient( "blueshift_template_validation_errors_{$post->ID}" ) ) { ?>
        <div class="error">
            <pre><?php echo $error; ?></pre>
        </div><?php

        delete_transient("blueshift_template_validation_errors_{$post->ID}");
    }
}
add_action( 'admin_notices', 'blueshift_template_validation_error' );
