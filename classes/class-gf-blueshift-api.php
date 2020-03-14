<?php
class GF_Blueshift_API {

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
}