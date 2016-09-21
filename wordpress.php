<?php
/**
 * @file wordpress.php
 * @desc This class is proxying WordPress functions.
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
                unset(
                    $p->comment_status,
                    $p->filter,
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
                $p->meta = get_post_meta( $p->ID );
            }
            $ret->found_posts = $data->found_posts;
            $ret->max_num_pages = $data->max_num_pages;
            $ret->posts = $data->posts;
            $ret->post_count = $data->post_count;
        }
        return $ret;
    }
}
