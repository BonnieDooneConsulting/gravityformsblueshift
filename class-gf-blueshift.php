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
    protected $api = null;

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
        $content_id = $this->create_content($feed, $entry, $form);

        if (is_numeric($content_id)) {
            gform_update_meta($entry['id'], 'blueshiftaddon_content_id', $content_id);
            $entry['blueshiftaddon_content_id'] = $content_id;
        }

        // Associate the new content with the current list
        $content_is_associated = $this->associate_content_with_list($content_id, $feed);

        // Create a new mailing from the new content
        if ($content_is_associated) {
            $mailing_id = $this->create_mailing($content_id, $feed, $entry, $form);
        }

        // Associate the new mailing with the current list
        if (isset($mailing_id) && is_numeric($mailing_id)) {

            gform_update_meta($entry['id'], 'blueshiftaddon_mailing_id', $mailing_id);
            $entry['blueshiftaddon_mailing_id'] = $mailing_id;

            $mailing_is_associated = $this->associate_mailing_with_list($mailing_id, $feed);

            // Get the mail object from Blueshift
            if ($mailing_is_associated) $mailing = $this->get_mailing($mailing_id);

        }

        if (is_object($mailing)) {

            // Set the Send Date on the new mailing
            $mailing = $this->set_mailing_send_date($mailing, $feed);

            // Approve the mailing to be sent at the Send Date
            $mailing = $this->set_mailing_approved_state($mailing, $feed, $content_id);

            $mailing_id = $this->api->put_update_mailing($mailing)->mid;

        }

        GFCommon::log_debug(__METHOD__ . "(): Mailing " . print_r($mailing, true));

        if (is_numeric($mailing_id)) {

            return true;

        } else {

            return false;

        }
    }

    public function create_content($feed, $entry, $form) {
        // Get settings for content from feed settings and entry
        $content_html = $this->get_filtered_field_value('contentBody',$feed, $entry, $form);
        $content_html = $this->get_html_body($content_html, $feed, $entry, $form);
        $content_plaintext = $this->get_filtered_field_value('contentPlaintextBody', $feed, $entry, $form);
        $content_name = $this->get_filtered_field_value('contentName',$feed, $entry, $form);
        $content_description = $this->get_filtered_field_value('contentDescription', $feed, $entry, $form);
        $subject = $this->get_filtered_field_value('subjectLine',$feed, $entry, $form);
        $from = $this->get_filtered_field_value('fromName',$feed, $entry, $form);
        $from_address = $this->get_filtered_field_value('fromAddress',$feed, $entry, $form);
        $to = $this->get_filtered_field_value('toName', $feed, $entry, $form );
        $headers = array(
            "To:".$to,
            "From:".$from." <".$from_address.">",
            "Subject:".$subject,
            "Reply-To: "
        );
        $content_id = $this->api->put_create_content($content_html, $content_plaintext, $content_name, $content_description, $headers)->cid;

        if(is_numeric($content_id)) {
            return $content_id;
        } else {
            return false;
        }
    }

    public function get_html_body($content_html, $feed, $entry, $form) {
        // Return unaltered $content_html if no template is selected
        if(rgblank($feed['meta']['contentTemplate'])) {
            $this->log_debug( __METHOD__ . '(): No template is set. Return content unaltered.' );
            return $content_html;
        } else {

            $template_post_id = $feed['meta']['contentTemplate'];
            $this->log_debug( __METHOD__ . '(): $template_post_id ' . print_r($template_post_id, true) );
            $template_post = get_post($template_post_id);
            $template_html = $template_post->post_content;
            // $this->log_debug( __METHOD__ . '(): $template_html ' . print_r($template_html, true) );
            $combined_content = str_ireplace("[FEEDCONTENT]",$content_html,$template_html);
            // $this->log_debug( __METHOD__ . '(): $combined_content ' . print_r($combined_content, true) );
            return $combined_content;

        }
    }

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

    public function get_entry_meta( $entry_meta, $form_id ) {
        $entry_meta['blueshiftaddon_content_id']   = array(
            'label'                      => 'Blueshift Content ID',
            'is_numeric'                 => true,
            'is_default_column'          => true,
            'update_entry_meta_callback' => array( $this, 'update_entry_meta' ),
            'filter'         => array(
                'operators' => array( 'is', 'isnot', '>', '<' )
            )
        );

        $entry_meta['blueshiftaddon_mailing_id']   = array(
            'label'                      => 'Blueshift Mailing ID',
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
        $content_id = rgar( $entry, 'blueshiftaddon_content_id' );
        $mailing_id = rgar( $entry, 'blueshiftaddon_mailing_id' );

        if ( (empty( $content_id ) || empty( $mailing_id )) && rgpost( 'action' ) == $action ) {
            check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

            // Because the entry doesn't already have a content id and the 'Process Feeds' button was clicked process the feeds.
            $entry = $this->maybe_process_feed( $entry, $form );

            // Retrieve the content id from the updated entry.
            $content_id = rgar( $entry, 'blueshiftaddon_content_id' );
            $mailing_id = rgar( $entry, 'blueshiftaddon_mailing_id' );

            $html .= esc_html__( 'Feeds Processed.', 'gravityformsblueshift' ) . '</br></br>';
        }

        if ( empty( $content_id ) || empty( $mailing_id ) ) {

            // Add the 'Process Feeds' button.
            $html .= sprintf( '<input type="submit" value="%s" class="button" onclick="jQuery(\'#action\').val(\'%s\');" />', esc_attr__( 'Process Feeds', 'gravityformsblueshift' ), $action );

        } else {

            // Display the content ID and mailing ID.

            $html .= '<p><a href="https://mc2.agora-inc.com/content/preview?contentid=' . $content_id .'" target="_blank">';

            $html .= esc_html__( 'Content ID', 'gravityformsblueshift' ) . ': ' . $content_id;

            $html .= "</a></p>";

            // Link to the content aand mailing in Blueshift

            $html .= '<p><a href="https://mc2.agora-inc.com/mailings/overview?mailingid=' . $mailing_id .'" target="_blank">';

            $html .= esc_html__( 'Mailing ID', 'gravityformsblueshift' ) . ': ' . $mailing_id;

            $html .= "</a></p>";

        }

        echo $html;
    }

    /**
     * Return the stylesheets which should be enqueued.
     *
     * @return array
     */
    public function styles() {

        $min    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
        $styles = array(
            array(
                'handle'  => 'gform_blueshift_form_settings_css',
                'src'     => $this->get_base_url() . "/css/form_settings{$min}.css",
                'version' => $this->_version,
                'enqueue' => array(
                    array( 'admin_page' => array( 'form_settings' ) ),
                ),
            ),
        );

        return array_merge( parent::styles(), $styles );

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
        );

    }

    /**
     * Prepare plugin settings description.
     *
     * @return string
     */
    public function plugin_settings_description() {

        $description = '<p>';
        $description .=
            esc_html__( 'Blueshift makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to send email broadcasts to your Blueshift segments.', 'gravityformsblueshift' );
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
            'mailingSegment'      => esc_html__( 'Blueshift List', 'gravityformsblueshift' ),
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
                        'label'    => esc_html__( 'Mailing Segment', 'gravityformsblueshift' ),
                        'type'     => 'select',
                        'required' => true,
                        'choices'  => $this->lists_for_feed_setting(),
                        'onchange' => "jQuery(this).parents('form').submit();",
                        'tooltip'  => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Mailing List', 'gravityformsblueshift' ),
                            esc_html__( 'Select one of the defined Blueshift lists for the mailing.', 'gravityformsblueshift' )
                        ),
                    ),
                    array(
                        'name'     => 'coreTarget',
                        'label'    => esc_html__( 'Core Target', 'gravityformsblueshift' ),
                        'type'     => 'select',
                        'dependency' => 'mailingSegment',
                        'choices'  => $this->targets_for_feed_setting(),
                        'tooltip'  => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Core Target', 'gravityformsblueshift' ),
                            esc_html__( 'Select one of the defined Blueshift core targets for the mailing.', 'gravityformsblueshift' )
                        ),
                    )
                ),
            ),
            'content'  => array(
                'title'  => esc_html__( 'Content Settings', 'gravityformsblueshift' ),
                'fields' => array(
                    array(
                        'name'          => 'contentName',
                        'label'         => esc_html__( 'Name', 'gravityformsblueshift' ),
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                    ),
                    array(
                        'name'          => 'contentDescription',
                        'label'         => esc_html__( 'Description', 'gravityformsblueshift' ),
                        'type'          => 'textarea',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                    ),
                    array(
                        'name'          => 'toName',
                        'label'         => esc_html__( 'To', 'gravityformsblueshift' ),
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'medium',
                        'default_value' => '[%= :prettyTo %]'
                    ),
                    array(
                        'name'          => 'fromName',
                        'label'         => esc_html__( 'From Name', 'gravityformsblueshift' ),
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                        // 'default_value' => $this->get_from_name_for_list(),
                        // 'dependency' => 'mailingSegment',
                    ),
                    array(
                        'name'          => 'fromAddress',
                        'label'         => esc_html__( 'From Address', 'gravityformsblueshift' ),
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
                        // 'default_value' => $this->get_from_name_for_list(),
                        // 'dependency' => 'mailingSegment',
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
                        'name'     => 'campaignType',
                        'label'    => esc_html__( 'Campaign', 'gravityformsblueshift' ),
                        'type'     => 'select',
                        'required' => true,
                        'choices'  => array(
                            array(
                                'label' => esc_html__( 'Daily Issue', 'gravityformsblueshift' ),
                                'value' => '5',
                            ),
                            array(
                                'label' => esc_html__( 'No Campaign', 'gravityformsblueshift' ),
                                'value' => '1',
                            )
                        ),
                        'tooltip'  => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Campaign Type', 'gravityformsblueshift' ),
                            esc_html__( 'Select one of the defined Blueshift campaign types for the mailing.', 'gravityformsblueshift' )
                        ),
                    ),
                    array(
                        'name'     => 'mailingType',
                        'label'    => esc_html__( 'Type', 'gravityformsblueshift' ),
                        'type'     => 'select',
                        'required' => true,
                        'choices'  => array(
                            array(
                                'label' => esc_html__( 'Scheduled', 'gravityformsblueshift' ),
                                'value' => '1',
                            ),
                            array(
                                'label' => esc_html__( 'Immediate', 'gravityformsblueshift' ),
                                'value' => '5',
                            )
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
                        'class'         => 'small',
                        // 'dependency'    => array( 'field' => 'mailingType', 'values' => array( '1' ) ),
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
        $result   = false;

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
     * Prepare Blueshift lists for feed field
     *
     * @return array
     */
    public function lists_for_feed_setting() {

        $lists = array(
            array(
                'label' => esc_html__( 'Select a Segement', 'gravityformsblueshift' ),
                'value' => ''
            )
        );

        /* If Blueshift API credentials are invalid, return the lists array. */
        if ( ! $this->initialize_api() ) {
            return $lists;
        }

        /* Get available Blueshift lists. */
        //$mc_lists = $this->api->get_all_lists_by_orgid();
        $blueshift_lists = array(array(
            'name' => 'Test Segment',
            'segmentid' => 'ebc7c8cc-abf2-4729-aa40-f23e193284f7'
        ));
        /* Add Blueshift lists to array and return it. */
        foreach ( $blueshift_lists as $list ) {

            $segments[] = array(
                'label' => $list['name'],
                'value' => $list['segmentid']
            );

        }
        return $segments;
    }

    /**
     * Prepare Blueshift Targets for feed field
     *
     * @return array
     */
    public function targets_for_feed_setting() {

        $targets = array(
            array(
                'label' => esc_html__( 'Select a Target', 'gravityformsblueshift' ),
                'value' => ''
            )
        );

        /* If Blueshift API credentials are invalid, return the lists array. */
        if ( ! $this->initialize_api() ) {
            return $targets;
        }

        // Get list ID.
        $current_feed = $this->get_current_feed();
        $list_id      = rgpost( '_gaddon_setting_mailingSegment' ) ? rgpost( '_gaddon_setting_mailingSegment' ) : $current_feed['meta']['mailingSegment'];


        /* Get available Blueshift targets for $list_id. */
        //$mc_targets = $this->api->get_target_id_by_list_id($list_id);

        $this->log_debug( __METHOD__ . '(): $mc_targets; ' . print_r($mc_targets, true) );

        /* Add Blueshift lists to array and return it. */
        foreach ( $mc_targets as $target ) {

            $targets[] = array(
                'label' => $target->name,
                'value' => $target->core_target_id
            );

        }

        return $targets;

    }

    /**
     * Prepare Wordpress Blueshift Email Templates for feed field
     *
     * @return array
     */
    public function get_templates_for_feed_setting() {

        $this->log_debug( __METHOD__ . '(): Query wordpress for gfmctemplate post_type entries' );

        $templates = array(
            array(
                'label' => esc_html__( 'Select a Template', 'gravityformsblueshift' ),
                'value' => ''
            )
        );

        $args = array(
            'post_type' => 'gfmctemplate'
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

    public function get_from_name_for_list() {
        // Get the current feed
        $current_feed = $this->get_current_feed();
        // Get the value of the Mailing List setting
        $list_id      = rgpost( '_gaddon_setting_mailingSegment' ) ? rgpost( '_gaddon_setting_mailingSegment' ) : $current_feed['meta']['mailingSegment'];
        // Log the list ID obtained from the Mailing List settings
        $this->log_debug(__METHOD__.'(): $list_id from savings submit: ' . print_r($list_id, true));
        // Get all lists for current Org ID from Blueshift
        //$mc_lists = $this->api->get_all_lists_by_orgid();
        $blueshift_segments = array(array(
            'name' => 'Test Segment',
            'segmentid' => 'ebc7c8cc-abf2-4729-aa40-f23e193284f7'
        ));
        // Add Blueshift lists to array.
        foreach ($blueshift_segments as $segment) {
            // Find the list that matches the current Mailing List setting
            if ($segment->segmentid == $current_feed['meta']['mailingSegment']) {
                $this->log_debug(__METHOD__.'(): fromaddress value found for the current Mailing List:' . print_r($segment, true));

                if(!empty($segment->fromaddress)) {
                    // Return the fromaddress value
                    $this->log_debug(__METHOD__.'(): fromaddress value found for the current Mailing List:' . print_r($segment->fromaddress, true));
                    return $segment->fromaddress;
                } else {
                    // Return an empty strying if no fromaddress found for the current Mailing List
                    $this->log_debug(__METHOD__.'(): No fromaddress value found for the current Mailing List');
                    return "";
                }
            } else {
                // Return an empty strying if no list found matching the current Mailing List
                $this->log_error(__METHOD__.'(): No agoralistid found matching current mailingSegment feed setting value.');
                return "";
            }
        }
    }
}

add_action('init', 'register_blueshift_post_type', 1);
function register_blueshift_post_type() {
    $post_type = 'gfmctemplate';

    $args = array(
        'label' => 'Email Templates',
        'exclude_from_search' => true,
        'public' => false,
        'publicly_queryable' => false,
        'query_var' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'has_archive' => false,
    );

    if(!post_type_exists($post_type)) {

        register_post_type($post_type, $args);

    }
}