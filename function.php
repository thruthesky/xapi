<?php


/**
 * Returns first error message from WP_Error array.
 * @param $error
 * @return string|void
 */
function xapi_get_error_message( $error ) {
    if ( ! is_wp_error($error) ) return null;
    list ( $k, $v ) = each ($error->errors);
    return "$k : $v[0]";
}
function xerror( $thing ) {
    return xapi_get_error_message( $thing );
}

function xapi_get_query_vars() {
    return ['xapi', 'session_id'];
}
function xapi_post_query_vars() {
    return ['xapi', 'session_id', 'category', 'title', 'content'];
}




function isValidJSON($str) {
    json_decode($str);
    return json_last_error() == JSON_ERROR_NONE;
}


function xapi_get_json_post() {
    $json_params = file_get_contents( "php://input" );
    if ( strlen($json_params) > 0 ) {
        $dec = json_decode( $json_params, true );
        if ( json_last_error() == JSON_ERROR_NONE ) {
            return $dec;
        }
    }
    return null;
}


if ( ! function_exists('in') ) {
    /**
     *
     * @note By default it returns null if the key does not exist.
     *
     * @param $name
     * @param null $default
     * @return null
     *
     */
    function in( $name, $default = null ) {
        if ( isset( $_REQUEST[$name] ) ) return $_REQUEST[$name];
        else return $default;
    }
}

if ( ! function_exists( 'xlog' ) ) {


    /**
     * Leaves a log message on WordPress log file on when the debug mode is enabled on WordPress. ( wp-content/debug.log )
     *
     * @param $message
     */
    function xlog( $message ) {
        static $count_log = 0;
        $count_log ++;
        if( WP_DEBUG === true ){
            if( is_array( $message ) || is_object( $message ) ){
                $message = print_r( $message, true );
            }
            else {

            }
        }
        $message = "[$count_log] $message";
        error_log( $message ); //
    }


}


function xapi_get_safe_filename($filename) {
    $pi = pathinfo($filename);
    $sanitized = md5($pi['filename'] . ' ' . $_SERVER['REMOTE_ADDR'] . ' ' . time());
    if ( isset($pi['extension']) && $pi['extension'] ) return $sanitized . '.' . $pi['extension'];
    else return $sanitized;
}