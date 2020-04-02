<?php

/**
 * Class GF_Blueshift_API
 */
class GF_Blueshift_API {

    const CAMPAIGN_TYPE_ONE_TIME = 'one_time';
    const CAMPAIGN_TYPE_EVENT_TRIGGERED = 'event_triggered';

    function __construct($api_url, $api_key = null) {
        $this->api_url = $api_url;
        $this->api_key = $api_key;
        add_filter('http_request_timeout', array($this, 'wp_timeout_extend'));
    }

    /**
     * Set some default headers
     * @return array
     */
    function default_options() {
        return array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
            )
        );
    }

    /**
     * Use this API to search among your users using email ID that you add to our platform.
     *
     * @see https://developer.blueshift.com/reference#user
     * @param $email
     * @return array
     */
    function search_user($email) {
        $url = $this->api_url . '/customers?' . http_build_query(array('email' => $email));
        return $this->_get($url);
    }

    /**
     * Use this API to create a campaign and specify its attributes
     *
     * @see https://developer.blueshift.com/reference#campaigns-1
     * @param $campaign_params
     * @param bool $campaign_type
     * @return array|mixed|WP_Error
     */
    function create_campaign($campaign_params, $campaign_type = false) {
        if (!$campaign_type) {
            $campaign_type = self::CAMPAIGN_TYPE_ONE_TIME;
        }

        $url = $this->api_url . '/campaigns/' . $campaign_type;
        return $this->_post($url, $campaign_params);
    }

    /**
     * Use this API to trigger a campaign
     *
     * @see https://developer.blueshift.com/reference#post_api-v1-campaigns-execute
     * @param $trigger_params
     * @return array|mixed|WP_Error
     */
    function trigger_campaign($trigger_params) {
        $url = $this->api_url . '/campaigns/execute';
        return $this->_post($url, $trigger_params);
    }

    /**
     * Use this API to get the list of email templates
     *
     * @see https://developer.blueshift.com/reference#email-template
     * @param $name
     * @param $subject
     * @param $content
     * @return array|mixed|WP_Error
     */
    function create_email_template($name, $subject, $content) {
        $url = $this->api_url . '/email_templates.json';
        $template_parameters = array(
            'name' => $name,
            'resource' => [
                'subject' => $subject,
                'content' => $content
            ]
        );
        $this->_post($url, $template_parameters);
    }

    /**
     * Can we access the Blueshift api?
     *
     * @return bool
     * @throws Exception
     */
    function auth_test() {
        /* there is no auth test for blueshift, just confirm we get a 200 back when searching for a user*/
        $response = $this->search_user('test@oxfordclub.com');
        /* If invalid content type, API URL is invalid. */
        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Invalid Request.' );
        }
        return true;
    }

    /**
     *	A helper method to reduce repetition
     *
     *	@param string $url
     *	@return array Associative array of returned data. Returns WP_Error object on error
     **/
    private function _get($url){

        $url = esc_url_raw($url);

        $result = wp_remote_get($url, $this->default_options());
        GFCommon::log_debug( __METHOD__ . '():'.$url.' $result  => ' . print_r( $result, true ) );

        if(is_wp_error($result)){

            return $result;

        }elseif($result['response']['code'] == 200){
            /**
             * This is a successful call and will return a php object
             */
            $response_content = json_decode(wp_remote_retrieve_body($result));

            return $response_content;
        }else{
            /**
             * Some other thing happened, log an error
             */
            GFCommon::log_debug( __METHOD__ . '():'.$url . $result );
        }
    }

    /**
     * Helper Method for POST requests
     *
     * @param $url
     * @param $payload
     *
     * @return array|mixed|WP_Error
     */
    private function _post($url, $payload){

        $url = esc_url_raw($url);

        $request_data = $this->default_options();
        $request_data['body'] = json_encode($payload);
        $request_data['headers']['Content-Type'] = 'application/json';

        $result = wp_remote_post($url, $request_data);
        GFCommon::log_debug( __METHOD__.'(): '.$url.' => ' . print_r( $result, true) );

        if(is_wp_error($result)){
            return $result;
        } elseif($result['response']['code'] == 200) {
            $response_content = json_decode(wp_remote_retrieve_body($result));
            return ($response_content == null OR $response_content == '') ? true : $response_content;
        } else {
            return new WP_Error('request_failed', __('Blueshift POST request failed'));
        }
    }
}