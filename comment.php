<?php

class XComment {


    public static $nest_comments = [];


    /**
     * @param $post_ID
     * @return array
     * @code
     *      di ( comment()->get_nested_comments_with_meta( get_the_ID() ) );
     * @endcode
     *
     */
    public function get_nested_comments_with_meta( $post_ID ) {
        self::$nest_comments = [];
        if ( ! get_comments_number( $post_ID ) ) return [];
        $comments = get_comments( [ 'post_id' => $post_ID ] );
        foreach ( $comments as $comment ) {
            $meta = get_comment_meta( $comment->comment_ID );
            foreach( $meta as $k => $arr ) {
                $comment->$k = $arr[0];
            }
        }
        ob_start();
        wp_list_comments(
            [
                'max_depth' => 10,
                'reverse_top_level' => 'asc',
                'avatar_size' => 0,
                'callback' => 'get_nested_comments_with_meta'
            ],
            $comments);
        $trash = ob_get_clean();
        return self::$nest_comments;
    }
}


function get_nested_comments_with_meta( $comment, $args, $depth ) {
    $parent_comment = null;
    //if ( $comment->comment_parent ) $parent_comment = get_comment($comment->comment_parent);
    $comment->depth = $depth;
    comment::$nest_comments[] = $comment;
}
