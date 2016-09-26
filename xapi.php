<?php
/**
 * Plugin Name: xapi
 * Description: WordPress REST API for backend web/app.
 * Author: WP REST API Team
 * Author URI: https://github.com/thruthesky/xapi
 * Version: 0.0.2
 * Plugin URI: https://github.com/thruthesky/xapi
 * License: GPL2+
 */


if ( ! isset( $_REQUEST['xapi'] ) ) return;


header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
header( "Access-Control-Allow-Headers: X-AMZ-META-TOKEN-ID, X-AMZ-META-TOKEN-SECRET, Content-Type, Accept" );
if ( $_SERVER['REQUEST_METHOD'] == 'OPTIONS' ) exit;


$xapi = $_REQUEST['xapi'];
$segments = explode( '.', $_REQUEST['xapi'] );
if ( count($segments) != 2 ) wp_send_json_error('Wrong xapi code');

// print_r( $_REQUEST );

require_once dirname( __FILE__ ) . '/function.php';
require_once dirname( __FILE__ ) . '/user.php';
require_once dirname( __FILE__ ) . '/post.php';
require_once dirname( __FILE__ ) . '/comment.php';
require_once dirname( __FILE__ ) . '/file.php';
require_once dirname( __FILE__ ) . '/wordpress.php';

xlog("xapi begins with '$_REQUEST[xapi]'");

if ( $json = xapi_get_json_post() ) $_REQUEST = array_merge( $_REQUEST, $json );

if ( isset( $_REQUEST['session_id'] ) ) {
	$user = new XUser();
	$user->authenticate();
}

list ( $class, $method ) = $segments;
if ( $method == 'list' ) $method = '__list';
$class = 'X' . ucfirst( $class );

if ( ! class_exists( $class ) ) wp_send_json_error( "Class - $class - does not exist." );

$obj = new $class();
if ( ! method_exists( $obj, $method ) ) wp_send_json_error( "Method - $class::$method - does not exist." );
$obj->$method();

wp_send_json_error( 'Unhandled xapi routine' );