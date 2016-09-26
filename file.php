<?php

class XFile {

    public function __construct()
    {

    }

    /**
     *
     *
     *
     * @WARNING
     *
     *      1. It uses md5() to avoid of replacing same file name.
     *          Since it does not add 'tag' like '(1)', '(2) for files which has same file name.
     *
     *      2. It uses md5() to avoid character set problems. like some server does not support utf-8 nor ... Most of servers do not support spanish chars. some servers do not support Korean characters.
     *
     *      3. It uses md5() to avoid possible matters due to lack of developmemnt time.
     *
     */
    public function upload() {

        if ( empty( $_FILES ) ) return; // return on OPTIONS method call.

        xlog( $_REQUEST );
        $file = $_FILES['file'];
        xlog($file);
        if ( $file['error'] ) wp_send_json_error( xerror( 'FILE UPLOAD ERROR ' . $file['error'] ) );


        // Prepare to save
        $file_type = wp_check_filetype( basename( $file["name"] ), null ); // get file type
        $file_name = xapi_get_safe_filename( $file["name"] ); // get save filename to save
        $dir = wp_upload_dir(); // Get WordPress upload folder.
        $file_path = $dir['path'] . "/$file_name"; // Get Path of uploaded file.
        $file_url = $dir['url'] . "/$file_name"; // Get Path of uploaded file.

        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) wp_send_json_error( "Failed on moving uploaded file." );

        // Create a post of attachment.
        $attachment = array(
            'guid'              => $file_url,
            'post_author'       => 1,
            'post_mime_type'    => $file_type['type'],
            'post_name'         => $file['name'],
            'post_title'        => $file_name,
            'post_content'      => '',
            'post_status'       => 'inherit',
        );

        // This does not upload a file but creates a 'attachment' post type in wp_posts.
        $attach_id = @wp_insert_attachment( $attachment, $file_name );
        if ( $attach_id == 0 || is_wp_error( $attach_id ) ) wp_send_json_error( xerror( $attach_id ) );
        xlog("attach_id: $attach_id");
        update_attached_file( $attach_id, $file_path ); // update post_meta for the use of get_attached_file(), get_attachment_url();
        require_once 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
        wp_update_attachment_metadata( $attach_id,  $attach_data );
        xlog( $attach_data );

        wp_send_json_success([
            'id' => $attach_id,
            'url' => $file_url,
            'type' => $file_type['type']
        ]);

    }

}