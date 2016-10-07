<?php
/**
 * 
 * @file wordpress.php
 * @desc This class is proxying WordPress functions.
 *
 */
class XWordpress {
    public function wp_query() {
        $args = isset($_REQUEST['args']) ? $_REQUEST['args'] : [];
        $data = new WP_Query( $args );
        $data = $this->getWPQueryJsonData( $data );
        wp_send_json_success($data);
    }
    public function getWPQueryJsonData( $data ) {
        $ret = new stdClass();
        if ( $data && $data->posts ) {
            foreach ( $data->posts as $p ) {
                $this->jsonPost($p);
                $p->meta = get_post_meta( $p->ID );
            }
            $ret->found_posts = $data->found_posts;
            $ret->max_num_pages = $data->max_num_pages;
            $ret->posts = $data->posts;
            $ret->post_count = $data->post_count;
        }
        return $ret;
    }


    public function get_categories() {
        wp_send_json_success( get_categories() );
    }


    public function get_user_by() {
        $field = $_REQUEST['field'];
        $value = $_REQUEST['value'];
        $raw = WP_User::get_data_by($field, $value);
        unset(
            $raw->display_name,
            $raw->user_activation_key,
            $raw->user_pass,
            $raw->user_status,
            $raw->user_url
        );

        $meta = get_user_meta( $raw->ID );
        unset(
            $meta['admin_color'],
            $meta['comment_shortcuts'],
            $meta['description'],
            $meta['rich_editing'],
            $meta['show_admin_bar_front'],
            $meta['use_ssl'],
            $meta['wp_capabilities'],
            $meta['wp_user_level']
        );
        $raw->meta = array_map( function( $a ){ return $a[0]; }, $meta );

        $user = new XUser($raw->ID );
        $raw->session_id = $user->get_session_id( $user );


        wp_send_json_success( $raw );
    }



    /**
     * @attention The password in the post's meta is deleted before the post data is sent to the browser.
     */
    public function get_post() {
        $post_ID = $_REQUEST['post_ID'];
        if ( empty( $post_ID ) ) wp_send_json_error('post_ID_is_empty');
        $post = get_post( $post_ID );
        $this->jsonPost( $post );
        $post->meta = get_post_meta( $post->ID );
        $images = get_attached_media( 'image', $post->ID );
        if ( $images ) {
            $post->images = [];
            foreach ( $images as $ID => $image ) {
                $this->jsonPost($image);
                $post->images[] = $image;
            }
        }
        if ( isset( $post->meta['password'] ) ) unset($post->meta['password']);
        wp_send_json_success( $post );
    }


    /**
     * This proxies wp_delete_post() upon ionic 2 - xapi client-end.
     *
     * @attention if the post has 'password' meta, then this checks the post meta password with $_REQUEST['password']
     *          if $_REQUEST['password'] matches with post's meta password, then it deletes.
     *
     * @attention if the post has no 'password' meta, then it does user authentication.
     *
     */
    public function delete_post() {
        $post_ID = $_REQUEST['post_ID'];
        if ( empty( $post_ID ) ) wp_send_json_error('post_ID_is_empty');
        $post = get_post( $post_ID );
        if ( empty( $post ) ) wp_send_json_error('no_post_by_that_ID');
        $password = get_post_meta( $post_ID, 'password', true );
        if ( $password ) {
            if ( !isset( $_REQUEST['password'] ) || empty($_REQUEST['password']) ) wp_send_json_error('input password');
            if ( $_REQUEST['password'] != $password ) wp_send_json_error('wrong password');
        }
        else {
            // @todo user authentication.
        }
        $re = @wp_delete_post( $post_ID );
        if ( !$re ) {
            // error
            // @todo warning if code comes here, it is an error.
            xlog("ERROR: XPost::insert()");
            wp_send_json_error('fail to delete');
        }
        else {
            wp_send_json_success($post_ID);
        }
    }



    /**
     *
     *
     *
     * @param $p
     */
    public function jsonPost( $p ) {
        unset(
            $p->comment_status,
            $p->filter,
            $p->pinged,
            $p->menu_order,
            $p->ping_status,
            $p->post_content_filtered,
            $p->post_date_gmt,
            $p->post_excerpt,
            $p->post_mime_type,
            $p->post_modified,
            $p->post_modified_gmt,
            $p->post_name,
            $p->post_password,
            $p->post_status,
            $p->post_type,
            $p->to_ping
        );
    }
}
