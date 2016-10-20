<?php
/**
 *
 * Wordpress Proxy Class
 *
 * @author JaeHo Song thruthesky@gmail.com
 * @file wordpress.php
 * @description This class is proxy class of WordPress functions.
 *
 *
 */
class XWordpress {
    public function wp_query() {
        $args = isset($_REQUEST['args']) ? $_REQUEST['args'] : [];
        $data = new WP_Query( $args );
        $data = $this->getWPQueryJsonData( $data );
        wp_send_json_success($data);
    }

    /**
     * Use this method after querying to database with 'WP_Query()'
     * @param $data - is the result of WP_Query()
     * @return stdClass - sanitized data which contains better result of WP_Query.
     */
    public function getWPQueryJsonData( $data ) {
        $ret = new stdClass();
        if ( $data && $data->posts ) {
            foreach ( $data->posts as $p ) {
                $this->jsonPost($p);
                $p->meta = array_map( function( $a ){ return $a[0]; }, get_post_meta( $p->ID ) );
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
        wp_send_json_success( $post );
    }



    /**
     *
     * Echoes posts in json string.
     *
     * @note this method is a proxy of wordpress 'get_posts' function.
     * @note it adds author's nicename to 'author_name' property.
     * @note 'meta' property is attached which holds all the meta data of the post.
     *          - 'meta' property does not contains array. it just hold single value.
     *              ex) post.meta.key_name
     *
     * @code how to call from URI
     *      http://work.org/wordpress/index.php?xapi=wp.get_posts&category_name=housemaid&paged=1&per_page=20
     * @endcodes
     *
     */
    public function get_posts() {

            $args = [
                'posts_per_page' => in('per_page', 10),
                'paged' => in('paged'),
            ];
            if ( isset($_REQUEST['category_name']) ) $args['category_name'] = $_REQUEST['category_name'];

            $_posts = get_posts($args);
            $posts = [];

            foreach( $_posts as $post ) {
                $posts[] = $this->jsonPost( $post );
            }
            wp_send_json_success( $posts );

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
     * Unsets data that are not needed by client.
     *
     * @param $p
     * @return mixed
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

        $p->post_date = $this->human_datetime( $p->post_date );
        if ( $p->post_author ) {
            $user = get_user_by('id', $p->post_author);
            $p->author_name = $user->user_nicename;
        }

        $p->meta = array_map( function( $a ){ return $a[0]; }, get_post_meta( $p->ID ) );

        $comment = new XComment();
        $p->comments = $comment->get_nested_comments_with_meta( $p->ID );
        $images = get_attached_media( 'image', $p->ID );
        if ( $images ) {
            $p->images = [];
            foreach ( $images as $image ) {
                $p->images[] = $image->guid;
            }
        }

        return $p;
    }


    function human_datetime( $date ) {
        $time = strtotime( $date );
        if ( date('Ymd') == date('Ymd', $time) ) return date("h:i a", $time);
        else return date("Y-m-d");
    }


    function delete_attachment() {
        $id = $_REQUEST['id'];
        if ( empty($id) ) wp_send_json_error(['message'=>'id is empty']);
        $post = @get_post( $id );
        if ( empty($post) ) wp_send_json_error( ['id' => $id, 'message' => 'attachment does not exists'] );
        if ( false === wp_delete_attachment( $id, true ) ) wp_send_json_error( [ 'id' => $id, 'message' => 'failed to delete file' ] );
        else wp_send_json_success( $id );
    }


}
