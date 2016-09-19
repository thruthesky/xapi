<?php
/**
 * @file user.php
 * @desc User class
 */
/**
 * Includes.
 */
require_once ABSPATH . '/wp-includes/pluggable.php';
require_once ABSPATH . '/wp-includes/user.php';
require_once ABSPATH . '/wp-admin/includes/user.php';

/**
 * Class WP_INCLUDE_USER
 */
class XUser extends WP_User {
    public $userdata = [];
    public function __construct( $id = 0, $name = '', $blog_id = '' )
    {
        parent::__construct($id, $name, $blog_id);
    }

    public function __list() {
    	wp_send_json_success("User list ok");
    }

    public function login() {
        $user = wp_authenticate( $_REQUEST['user_login'], $_REQUEST['user_pass']);
        if ( is_wp_error( $user ) ) {
            $user = get_user_by('login', $_REQUEST['user_login']);
            if ( $user ) {
                //wp_send_json_error("Wrong Password. Password is incorrect - $_REQUEST[user_pass]");
                wp_send_json_error("wrong-password");
            }
            else {
                //wp_send_json_error("Wrong User ID. There is no user by - $_REQUEST[user_login]");
                wp_send_json_error("wrong-id");
            }
        }
        wp_set_current_user( $user->ID );

        // wp_send_json_success( $this->get_session_id( $user ) );

        wp_send_json_success( $this->userResponse( $user ) );

    }

    public function get_session_id( WP_User $user ) {
        $userdata = $user->to_array();
        if ( ! isset( $userdata['ID'] ) ) wp_send_json_error("User data has no ID");
        $reg = $userdata['user_registered'];
        $reg = str_replace(' ', '', $reg);
        $reg = str_replace('-', '', $reg);
        $reg = str_replace(':', '', $reg);
        $uid = $userdata['ID'] . $userdata['user_login'] . $userdata['user_email'] . $userdata['user_pass'] . $reg;
        $uid = $userdata['ID'] . '_' . md5( $uid );
        return $uid;
    }




    public function authenticate() {
        $session_id = $_REQUEST['session_id'];

        list( $ID, $trash ) = explode('_', $session_id);

        $user = get_userdata( $ID );
        if ( $user ) {
            if ( $session_id == $this->get_session_id( $user ) ) {

                wp_set_current_user( $ID );
                $caps = $user->get_role_caps();
                if ( isset($caps['subscriber']) && $caps['subscriber'] ) $user->set_role('author');
            }
            else {
                wp_send_json_error('Session ID is invalid. Session ID is incorrect.');
            }
        }
        else {
            wp_send_json_error('Session ID is invalid. No user by that session ID.');
        }
    }



    /**
     *
     * It only sets key/value on self::$userdata for the use of user()->create & user()->update
     *
     * @param $key
     * @param $value
     * @return XUser
     *
     * @see test/testUser.php for sample codes.
     *
     */
    public function set( $key, $value ) {
        $this->userdata[$key] = $value;
        return $this;
    }
    public function create() {
        $user_id = wp_insert_user( $this->userdata );
        if ( is_wp_error( $user_id ) ) wp_send_json_error( xerror($user_id) );
        return $user_id;
    }

    public function register() {

        if ( !isset($_REQUEST['user_login']) || empty($_REQUEST['user_login']) ) wp_send_json_error('user_login_is_empty');
        if ( !isset($_REQUEST['user_pass']) || empty($_REQUEST['user_pass']) ) wp_send_json_error('user_pass_is_empty');
        if ( !isset($_REQUEST['user_email']) || empty($_REQUEST['user_email']) ) wp_send_json_error('user_email_is_empty');

        if ( (new WP_User( $_REQUEST['user_login'] ))->exists() ) wp_send_json_error('user_exist');
        if ( get_user_by('email', $_REQUEST['user_email']) ) wp_send_json_error('email_exist');


        $user_id = $this
            ->set('user_login', $_REQUEST['user_login'])
            ->set('user_pass', $_REQUEST['user_pass'])
            ->set('user_email', $_REQUEST['user_email'])
            ->create();


        $user = new WP_User( $user_id );
        wp_send_json_success( $this->userResponse( $user ) );

    }

    private function userResponse( $user ) {
        return [
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'user_nicename' => $user->user_nicename,
            'session_id' => $this->get_session_id( $user )
        ];
    }


}
