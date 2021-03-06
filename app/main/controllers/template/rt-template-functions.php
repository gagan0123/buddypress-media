<?php

/**
 * Checks at any point of time any media is left to be processed in the db pool
 * @global type $rt_media_query
 * @return type
 */
function have_rt_media() {
    global $rt_media_query;

    return $rt_media_query->have_media();
}

/**
 * Rewinds the db pool of media album and resets it to begining
 * @global type $rt_media_query
 * @return type
 */
function rewind_rt_media() {

    global $rt_media_query;

    return $rt_media_query->rewind_media();
}

/**
 * moves ahead in the loop of media within the album
 * @global type $rt_media_query
 * @return type
 */
function rt_media() {
    global $rt_media_query;

    return $rt_media_query->rt_media();
}

/**
 * echo the title of the media
 * @global type $rt_media_media
 */
function rt_media_title() {

    global $rt_media_backbone;
    if ($rt_media_backbone) {
        echo '<%= media_title %>';
    } else {
        global $rt_media_media;
        return $rt_media_media->post_title;
    }
}

function rt_media_id() {
    global $rt_media_media;
    return $rt_media_media->id;
}

/**
 * echo parmalink of the media
 * @global type $rt_media_media
 */
function rt_media_permalink() {

    global $rt_media_backbone;

    if ($rt_media_backbone) {
        echo '<%= rt_permalink %>';
    } else {
        echo get_rt_media_permalink(rt_media_id());
    }
}

/*
 * echo http url of the media
 */

function rt_media_image($size = 'thumbnail', $return = 'src') {
    global $rt_media_media, $rt_media_backbone;
    $thumbnail_id = 0;

    if ($rt_media_backbone) {
        if ($return == "src")
            echo '<%= guid %>';
        if ($return == "width")
            echo '<%= width %>';
        if ($return == "height")
            echo '<%= height %>';
    } else if (isset($rt_media_media->media_type)) {
        if ($rt_media_media->media_type == 'album' ||
                $rt_media_media->media_type != 'photo') {
            $thumbnail_id = get_rtmedia_meta($rt_media_media->media_id, 'cover_art');
        } elseif ($rt_media_media->media_type == 'photo') {
            $thumbnail_id = $rt_media_media->media_id;
        } else {
            return false;
        }
    } else {
        return false;
    }

    if (!$thumbnail_id)
        return false;

    list($src, $width, $height) = wp_get_attachment_image_src($thumbnail_id, $size);

    if ($return == "src")
        echo $src;
    if ($return == "width")
        echo $width;
    if ($return == "height")
        echo $height;
}

function rt_media_delete_allowed() {
    global $rt_media_media;

    $flag = $rt_media_media->media_author == get_current_user_id();

    $flag = apply_filters('rt_media_media_delete_priv', $flag);

    return $flag;
}

function rt_media_edit_allowed() {

    global $rt_media_media;

    $flag = $rt_media_media->media_author == get_current_user_id();

    $flag = apply_filters('rt_media_media_edit_priv', $flag);

    return $flag;
}

function rt_media_request_action() {
    global $rt_media_query;
    return $rt_media_query->action_query->action;
}

function rt_media_title_input() {
    global $rt_media_media;

    $name = 'media_title';
    $value = $rt_media_media->media_title;

    $html = '';

    if (rt_media_request_action() == 'edit')
        $html .= '<input type="text" name="' . $name . '" id="' . $name . '" value="' . $value . '">';
    else
        $html .= '<h2 name="' . $name . '" id="' . $name . '">' . $value . '</h2>';

    $html .= '';

    return $html;
}

function rt_media_description_input() {
    global $rt_media_media;

    $name = 'description';
    $value = $rt_media_media->post_content;

    $html = '';

    if (rt_media_request_action() == 'edit')
        $html .= wp_editor($value, $name, array('media_buttons' => false));
    else
        $html .= '<div name="' . $name . '" id="' . $name . '">' . $value . '</div>';

    $html .= '';

    return $html;
}

/**
 * echo media description
 * @global type $rt_media_media
 */
function rt_media_description() {
    global $rt_media_media;
    echo $rt_media_media->post_content;
}

/**
 * returns total media count in the album
 * @global type $rt_media_query
 * @return type
 */
function rt_media_count() {
    global $rt_media_query;

    return $rt_media_query->media_count;
}

/**
 * returns the page offset for the media pool
 * @global type $rt_media_query
 * @return type
 */
function rt_media_offset() {
    global $rt_media_query;

    return ($rt_media_query->action_query->page - 1) * $rt_media_query->action_query->per_page_media;
}

/**
 * returns number of media per page to be displayed
 * @global type $rt_media_query
 * @return type
 */
function rt_media_per_page_media() {
    global $rt_media_query;

    return $rt_media_query->action_query->per_page_media;
}

/**
 * returns the page number of media album in the pagination
 * @global type $rt_media_query
 * @return type
 */
function rt_media_page() {
    global $rt_media_query;

    return $rt_media_query->action_query->page;
}

/**
 * returns the current media number in the album pool
 * @global type $rt_media_query
 * @return type
 */
function rt_media_current_media() {
    global $rt_media_query;

    return $rt_media_query->current_media;
}

/**
 *
 */
function rt_media_actions() {
    global $rt_media_query;
    $actions = $rt_media_query->actions;

    unset($actions['edit']);
    unset($actions['comment']);
    unset($actions['delete']);
    unset($actions['merge']);

	
//        if (!is_rt_media_album())
//            unset($actions['merge']);
    //render edit button here
    if (is_rt_media_album()) {
        $id = $rt_media_query->media_query['album_id'];
    } else {
        $id = $rt_media_query->action_query->id;
    }

	foreach ($actions as $action=>$details ){
		$button = '';
		if($details[1]!=false){
		$button .= '<form action="'.get_rt_media_permalink($rt_media_query->action_query->id).$action.'/" method="post">';
		$button .= wp_nonce_field( $rt_media_query->action_query->id, 'rt_media_user_action_'.$action.'_nonce', true, false );
		$button .= '<input type="submit" class="rt-media-'.$action.'" value="'.$details[0].'">';
		$button .= '</form>';
		}
		echo $button;
	}

	// render delete button here
}

/**
 * 	rendering comments section
 */
function rt_media_comments() {

    $html = '<ul>';

    global $wpdb, $rt_media_media;

    $comments = $wpdb->get_results("SELECT * FROM wp_comments WHERE comment_post_ID = '" . $rt_media_media->id . "'", ARRAY_A);

    foreach ($comments as $comment) {
        $html .= '<li class="rt-media-comment">';
        $html .= '<div class ="rt-media-comment-author">' . (($comment['comment_author']) ? $comment['comment_author'] : 'Annonymous') . '  said : </div>';
        $html .= '<div class="rt-media-comment-content">' . $comment['comment_content'] . '</div>';
        $html .= '<div class ="rt-media-comment-date"> on ' . $comment['comment_date_gmt'] . '</div>';
//			$html .= '<a href></a>';
        $html .= '</li>';
    }

    $html .= '</ul>';

    echo $html;
}

function rt_media_pagination_prev_link() {

    global $rt_media_media, $rt_media_interaction;

    $page_url = ((rt_media_page() - 1) == 1) ? "" : "/pg/" . (rt_media_page() - 1);

    if ($rt_media_interaction->context->type == "profile") {
        if (class_exists("BuddyPress"))
            $link = get_site_url() . '/members/' . get_query_var('author_name') . '/media/' . $page_url;
        else
            $link = get_site_url() . '/author/' . get_query_var('author_name') . '/media/' . $page_url;
    } else if ($rt_media_interaction->context->type == 'group') {
        if (function_exists("bp_get_current_group_slug"))
            $link = get_site_url() . '/groups/' . bp_get_current_group_slug() . '/media/' . $page_url;
    } else {
        $post = get_post($rt_media_media->post_parent);

        $link = get_site_url() . '/' . $post->post_name . '/media/' . $page_url;
    }
    return $link;
}

function rt_media_pagination_next_link() {

    global $rt_media_media, $rt_media_interaction;

    if ($rt_media_interaction->context->type == "profile") {
        if (function_exists("bp_core_get_user_domain"))
            $link = bp_core_get_user_domain($rt_media_media->media_author) . 'media/pg/' . (rt_media_page() + 1);
        else
            $link = get_site_url() . '/author/' . get_query_var('author_name') . '/media/pg/' . (rt_media_page() + 1);
    } else if ($rt_media_interaction->context->type == 'group') {
        if (function_exists("bp_get_current_group_slug"))
            $link = get_site_url() . '/groups/' . bp_get_current_group_slug() . '/media/pg/' . (rt_media_page() + 1);
    } else {
        $post = get_post($rt_media_media->post_parent);

        $link = get_site_url() . '/' . $post->post_name . '/media/pg/' . (rt_media_page() + 1);
    }
    return $link;
}

function rt_media_url() {

    global $rt_media_media;

    $post = get_post($rt_media_media->post_parent);

    $link = get_site_url() . '/' . $post->post_name . '/media/' . $rt_media_media->id;

    return $link;
}

function rt_media_comments_enabled() {
    return rt_media_get_site_option('general_enableComments') && is_user_logged_in();
}

/**
 *
 * @return boolean
 */
function is_rt_media_gallery() {
    global $rt_media_query;
    return $rt_media_query->is_gallery();
}

/**
 *
 * @return boolean
 */
function is_rt_media_album_gallery() {
    global $rt_media_query;
    return $rt_media_query->is_album_gallery();
}

/**
 *
 * @return boolean
 */
function is_rt_media_single() {
    global $rt_media_query;
    return $rt_media_query->is_single();
}

/**
 *
 * @return boolean
 */
function is_rt_media_album() {
    global $rt_media_query;
    return $rt_media_query->is_album();
}

function rt_media_image_editor() {

    RTMediaTemplate::enqueue_image_editor_scripts();
    global $rt_media_query;
    $media_id = $rt_media_query->media[0]->media_id;
    $id = $rt_media_query->media[0]->id;
    //$editor = wp_get_image_editor(get_attached_file($id));
    include_once( ABSPATH . 'wp-admin/includes/image-edit.php' );
    echo '<div class="rt-media-image-editor-cotnainer">';
    echo '<div class="rt-media-image-editor" id="image-editor-' . $media_id . '"></div>';
    $thumb_url = wp_get_attachment_image_src($media_id, 'thumbnail', true);
    $nonce = wp_create_nonce("image_editor-$media_id");
    echo '<div id="imgedit-response-' . $media_id . '"></div>';
    echo '<div class="wp_attachment_image" id="media-head-' . $media_id . '">
				<p id="thumbnail-head-' . $id . '"><img class="thumbnail" src="' . set_url_scheme($thumb_url[0]) . '" alt="" /></p>
	<p><input type="button" class="rt-media-image-edit" id="imgedit-open-btn-' . $media_id . '" onclick="imageEdit.open( \'' . $media_id . '\', \'' . $nonce . '\' )" class="button" value="Modifiy Image"> <span class="spinner"></span></p></div>';
    echo '</div>';
}

function rt_media_comment_form() {

    $html = '<form method="post" action="' . get_rt_media_permalink(rt_media_id()) . 'comment/" style="width: 400px;">';
    $html .= '<textarea rows="4" name="comment_content" id="comment_content"></textarea>';
    $html .= '<input type="submit" value="Comment">';
    echo $html;
    RTMediaComment::comment_nonce_generator();
    echo '</form>';
}

function rt_media_delete_form() {

    $html = '<form method="post" acction="' . get_rt_media_permalink(rt_media_id()) . 'delete/">';
    $html .= '<input type="hidden" name="id" id="id" value="' . rt_media_id() . '">';
    $html .= '<input type="hidden" name="request_action" id="request_action" value="delete">';
    echo $html;
    RTMediaMedia::media_nonce_generator(rt_media_id(), true);
    echo '<input type="submit" value="Delete"></form>';
}

/**
 *
 * @param type $attr
 */
function rt_media_uploader($attr = '') {

    if (function_exists('bp_is_blog_page') && !bp_is_blog_page()) {
        if (function_exists('bp_is_user') && bp_is_user() && function_exists('bp_displayed_user_id') && bp_displayed_user_id() == get_current_user_id())
            echo RTMediaUploadShortcode::pre_render($attr);
        else if (function_exists('bp_is_group') && bp_is_group() && function_exists('bp_group_is_member') && bp_group_is_member())
            echo RTMediaUploadShortcode::pre_render($attr);
    }
}

function rt_media_gallery($attr = '') {
    echo RTMediaGalleryShortcode::render($attr);
}

function get_rtmedia_meta($id = false, $key = false) {
    $rtmediameta = new RTMediaMeta();
    return $rtmediameta->get_meta($id, $key);
}

function add_rtmedia_meta($id = false, $key = false, $value = false, $duplicate = false) {
    $rtmediameta = new RTMediaMeta($id, $key, $value, $duplicate);
    return $rtmediameta->add_meta($id, $key, $value, $duplicate);
}

function update_rtmedia_meta($id = false, $key = false, $value = false, $duplicate = false) {
    $rtmediameta = new RTMediaMeta();
    return $rtmediameta->update_meta($id, $key, $value, $duplicate);
}

function delete_rtmedia_meta($id = false, $key = false) {
    $rtmediameta = new RTMediaMeta();
    return $rtmediameta->delete_meta($id, $key);
}

function rt_media_user_album_list() {
    global $rt_media_query;
    $global_albums = get_site_option('rt-media-global-albums');
    $option = NULL;
    foreach ($global_albums as $album) {
        $model = new RTMediaModel();
        $album_object = $model->get_media(array('id' => $album), false, false);
        $global_album_ids[] = $album_object[0]->id;
        if ( (isset($rt_media_query->media_query['album_id']) && ($album_object[0]->id != $rt_media_query->media_query['album_id'])) || !isset($rt_media_query->media_query['album_id']))
            $option .= '<option value="' . $album_object[0]->id . '">' . $album_object[0]->media_title . '</option>';
    }
    $album_objects = $model->get_media(array('media_author' => get_current_user_id(), 'media_type' => 'album'), false, false);
    if ($album_objects) {
        foreach ($album_objects as $album) {
            if (!in_array($album->id, $global_album_ids) && (( isset($rt_media_query->media_query['album_id']) && ($album->id != $rt_media_query->media_query['album_id'])) || !isset($rt_media_query->media_query['album_id']) ))
                $option .= '<option value="' . $album->id . '">' . $album->media_title . '</option>';
        }
    }

    if ( $option )
        return $option;
    else
        return false;
}

add_action('rtmedia_before_media_gallery', 'rt_media_create_album');
function rt_media_create_album(){
    global $rt_media_query;

    if (bp_displayed_user_id() == get_current_user_id() ) {
        if(isset($rt_media_query->query['context']) && !isset($rt_media_query->media_query['album_id']) && in_array($rt_media_query->query['context'],array('profile','group'))){ ?>
            <input type=button class="button rt-media-create-new-album-button" value="Create New Album" />
            <div class="rt-media-create-new-album-container">
                <input type="text" class="rt-media-new-album-name" value="" />
                <input type="submit" class="rt-media-create-new-album" value="Create Album" />
            </div><?php
        }
    }
}

add_action('rtmedia_before_media_gallery', 'rt_media_album_edit');
function rt_media_album_edit() {

    if (!is_rt_media_album() || !is_user_logged_in())
        return;

    global $rt_media_query;
    if (isset($rt_media_query->media_query) && get_current_user_id() == $rt_media_query->media_query['media_author'] && !in_arraY($rt_media_query->media_query['album_id'],get_site_option('rt-media-global-albums'))) {
        ?>
        <a class="alignleft" href="edit/"><input type="button" class="button rt-media-edit" value="<?php _e('Edit','rt-media'); ?>" /></a>
        <form method="post" class="album-delete-form alignleft" action="delete/">
            <?php wp_nonce_field('rt_media_delete_album_' . $rt_media_query->media_query['album_id'], 'rt_media_delete_album_nonce'); ?>
            <input type="submit" name="album-delete" value="<?php _e('Delete', 'rt-media'); ?>" />
        </form>
        <?php if ( $album_list = rt_media_user_album_list() ) { ?>
        <input type="button" class="button rt-media-merge" value="<?php _e('Merge','rt-media'); ?>" />
        <div class="rt-media-merge-container">
            <?php _e('Merge to', 'rt-media'); ?>
            <form method="post" class="album-merge-form" action="merge/">
                <?php echo '<select name="album" class="rt-media-merge-user-album-list">'.$album_list.'</select>'; ?>
                <?php wp_nonce_field('rt_media_merge_album_' . $rt_media_query->media_query['album_id'], 'rt_media_merge_album_nonce'); ?>
                <input type="submit" class="rt-media-move-selected" name="merge-album" value="<?php _e('Merge Album','rt-media'); ?>" />
            </form>
        </div>
        <?php
        }
    }
}

add_action('rtmedia_before_item', 'rt_media_item_select');

function rt_media_item_select() {
    global $rt_media_query, $rt_media_backbone;
    if ($rt_media_backbone) {
        echo '<input type="checkbox" name="move[]" value="<%= id %>" />';
    } else if (is_rt_media_album() && isset($rt_media_query->media_query) && get_current_user_id() == $rt_media_query->media_query['media_author'] && $rt_media_query->action_query->action == 'edit') {
        echo '<input type="checkbox" name="selected[]" value="' . rt_media_id() . '" />';
    }
}

add_action('rt_media_query_actions', 'rt_media_album_merge_action');

function rt_media_album_merge_action($actions) {
    $actions['merge'] = __('Merge', 'rt-media');
    return $actions;
}
?>
