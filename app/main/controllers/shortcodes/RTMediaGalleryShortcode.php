<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RTMediaGalleryShortcode
 *
 * rtMedia Gallery Shortcode to embedd a gallery of media anywhere
 *
 * @author Udit Desai <udit.desai@rtcamp.com>
 */
class RTMediaGalleryShortcode {

	static $add_script;

	/**
	 *
	 */
	public function __construct() {

		add_shortcode('rtmedia_gallery', array('RTMediaGalleryShortcode', 'render'));
		add_action('init', array($this, 'register_scripts'));
		add_action('wp_footer', array($this, 'print_script'));
	}

	function register_scripts() {
                wp_enqueue_script('plupload-all');
		//wp_register_script('rtmedia-models', RT_MEDIA_URL . 'app/assets/js/backbone/models.js', array('backbone'));
		//wp_register_script('rtmedia-collections', RT_MEDIA_URL . 'app/assets/js/backbone/collections.js', array('backbone', 'rtmedia-models'));
		//wp_register_script('rtmedia-views', RT_MEDIA_URL . 'app/assets/js/backbone/views.js', array('backbone', 'rtmedia-collections'));
		wp_register_script('rtmedia-backbone', RTMEDIA_URL . 'app/assets/js/rtMedia.backbone.js', array('plupload','backbone'));
		wp_localize_script('rtmedia-backbone', 'template_url', RTMEDIA_URL . 'templates/media');
                $url = $_SERVER["REQUEST_URI"];
                $url = trailingslashit($url);
                
		$params = array(
                    'url' => (isset($url) && (strpos($url,"/media/") !== false))?str_replace("/media/", "/upload/", $url):'upload/',
                    'runtimes' => 'gears,html5,flash,silverlight,browserplus',
                    'browse_button' => 'rtMedia-upload-button',
                    'container' => 'rtmedia-upload-container',
                    'drop_element' => 'drag-drop-area',
                    'filters' => apply_filters('bp_media_plupload_files_filter', array(array('title' => "Media Files", 'extensions' => "mp4,jpg,png,jpeg,gif,mp3"))),
                    'max_file_size' => min(array(ini_get('upload_max_filesize'), ini_get('post_max_size'))),
                    'multipart' => true,
                    'urlstream_upload' => true,
                    'flash_swf_url' => includes_url('js/plupload/plupload.flash.swf'),
                    'silverlight_xap_url' => includes_url('js/plupload/plupload.silverlight.xap'),
                    'file_data_name' => 'rt_media_file', // key passed to $_FILE.
                    'multi_selection' => true,
                    'multipart_params' => apply_filters('rt-media-multi-params', array('redirect'=>'no','action' => 'wp_handle_upload','_wp_http_referer'=> $_SERVER['REQUEST_URI'],'mode'=>'file_upload','rt_media_upload_nonce'=>RTMediaUploadView::upload_nonce_generator(false,true)))
                );
                wp_localize_script('rtmedia-backbone', 'rtMedia_plupload_config', $params);
	}

	/**
	 * Helper function to check whether the shortcode should be rendered or not
	 *
	 * @return type
	 */
	static function display_allowed() {

		$flag = !(is_home() || is_post_type_archive() || is_author());
		$flag = apply_filters('before_rtmedia_gallery_display', $flag);
		return $flag;
	}

	/**
	 * Render a shortcode according to the attributes passed with it
	 *
	 * @param boolean $attr
	 */
	static function render($attr) {
		if (self::display_allowed()) {
			self::$add_script = true;

			ob_start();

			if ((!isset($attr)) || empty($attr))
				$attr = true;

			$attr = array('name' => 'gallery', 'attr' => $attr);

			$template = new RTMediaTemplate();
			$template->set_template('media-gallery', $attr);

			return ob_get_clean();
		}
	}

	static function print_script() {
		if (!self::$add_script)
			return;
		if (!wp_script_is('rtmedia-backbone')){
			wp_print_scripts('rtmedia-backbone');
		}
	}

}

?>
