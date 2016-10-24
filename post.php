<?php
/**
 * Post class
 *
 */
class XPost {
    static $post = null; // WP_Post
    static $post_data = []; // create / update data.
    static $post_fields = [
        'ID',
        'post_author',
        'post_date',
        'post_date_gmt',
        'post_content',
        'post_title',
        'post_excerpt',
        'post_status',
        'comment_status',
        'ping_status',
        'post_password',
        'post_name',
        'to_ping',
        'pinged',
        'post_modified',
        'post_modified_gmt',
        'post_content_filtered',
        'post_parent',
        'guid',
        'menu_order',
        'post_type',
        'post_mime_type',
        'comment_count'
    ];

    /**
     * $post = $this
    ->set('post_category', [ forum()->getCategory()->term_id ])
    ->set('post_title', $title)
    ->set('post_content', $content)
    ->set('post_status', 'publish')
     *
     *
     * @return int
     */
    public function create() {
        return @wp_insert_post( self::$post_data );
    }
    public function update() {
        return @wp_update_post( self::$post_data );
    }


    /**
     *
     * @param $key
     * @param $value
     * @return XPost
     *
     *
     */
    public function set( $key, $value ) {
        self::$post_data[ $key ] = $value;
        return $this;
    }


    /**
     *
     *
     * @attention it gets 'file_id' as attachment id and set the parent of the attachment to this post.
     *
     * @attention if $_REQUEST['file_id'] is set and $_REQUEST['single_image'] is set to 1,
     *      then it will delete all the previous images.
     *      which means, it only maintains one last image.
     */
    public function insert() {
        xlog( $_REQUEST );
        $this->check_insert_input();
        if ( isset($_REQUEST['ID']) && is_numeric( $_REQUEST['ID']) ) $post_ID = $_REQUEST['ID'];
        else $post_ID = 0;
        if ( isset( $_REQUEST['post_content'] ) ) $post_content = $_REQUEST['post_content'];
        else $post_content = '';
        if ( $post_ID ) { // 글 수정

            $post = get_post( $post_ID );
            if ( $post ) {
                if ( $post->post_author ) { // 글의 소유주가 회원인 경우,
                    if ( is_user_logged_in() ) { // 로그인 되었으면 개인 소유 정보 확인
                        if ( wp_get_current_user()->ID != $post->post_author ) wp_send_json_error('You are not the owner of the post.');
                    }
                    else wp_send_json_error('This post is owned by a member. Login first before you edit.');
                }
                else { // 입력된 비밀번호로  확인
                    if ( ! isset( $_REQUEST['password'] ) || empty( $_REQUEST['password']) ) wp_send_json_error( 'input password' );
                    $password = $_REQUEST['password'];
                    $old_password = get_post_meta( $post_ID, 'password', true );
                    if ( $password != $old_password ) wp_send_json_error( 'wrong password' );
                }
            }

        }

        $category = get_category_by_slug($_REQUEST['category']);
        if ( $category === false ) wp_send_json_error( 'category does not exists' );
        $this
            ->set('post_category', [ $category->term_id ])
            ->set('post_title', $_REQUEST['post_title'])
            ->set('post_content', $post_content)
            ->set('post_status', 'publish');

        if ( is_user_logged_in() ) {
            $this->set('post_author', wp_get_current_user()->ID);
        }

        if ( $post_ID ) {
            $this
                ->set('ID', $post_ID)
                ->update();
        }
        else {
            $post_ID = $this->create();
        }

        if ( is_wp_error( $post_ID ) ) wp_send_json_error( xerror( $post_ID ) );
        self::load( $post_ID );
        $this->saveMeta();

        if ( isset( $_REQUEST['fid'] ) && is_array( $_REQUEST['fid'] ) ) {
            foreach( $_REQUEST['fid'] as $file_id ) {
                $data = [ 'ID' => $file_id, 'post_parent' => $post_ID ];
                $attach_id = @wp_update_post( $data );
                if ( $attach_id == 0 || is_wp_error( $attach_id ) ) wp_send_json_error( xerror( $attach_id ) );
            }
        }

        /*
        if ( isset( $_REQUEST['file_id'] ) && $_REQUEST['file_id'] ) {
            if ( $_REQUEST['single_image'] ) {
                $images = get_attached_media('image', $post_ID);
                if ( $images ) {
                    foreach ( $images as $ID => $image ) {
                        $re = @wp_delete_post( $ID );
                        if ( !$re ) {
                            // error
                            // @todo warning if code comes here, it is an error.
                            xlog("ERROR: XPost::insert()");
                        }
                    }
                }
            }
            $data = [ 'ID' => $_REQUEST['file_id'], 'post_parent' => $post_ID ];
            $attach_id = @wp_update_post( $data );
            if ( $attach_id == 0 || is_wp_error( $attach_id ) ) wp_send_json_error( xerror( $attach_id ) );
        }
        */
        wp_send_json_success( $post_ID );
    }




    /**
     *
     * This method saves all the input into post_meta except those are already saved in wp_posts table.
     *
     * @attention This will save everything except wp_posts fields,
     *      so you need to be careful not to add un-wanted form values.
     *
     * @param $post_ID
     */
    public function saveMeta()
    {
        foreach ( $_REQUEST as $k => $v ) {
            if ( in_array( $k, self::$post_fields ) ) continue;
            if ( in_array( $k, xapi_post_query_meta_exclude_vars() ) ) continue;
            $this->meta($k, $v );
        }
    }



    /**
     *
     * Saves data into 'post_meta' or Gets data from 'post_meta'
     *
     * @note it automatically serialize and un-serialize.
     *
     * @Attention This returns on 'single' value.
     *
     * @param $key
     * @param null $value - If it is not null, then it updates meta data.
     *
     * @return mixed|null
     *
     * @code
     *          post()->meta( $post_ID, 'files', $files );          /// SAVE
     *          $this->meta( self::$post->ID, $property );          /// GET meta of post->ID
     *          $p = post()->meta( 'process' );                     /// GET meta of self::$post->ID
     * @endcode
     *
     */
    public function meta($key = null, $value = null)
    {

        if ( $value !== null ) {

            // @deprecated. Automatically serialized by wp.
            //if ( ! is_string($value) && ! is_numeric( $value ) && ! is_integer( $value ) ) {
            //    $value = serialize($value);
            //}


            update_post_meta( $this->get('ID'), $key, $value);
            return null;
        }
        else {
            $value = get_post_meta( $this->get('ID'), $key, true);
            if ( is_serialized( $value ) ) {
                $value = unserialize( $value );
            }
            return $value;
        }
    }



    public function get( $key ) {
        return self::$post->$key;
    }


    public static function load( $post_ID ) {
        self::$post = get_post( $post_ID );
    }



    /**
     *
     * @note it adds author's nicename to 'author_name' property.
     * @note post meta data will be added as post property.
     *
     *      ( post meta 키가 post 속성으로 바로 추가 된다. 예: post->content_type )
     *
     *
     */
    public function page() {
        $this->check_page_input();
        $category = get_category_by_slug( in('category') );
        if ( $category === false ) wp_send_json_error( 'category does not exists' );
        $args = [
            'category' => $category->term_id,
            'posts_per_page' => in('posts_per_page', 10),
            'paged' => in('paged'),
        ];
        $_posts = get_posts($args);
        $posts = [];
        $comment = new XComment();
        foreach( $_posts as $_post ) {
            $post = [];
            $post['ID'] = $_post->ID;
            $post['post_title'] = $_post->post_title;
            $post['post_content'] = $_post->post_content;
            $post['date'] = $_post->post_date;
            if ( $_post->post_author ) {
                $user = get_user_by('id', $_post->post_author);
                $author_name = $user->user_nicename;
                $post[ 'author_name' ] = $author_name;
            }


            $post['meta'] = get_post_meta( $_post->ID );
            /*
            foreach( $meta as $k => $arr ) {
                $post->$k = $arr[0];
            }*/

            $post['comments'] = $comment->get_nested_comments_with_meta( $_post->ID );
            $images = get_attached_media( 'image', $_post->ID );
            if ( $images ) {
                $post['images'] = [];
                foreach ( $images as $image ) {
                    $post['images'][] = $image->guid;
                }
            }
            $posts[] = $post;
        }
        wp_send_json_success( [
            '_REQUEST' => $_REQUEST,
            'category' => $category,
            'posts' => $posts
        ] );
    }


    /**
     * @doc when there is any key which has no value, it will display error message with : "EMPTY:key"
     */
    private function check_insert_input()
    {
        $keys = [
            'category', 'post_title'
        ];
        foreach ( $keys as $k ) {
            if ( ! isset( $_REQUEST[$k] ) || empty( $_REQUEST[$k] ) ) {
                wp_send_json_error( "EMPTY:$k" );
            }
        }

    }

    private function check_page_input()
    {
        $keys = [ 'category', 'paged' ];
        foreach ( $keys as $k ) {
            if ( ! isset( $_REQUEST[$k] ) || empty( $_REQUEST[$k] ) ) {
                wp_send_json_error( "$k is not provided" );
            }
        }
    }
}