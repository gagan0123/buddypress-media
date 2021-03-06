<?php
global $rt_media_query, $rt_media_media;
print_r($rt_media_media);

$model = new RTMediaModel();

$media = $model->get_media(array('id' => $rt_media_query->media_query['album_id']), false, false);
?>
<div class="rt-media-container rt-media-single-container">

    <form method="post">
        <?php
        RTMediaMedia::media_nonce_generator($rt_media_query->media_query['album_id']);
        $post_details = get_post($media[0]->media_id);
        $content = apply_filters('the_content', $post_details->post_content);
        ?>

        <input type="text" name="media_title" value="<?php echo esc_attr($media[0]->media_title); ?>" />
        <?php wp_editor($content, 'description', array('media_buttons' => false)); ?>
        <input type="submit" name="submit" value="Submit" />

    </form>
    <?php if (have_rt_media()) { ?>
        <br />
        <form class="rt-media-bulk-actions" method="post">
            <?php wp_nonce_field('rt_media_bulk_delete_nonce', 'rt_media_bulk_delete_nonce'); ?>
            <?php RTMediaMedia::media_nonce_generator($rt_media_query->media_query['album_id']); ?>
            <span class="rt-media-selection"><a class="select-all" href="#">Select All Visible</a> | 
                <a class="unselect-all" href="#">Unselect All Visible</a> | </span>
            <br />
            <input type="button" class="button rt-media-move" value="Move" />
            <input type="submit" name="delete-selected" class="button rt-media-delete-selected" value="Delete Selected" />
            <div class="rt-media-move-container">
                <?php $global_albums = get_site_option('rt-media-global-albums'); ?>
                <?php _e('Move selected media to', 'rt-media'); ?>
                <?php echo '<select name="album" class="rt-media-user-album-list">'.rt_media_user_album_list().'</select>'; ?>
                <input type="submit" class="rt-media-move-selected" name="move-selected" value="Move Selected" />
            </div>


            <ul class="rt-media-list  large-block-grid-5">

                <?php while (have_rt_media()) : rt_media(); ?>

                    <?php include ('media-gallery-item.php'); ?>

                <?php endwhile; ?>

            </ul>


            <!--  these links will be handled by backbone later
                                            -- get request parameters will be removed  -->
            <?php
            $display = '';
            if (rt_media_offset() != 0)
                $display = 'style="display:block;"';
            else
                $display = 'style="display:none;"';
            ?>
            <a id="rtMedia-galary-prev" <?php echo $display; ?> href="<?php echo rt_media_pagination_prev_link(); ?>">Prev</a>

            <?php
            $display = '';
            if (rt_media_offset() + rt_media_per_page_media() < rt_media_count())
                $display = 'style="display:block;"';
            else
                $display = 'style="display:none;"';
            ?>
            <a id="rtMedia-galary-next" <?php echo $display; ?> href="<?php echo rt_media_pagination_next_link(); ?>">Next</a>

        <?php } else { ?>
            <p><?php echo __("The album is empty.", "rt-media"); ?></p>
        <?php } ?>
    </form>


</div>