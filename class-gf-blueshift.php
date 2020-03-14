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

//        $this->log_debug(__METHOD__ . '(): Processing feed.');
//
//        /* If API instance is not initialized, exit. */
//        if (!$this->initialize_api()) {
//
//            $this->log_error(__METHOD__ . '(): Failed to set up the API.');
//
//            return;
//
//        }
//
//        // Assemble the email content from the entry values and feed settings
//        $content_id = $this->create_content($feed, $entry, $form);
//
//        if (is_numeric($content_id)) {
//            gform_update_meta($entry['id'], 'blueshiftaddon_content_id', $content_id);
//            $entry['blueshiftaddon_content_id'] = $content_id;
//        }
//
//        // Associate the new content with the current list
//        $content_is_associated = $this->associate_content_with_list($content_id, $feed);
//
//        // Create a new mailing from the new content
//        if ($content_is_associated) {
//            $mailing_id = $this->create_mailing($content_id, $feed, $entry, $form);
//        }
//
//        // Associate the new mailing with the current list
//        if (isset($mailing_id) && is_numeric($mailing_id)) {
//
//            gform_update_meta($entry['id'], 'blueshiftaddon_mailing_id', $mailing_id);
//            $entry['blueshiftaddon_mailing_id'] = $mailing_id;
//
//            $mailing_is_associated = $this->associate_mailing_with_list($mailing_id, $feed);
//
//            // Get the mail object from Blueshift
//            if ($mailing_is_associated) $mailing = $this->get_mailing($mailing_id);
//
//        }
//
//        if (is_object($mailing)) {
//
//            // Set the Send Date on the new mailing
//            $mailing = $this->set_mailing_send_date($mailing, $feed);
//
//            // Approve the mailing to be sent at the Send Date
//            $mailing = $this->set_mailing_approved_state($mailing, $feed, $content_id);
//
//            $mailing_id = $this->api->put_update_mailing($mailing)->mid;
//
//        }
//
//        GFCommon::log_debug(__METHOD__ . "(): Mailing " . print_r($mailing, true));
//
//        if (is_numeric($mailing_id)) {
//
//            return true;
//
//        } else {
//
//            return false;
//
//        }
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

}