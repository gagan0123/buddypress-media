<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RTMediaMedia
 *
 * @author Udit Desai <udit.desai@rtcamp.com>
 */
class RTMediaMedia {

    /**
     * DB Model object to interact on Database operations
     *
     * @var object the database model
     */
    var $model;

    /**
     * Initialises the model object of the mediua object
     */
    public function __construct() {

        $this->model = new RTMediaModel();
    }

    /**
     * Generate nonce
     * @param boolean $echo whether nonce should be echoed
     * @return string json encoded nonce
     */
    static function media_nonce_generator($id, $echo = true) {
        if ($echo) {
            wp_nonce_field('rt_media_' . $id, 'rt_media_media_nonce');
        } else {
            $token = array(
                'action' => 'rt_media_media_nonce',
                'nonce' => wp_create_nonce('rt_media_' . $id)
            );

            return json_encode($token);
        }
    }

    /**
     * Method verifies the nonce passed while performing any CRUD operations
     * on the media.
     *
     * @param string $mode The upload mode
     * @return boolean whether the nonce is valid
     */
    function verify_nonce($mode) {

        $nonce = $_REQUEST["rt_media_{$mode}_media_nonce"];
        $mode = $_REQUEST['mode'];

        if (wp_verify_nonce($nonce, 'rt_media_' . $mode))
            return true;
        else
            return false;
    }

    /**
     * Adds a hook to delete_attachment tag called
     * when a media is deleted externally out of rtMedia context
     */
    public function delete_hook() {
        add_action('delete_attachment', array($this, 'delete'));
    }

    /**
     * Adds taxonomy
     * @param array $attachments ids of the attachments created after upload
     * @param array $taxonomies array of terms indexed by a taxonomy
     */
    function add_taxonomy($attachments, $taxonomies) {

        foreach ($attachments as $id) {

            foreach ($taxonomies as $taxonomy => $terms) {
                if (!taxonomy_exists($taxonomy)) {
                    continue;
                }

                wp_set_object_terms($id, $terms, $taxonomy);
            }
        }
    }

    /**
     *
     * @param array $attachments attachment ids
     * @param array $custom_fields array of key value pairs of meta
     * @return boolean success of meta
     */
    function add_meta($attachments, $custom_fields) {

        foreach ($attachments as $id) {
            $row = array('media_id' => $id);

            foreach ($custom_fields as $key => $value) {

                if (!is_null($value)) {
                    $row['meta_key'] = $key;
                    $row['meta_value'] = $value;
                    $status = add_rtmedia_meta($id, $key, $value);

                    if (get_class($status) == 'WP_Error' || $status == 0)
                        return false;
                }
            }
        }

        return true;
    }

    /**
     * Helper method to check for multisite - will add a few additional checks
     * for handling taxonomies
     * @return boolean
     */
    function is_multisite() {
        return is_multisite();
    }

    /**
     * Generic method to add a media
     *
     * @param type $uploaded
     * @param type $file_object
     * @return type
     */
    function add($uploaded, $file_object) {

        /* action to perform any task before adding a media */
        do_action('rt_media_before_add_media', $this);

        /* Generate media details required to feed in database */
        $attachments = $this->generate_post_array($uploaded, $file_object);

        /* Insert the media as an attachment in Wordpress context */
        $attachment_ids = $this->insert_attachment($attachments, $file_object);

        /* check for multisite and if valid then add taxonomies */
        if (!$this->is_multisite())
            $this->add_taxonomy($attachment_ids, $uploaded['taxonomy']);

        /* fetch custom fields and add them to meta table */
        $this->add_meta($attachment_ids, $uploaded['custom_fields']);


        /* add media in rtMedia context */
        $media_ids = $this->insert_media($attachment_ids, $uploaded);

        /* action to perform any task after adding a media */
        do_action('rt_media_after_add_media', $this);

        return $media_ids;
    }

    /**
     * Generic method to update a media. media details can be changed from this method
     *
     * @param type $media_id
     * @param type $meta
     * @return boolean
     */
    function update($id, $data, $media_id) {

        /* action to perform any task before updating a media */
        do_action('rt_media_before_update_media', $this);

        $defaults = array();
        $data = wp_parse_args($data, $defaults);
        $where = array('id' => $id);

        if (array_key_exists('media_title', $data) || array_key_exists('description', $data)) {
            $post_data['ID'] = $media_id;
            if (isset($data['media_title'])) {
                $post_data['post_title'] = $data['media_title'];
                $post_data['post_name'] = sanitize_title($data['media_title']);
            }
            if (isset($data['description'])) {
                $post_data['post_content'] = $data['description'];
                unset($data['description']);
            }
            wp_update_post($post_data);
        }

        $status = $this->model->update($data, $where);

        if ($status == 0) {
            return false;
        } else {
            /* action to perform any task after updating a media */
            do_action('rt_media_after_update_media', $this);
            return true;
        }
    }

    /**
     * Generic method to delete a media
     *
     * @param type $media_id
     * @return boolean
     */
    function delete($id) {

        do_action('rt_media_before_delete_media', $this);

        $media = $this->model->get(array('id' => $id), false, false);

        $status = 0;

        if ($media) {
            /* delete meta */
            delete_rtmedia_meta($id);
            wp_delete_post($media[0]->media_id, true);
            $status = $this->model->delete(array('id' => $id));
        }

        if ($status == 0) {
            return false;
        } else {
            do_action('rt_media_after_delete_media', $this);
            return true;
        }
    }

    /**
     * Move a media from one album to another
     *
     * @global type $wpdb
     * @param type $media_id
     * @param type $album_id
     * @return boolean
     */
    function move($media_id, $album_id) {

        global $wpdb;
        /* update the post_parent value in wp_post table */
        $status = $wpdb->update('wp_posts', array('post_parent' => $album_id), array('ID' => $media_id));

        if (get_class($status) == 'WP_Error' || $status == 0) {
            return false;
        } else {
            /* update album_id, context, context_id and privacy in rtMedia context */
            $album_data = $this->model->get(array('media_id' => $media_id));
            $data = array(
                'album_id' => $album_id,
                'context' => $album_data->context,
                'context_id' => $album_data->context_id,
                'privacy' => $album_data->privacy
            );
            return $this->update($media_id, $data);
        }
    }

    /**
     *  Imports attachment as media
     */
    function import_attachment() {

    }

    /**
     * Check if BuddyPress and the activity component are enabled
     * @return boolean
     */
    function activity_enabled() {

        if (!function_exists('bp_is_active') || !bp_is_active('activity'))
            return false;

        return rt_media_get_site_option('rt-media-enable-on-activity');
    }

    /**
     *
     * @param type $uploaded
     * @param type $file_object
     * @return type
     */
    function generate_post_array($uploaded, $file_object) {
        if ($uploaded['album_id']) {
            $model = new RTMediaModel();
            $parent_details = $model->get(array('id' => $uploaded['album_id']));
            $album_id = $parent_details[0]->media_id;
        } else {
            $album_id = 0;
        }

        foreach ($file_object as $file) {
            $attachments[] = array(
                'post_mime_type' => $file['type'],
                'guid' => $file['url'],
                'post_title' => $uploaded['title'] ? $uploaded['title'] : $file['name'],
                'post_content' => $uploaded['description'] ? $uploaded['description'] : '',
                'post_parent' => $album_id,
                'post_author' => $uploaded['media_author']
            );
        }
        return $attachments;
    }

    /**
     *
     * @param type $attachments
     * @param type $file_object
     * @return type
     * @throws Exception
     */
    function insert_attachment($attachments, $file_object) {
        foreach ($attachments as $key => $attachment) {
            $attachment_id = wp_insert_attachment($attachment, $file_object[$key]['file'], $attachment['post_parent']);
            if (!is_wp_error($attachment_id)) {
//                add_filter('intermediate_image_sizes', array($this, 'rt_media_image_sizes'), 99);
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_object[$key]['file']));
            } else {
                unlink($file_object[$key]['file']);
                throw new Exception(__('Error creating attachment for the media file, please try again', 'buddypress-media'));
            }
            $updated_attachment_ids[] = $attachment_id;
        }

        return $updated_attachment_ids;
    }

    /**
     *
     * @param type $sizes
     * @return type
     */
    function image_sizes($sizes) {
        return array('bp_media_thumbnail', 'bp_media_activity_image', 'bp_media_single_image');
    }

    /**
     *
     * @param type $attributes
     */
    function insert_album($attributes) {

        return $this->model->insert($attributes);
    }

    function set_media_type($mime_type) {
        switch ($mime_type) {
            case 'image': return 'photo';
            case 'audio': return 'music';
            default: return $mime_type;
        }
    }

    /**
     *
     * @param type $attachment_ids
     * @param type $uploaded
     */
    function insert_media($attachment_ids, $uploaded) {

        $defaults = array(
            'activity_id' => $this->activity_enabled(),
            'privacy' => false
        );

        $uploaded = wp_parse_args($uploaded, $defaults);

        $blog_id = get_current_blog_id();
        $media_id = Array();
        foreach ($attachment_ids as $id) {
            $attachment = get_post($id, ARRAY_A);
            $mime_type = explode('/', $attachment['post_mime_type']);
            $media = array(
                'blog_id' => $blog_id,
                'media_id' => $id,
                'album_id' => $uploaded['album_id'],
                'media_author' => $attachment['post_author'],
                'media_title' => $attachment['post_title'],
                'media_type' => ($mime_type[0] == 'image') ? 'photo' : $mime_type[0],
                'context' => $uploaded['context'],
                'context_id' => $uploaded['context_id'],
                'privacy' => $uploaded['privacy']
            );

            $media_id[] = $this->model->insert($media);
        }
        return $media_id;
    }

    function insert_activity($id, $media) {
        if (!$this->activity_enabled())
            return;
        $activity = new RTMediaActivity($id, $media->privacy);
        $activity_content = $activity->create_activity_html();
        $user = get_userdata($media->media_author);
        $username = '<a href="' . get_rt_media_user_link($media->media_author) . '">' . $user->user_nicename . '</a>';
        $count = count($id);
        $media_const = 'RTMEDIA_' . strtoupper($media->media_type);
        if ($count > 1) {
            $media_const .= '_PLURAL';
        }
        $media_const.='_LABEL';

        $media_str = constant($media_const);

        $action = sprintf(
                _n(
                        '%s added a %s', '%s added %d %s.', $count, 'rt-media'
                ), $username, $media->media_type, $media_str
        );

        $activity_args = array(
            'action' => $action,
            'content' => $activity_content,
            'type' => 'rtmedia_update',
            'primary_link' => '',
            'item_id' => $id
        );
        if ($media->context == 'group' || 'profile') {
            $activity_args['component'] = $media->context;
        }

        $activity_id = bp_activity_add($activity_args);


        $this->model->update(
                array('activity_id' => $activity_id), array('id' => $id)
        );

        return $activity_id;
    }

}

?>
