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
 *
 *
 */
class XUser extends WP_User {
    public $userdata = [];
    static $usermeta = ['name', 'gender', 'address', 'birthday', 'mobile', 'landline'];
    public function __construct( $id = 0, $name = '', $blog_id = '' )
    {
        parent::__construct($id, $name, $blog_id);
    }

    public function __list() {
    	wp_send_json_success("User list ok");
    }

    public function login() {
        if ( ! isset($_REQUEST['user_login']) || empty( $_REQUEST['user_login'] ) ) wp_send_json_error('input-id');
        if ( ! isset($_REQUEST['user_pass']) || empty( $_REQUEST['user_pass'] ) ) wp_send_json_error('input-password');
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

        wp_send_json_success( $this->userResponse( $user->ID ) );

    }

    /**
     * @param WP_User $user
     * @return string
     *
     * @code How to get session_id
            $user = new XUser($raw->ID );
            $raw->session_id = $user->get_session_id( $user );
     * @endcode
     */
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

    /**
     *
     * session_id 값을 입력 받아
     *      - 해당 사용자를 로그인 시키고,
     *      - 해당 사용자를 author 등급으로 업그레이드 하고,
     *      - WP_User 객체를 리턴한다.
     * @param $session_id
     *
     * @return WP_User
     *      - Returns WP_User instance on success or exits with error code.
     * @code
     *  $user = $this->session_login( $_REQUEST['session_id'] );
     * @endcode
     *
     */
    public function session_login( $session_id ) {
        if ( empty($session_id) ) wp_send_json_error('get_user_by_session_id() : empty session_id');

        list( $ID, $trash ) = explode('_', $session_id);
        $user = get_userdata( $ID );
        if ( $user ) {
            if ( $session_id == $this->get_session_id( $user ) ) {
                wp_set_current_user( $ID );
                $caps = $user->get_role_caps();
                if ( isset($caps['subscriber']) && $caps['subscriber'] ) $user->set_role('author');
                return $user;
            }
            else {
                wp_send_json_error('get_user_by_session_id() : invalid session_id');
            }
        }
        else {
            wp_send_json_error('get_user_by_session_id() : wrong session_id');
        }
    }
    /**
     * 이 함수를 호출하면,
     *      $_REQUEST['session_id'] 의 값을 바탕으로 사용자를 로그인 시킨다.
     * 이 함수는 현재 접속자를 로그인 시켜야 할 필요가 있을 때 사용하면 된다.
     *
     */
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

    /**
     * 사용자 기본 정보를 업데이트한다. ( 메타 정보를 업데이트 하지 않는다. )
     *
     * @attention 사용자는 this->session_login() 이나 this->authenciticate() 으로 로그인을 해 있어야 한다.
     *
     * @note 워드프레스는 이메일을 변경 할 때, 사용자에게 이메일을 보내서 확인을 하게 한다.
     *
     *  이 때, 서버의 이메일 설정이 올바르지 않으면 에러가 발생한다.
     *
     *  따라서 메일을 보내지 않도록 한다. https://docs.google.com/document/d/1t3jvgHilztEMRacUxMtHrlN1E3ZkbFLS99EWxLAyngA/edit#heading=h.uw2v62fvirv6
     */
    public function update() {
        $this->userdata[ 'ID' ] = wp_get_current_user()->ID;
        $user_id = wp_update_user( $this->userdata );
        if ( is_wp_error( $user_id ) ) wp_send_json_error( 'updateUser() : error' );
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

        $this->updateUserMeta( $user_id );

        $user = new WP_User( $user_id );
        wp_send_json_success( $this->userResponse( $user->ID ) );
    }

    /**
     * 사용자 정보와 메타 정보를 업데이트한다.
     *
     * 이 메소드를 API 를 통해서 직접 호출 된다.
     *
     */
    public function profile() {
        if ( ! isset( $_REQUEST['session_id'] ) ) wp_send_json_error('session_id is empty');
        if ( !isset($_REQUEST['user_email']) || empty($_REQUEST['user_email']) ) wp_send_json_error('user_email_is_empty');

        $user = $this->session_login( $_REQUEST['session_id'] );
        if ( $user->user_email != $_REQUEST['user_email'] ) {
            if ( get_user_by('email', $_REQUEST['user_email']) ) wp_send_json_error('email_exist');
        }

        $user_id = $this
            ->set('user_email', $_REQUEST['user_email'])
            ->update();

        $this->updateUserMeta( $user_id );

        wp_send_json_success( $this->userResponse( $user_id ) );

    }

    private function updateUserMeta( $user_ID ) {
        foreach( self::$usermeta as $i ) {
            if ( isset( $_REQUEST[ $i ] ) ) {
                update_user_meta( $user_ID, $i, $_REQUEST[$i]);
            }
        }
    }

    /**
     * API 호출을 통해서 회원 비밀번호를 변경한다.
     *
     * @param $_REQUEST['session_id'] 회원 세션 아이디 값
     * @param $_REQUEST['old_password'] 기존 비밀번호
     * @param $_REQUEST['new_password'] 신규 비밀번호
     *
     *
     */
    public function password() {
        if ( ! isset( $_REQUEST['session_id'] ) ) wp_send_json_error('empty session_id');
        if ( ! isset( $_REQUEST['old_password'] ) ) wp_send_json_error('empty old password');
        if ( ! isset( $_REQUEST['new_password'] ) ) wp_send_json_error('empty new password');
        $user = $this->session_login( $_REQUEST['session_id'] );
        if ( $this->checkPassword($user->user_login, $_REQUEST['old_password']) ) {
            $user_id = wp_update_user( ['ID' => $user->ID, 'user_pass'=>$_REQUEST['new_password'] ] );
            if ( is_wp_error( $user_id ) ) wp_send_json_error( 'password-change-error' );
            wp_send_json_success( $this->userResponse( $user->ID ) );

        }
        else wp_send_json_error('wrong-old-password');
    }

    /**
     *
     */
    public function resign() {
        if ( ! isset( $_REQUEST['session_id'] ) ) wp_send_json_error('empty session_id');
        $user = $this->session_login( $_REQUEST['session_id'] );
        $re = wp_delete_user( $user->ID );
        if ( $re ) wp_send_json_success();
        else wp_send_json_error( 'failed to resign' );
    }

    private function checkPassword( $user_login, $user_pass ) {
        $user = get_user_by( 'login', $user_login );
        if ( $user && wp_check_password( $user_pass, $user->data->user_pass, $user->ID ) ) {
            return true;
        } else {
            return false;
        }
    }

    private function userResponse( $user_ID ) {
        $user = new WP_User( $user_ID );
        return [
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'user_nicename' => $user->user_nicename,
            'session_id' => $this->get_session_id( $user )
        ];
    }


}
