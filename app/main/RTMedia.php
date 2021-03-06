<?php

/**
 * Don't load this file directly!
 */
if (!defined('ABSPATH'))
	exit;

/**
 * BuddyPress Media
 *
 * The main BuddyPress Media Class. This is where everything starts.
 *
 * @package BuddyPressMedia
 * @subpackage Main
 *
 * @author Saurabh Shukla <saurabh.shukla@rtcamp.com>
 * @author Gagandeep Singh <gagandeep.singh@rtcamp.com>
 * @author Joshua Abenazer <joshua.abenazer@rtcamp.com>
 */
class RTMedia {

	/**
	 * @var string default thumbnail url fallback for all media types
	 */
	private $default_thumbnail;
	/**
	 *
	 * @var array allowed media types
	 */
	public $allowed_types;
	/**
	 *
	 * @var array privacy settings
	 */
	public $privacy_settings;
	/**
	 *
	 * @var array default media sizes
	 */
	public $default_sizes;

	/**
	 *
	 * @var object default application wide privacy levels
	 */
	public $default_privacy = array(
		'0'		=> 'Public',
		'20'	=> 'Users',
		'40'	=> 'Friends',
		'60'	=> 'Private'
	);


	/**
	 *
	 * @var string Support forum url
	 */
	public $support_url = 'http://rtcamp.com/support/forum/buddypress-media/?utm_source=dashboard&utm_medium=plugin&utm_campaign=buddypress-media';
	/**
	 *
	 * @var int Number of media items to show in one view.
	 */
	public $posts_per_page = 10;

	/**
	 *
	 * @var array The types of activity BuddyPress Media creates
	 */
	public $activity_types = array(
		'media_upload',
		'album_updated',
		'album_created'
	);


	public $options;
	public $render_options;


	/**
	 * Constructs the class
	 * Defines constants and excerpt lengths, initiates admin notices,
	 * loads and initiates the plugin, loads translations.
	 * Initialises media counter
	 *
	 * @global int $bp_media_counter Media counter
	 */
	public function __construct() {

		// Rewrite API flush before activating and after deactivating the plugin
		register_activation_hook(__FILE__, array($this, 'flush_rewrite'));
		register_deactivation_hook(__FILE__, array($this, 'flush_rewrite'));

		$this->default_thumbnail = apply_filters('rtmedia_default_thumbnail',RTMEDIA_URL. 'assets/thumb_default.png');
		// Define allowed types
		$this->set_allowed_types();

		$this->constants(); // Define constants

		// check for global album --- after wordpress is fully loaded
		add_action('init', array($this, 'check_global_album'));

		// Hook it to WordPress
		add_action('plugins_loaded', array($this, 'init'));

		// Load translations
		add_action('plugins_loaded', array($this, 'load_translation'));

		//Admin Panel
		add_action('init', array($this, 'admin_init'));

		$this->set_default_sizes(); // set default sizes

		$this->set_privacy(); // set privacy

		/**
		 * Load options/settings
		 */
		$this->set_site_options();

		//  Enqueue Plugin Scripts and Styles
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_styles'), 11);

		/* Includes db specific wrapper functions required to render the template */
		include(RTMEDIA_PATH . 'app/main/controllers/template/rt-template-functions.php');
	}

	function set_site_options() {

		$rt_media_options = rt_media_get_site_option('rt-media-options');
		$bp_media_options = rt_media_get_site_option('bp_media_options');

		if($rt_media_options == false) {
			$this->init_site_options();
		} else {
			/* if new options added via filter then it needs to be updated */
			$this->options = $rt_media_options;
		}
	}

	/**
	 *  Default allowed media types array
	 */
	function set_allowed_types(){
		$allowed_types = array(
			array(
					'name'	=> 'photo',
					'plural' => 'photos',
					'label' => __('Photo','rt-media'),
					'plural_label' => __('Photos','rt-media'),
					'extn' => array('jpeg', 'png'),
					'thumbnail' => RTMEDIA_URL.'assets/img/image_thumb.png'
				),
			array(
					'name'	=> 'video',
					'plural' => 'videos',
					'label' => __('Video','rt-media'),
					'plural_label' => __('Videos','rt-media'),
					'extn' => array('mp4'),
					'thumbnail' => RTMEDIA_URL.'assets/img/video_thumb.png'
				),
			array(
					'name'	=> 'music',
					'plural' => 'music',
					'label' => __('Music','rt-media'),
					'plural_label' => __('Music','rt-media'),
					'extn' => array('mp3'),
					'thumbnail' => RTMEDIA_URL.'assets/img/audio_thumb.png'
				)
		);

		// filter for hooking additional media types
		$allowed_types = apply_filters('rt_media_allowed_types', $allowed_types);

		// sanitize all the types
		$allowed_types = $this->sanitize_allowed_types($allowed_types);

		// set the allowed types property
		$this->allowed_types = $allowed_types;

	}

	/**
	 * Sanitize all media sizes after hooking custom media types
	 *
	 * @param array $allowed_types allowed media types after hooking custom types
	 * @return array $allowed_types sanitized media types
	 */
	function sanitize_allowed_types($allowed_types){
		// check if the array is formatted properly
		if(!is_array($allowed_types)&& count($allowed_types)<1) return;

		//loop through each type
		foreach($allowed_types as $key=>&$type){

			if(!isset($type['name']) || // check if a name is set
					empty($type['name']) ||
					!isset($type['extn']) || // check if file extensions are set
					empty($type['extn']) || strstr($type['name']," ") || strstr($type['name'],"_") ) {
				unset($allowed_types[$key]); // if not unset this type
				continue;
			}

			// if thumbnail is not supplied, use the default thumbnail
			if(!isset($type['thumbnail']) || empty($type['thumbnail'])){
				$type['thumbnail']= $this->default_thumbnail;
			}

		}
		return $allowed_types;
	}

	/**
	 * Set the default sizes
	 */
	function set_default_sizes(){
		$this->default_sizes = array(
			'photo' => array(
				'thumbnail' => array('width' => 150, 'height' => 150, 'crop' => 1),
				'medium' => array('width' => 320, 'height' => 240, 'crop' => 1),
				'large' => array('width' => 800, 'height' => 0, 'crop' => 1)
			),
			'video' => array(
				'activityPlayer' => array('width' => 320, 'height' => 240),
				'singlePlayer' => array('width' => 640, 'height' => 480)
			),
			'music' => array(
				'activityPlayer' => array('width' => 320),
				'singlePlayer' => array('width' => 640)
			),
			'featured' => array(
				'default' => array('width' => 100, 'height' => 100, 'crop' => 1)
			)
		);

		$this->default_sizes = apply_filters('rt_media_allowed_sizes', $this->default_sizes);

	}

	/**
	 * Set privacy options
	 */
	function set_privacy(){

		$this->privacy_settings = array(
			'levels' => array(
				60 => __('<strong>Private</strong> - Visible only to the user', 'rt-media'),
				40 => __('<strong>Friends</strong> - Visible to user\'s friends', 'rt-media'),
				20 => __('<strong>Users</strong> - Visible to registered users', 'rt-media'),
				0 => __('<strong>Public</strong> - Visible to the world', 'rt-media')
			)
		);
		$this->privacy_settings = apply_filters('rt_media_privacy_levels', $this->privacy_settings);

		if (function_exists("bp_is_active") && !bp_is_active('friends')) {
			unset($this->privacy_settings['levels'][40]);
		}
	}

	/**
	 * Load admin screens
	 *
	 * @global RTMediaAdmin $rt_media_admin Class for loading admin screen
	 */
	function admin_init() {
		global $rt_media_admin;
		$rt_media_admin = new RTMediaAdmin();
	}

	function media_screen(){
		return;
	}

	/**
	 * Load Custom tabs on BuddyPress
	 *
	 * @global object $bp global BuddyPress object
	 */

	function custom_media_nav_tab() {

		bp_core_new_nav_item( array(
			'name' => __( 'Media', 'rt-media' ),
			'slug' => 'media',
			'screen_function' => array($this,'media_screen')
		) );

		if(bp_is_group()) {
			global $bp;
			$bp->bp_options_nav[bp_get_current_group_slug()]['media'] = array(
				'name' => 'Media',
				'link' => ( (is_multisite()) ? get_site_url(get_current_blog_id()) : get_site_url() ) . '/groups/' . bp_get_current_group_slug().'/media',
				'slug' => 'media',
				'user_has_access' => true,
				'css_id' => 'rt-media-media-nav',
				'position' => 99
			);
		}
	}

	public function init_buddypress_options() {
		/**
		 * BuddyPress Settings
		 */

		$bp_media_options = rt_media_get_site_option('bp_media_options');

		$group = 0;
		if(isset($bp_media_options['enable_on_group']) && !empty($bp_media_options['enable_on_group']))
			$group = $bp_media_options['enable_on_group'];
		else if(function_exists("bp_is_active"))
			$group = bp_is_active('groups');
		$this->options['buddypress_enable_on_group'] = $group;

		$activity = 0;
		if(isset($bp_media_options['activity_upload']) && !empty($bp_media_options['activity_upload']))
			$activity = $bp_media_options['activity_upload'];
		else if(function_exists("bp_is_active"))
			$activity = bp_is_active('activity');
		$this->options['buddypress_enable_on_activity'] = $activity;

		$this->options['buddypress_enable_on_profile'] = 0;

		/* Last settings updated in options. Update them in DB & after this no other option would be saved in db */
		rt_media_update_site_option('rt-media-options', $this->options);

	}

	public function init_site_options() {

		$bp_media_options = rt_media_get_site_option('bp_media_options');

		$defaults = array(
			'general_enableAlbums' => 0,
			'general_enableComments' => 0,
			'general_downloadButton' => (isset($bp_media_options['download_enabled'])) ? $bp_media_options['download_enabled'] : 0,
			'general_enableLightbox' => (isset($bp_media_options['enable_lightbox'])) ? $bp_media_options['enable_lightbox'] : 0,
			'general_perPageMedia' => (isset($bp_media_options['default_count'])) ? $bp_media_options['default_count'] : 10,
			'general_enableMediaEndPoint' => 0,
			'general_showAdminMenu' => (isset($bp_media_options['show_admin_menu'])) ? $bp_media_options['show_admin_menu'] : 0,
		);


		foreach($this->allowed_types as $type){
			// invalid keys handled in sanitize method
			$defaults['allowedTypes_'.$type['name'].'_enabled'] = 1;
			$defaults['allowedTypes_'.$type['name'].'_featured'] = 0;
		}

		/* Previous Sizes values from buddypress is migrated */
		foreach ($this->default_sizes as $type => $typeValue) {
			foreach ($typeValue as $size => $sizeValue) {
				foreach ($sizeValue as $dimension => $value) {
					switch($type) {
						case 'photo':
							if(isset($bp_media_options['sizes']['image'][$size][$dimension]) && !empty($bp_media_options['sizes']['image'][$size][$dimension]))
								$value = $bp_media_options['sizes']['image'][$size][$dimension];
							break;
						case 'video':
						case 'music':
							$old = ($type=='video')?'video':($type=='music')?'audio':'';
							switch($size) {
								case 'activityPlayer':
									if(isset($bp_media_options['sizes'][$old]['medium'][$dimension]) && !empty($bp_media_options['sizes'][$old]['medium'][$dimension]))
										$value = $bp_media_options['sizes'][$old]['medium'][$dimension];
									break;
								case 'singlePlayer':
									if(isset($bp_media_options['sizes'][$old]['large'][$dimension]) && !empty($bp_media_options['sizes'][$old]['large'][$dimension]))
										$value = $bp_media_options['sizes'][$old]['large'][$dimension];
									break;
							}
							break;
					}
					$defaults['defaultSizes_'.$type.'_'.$size.'_'.$dimension] = $value;
				}
			}
		}

		/* Privacy */
		$defaults['privacy_enabled'] = (isset($bp_media_options['privacy_enabled'])) ? $bp_media_options['privacy_enabled'] : 0;
		$defaults['privacy_default'] = (isset($bp_media_options['default_privacy_level'])) ? $bp_media_options['default_privacy_level'] : 0;
		$defaults['privacy_userOverride'] = (isset($bp_media_options['privacy_override_enabled'])) ? $bp_media_options['privacy_override_enabled'] : 0;

		$this->options = $defaults;

		add_action('bp_include',array($this,'init_buddypress_options'));
	}

	/**
	 * Defines all the constants if undefined. Can be overridden by
	 * defining them elsewhere, say wp-config.php
	 */
	public function constants() {

		/* If the plugin is installed. */
		if (!defined('RTMEDIA_IS_INSTALLED'))
			define('RTMEDIA_IS_INSTALLED', 1);

		/* Current Version. */
		if (!defined('RTMEDIA_VERSION'))
			define('RTMEDIA_VERSION', '3.0 Beta');

		/* Required Version  */
		if (!defined('RTMEDIA_REQUIRED_BP'))
			define('RTMEDIA_REQUIRED_BP', '1.7');


		/* Slug Constants for building urls */

		/* Media slugs */

		if (!defined('RTMEDIA_MEDIA_SLUG'))
			define('RTMEDIA_MEDIA_SLUG', 'media');

		if (!defined('RTMEDIA_MEDIA_LABEL'))
			define('RTMEDIA_MEDIA_LABEL', __('Media','rt-media'));

		if (!defined('RTMEDIA_ALBUM_SLUG'))
			define('RTMEDIA_ALBUM_SLUG', 'album');

		if (!defined('RTMEDIA_ALBUM_PLURAL_SLUG'))
			define('RTMEDIA_ALBUM_PLURAL_SLUG', 'albums');

		if (!defined('RTMEDIA_ALBUM_LABEL'))
			define('RTMEDIA_ALBUM_LABEL', __('Album','rt-media'));

		if (!defined('RTMEDIA_ALBUM_PLURAL_LABEL'))
			define('RTMEDIA_ALBUM_PLURAL_LABEL', __('Albums','rt-media'));

		/* Upload slug */
		if (!defined('RTMEDIA_UPLOAD_SLUG'))
			define('RTMEDIA_UPLOAD_SLUG', 'upload');

		/* Upload slug */
		if (!defined('RTMEDIA_UPLOAD_LABEL'))
			define('RTMEDIA_UPLOAD_LABEL', __('Upload','rt-media'));


		$this->define_type_constants();


	}

	function define_type_constants(){

		if(!isset($this->allowed_types)) return;
		foreach($this->allowed_types as $type){

			if(!isset($type['name'])|| $type['name']==='')
				continue;

			$name = $type['name'];

			if(isset($type['plural'])&& $type['plural']!=''){
				$plural = $type['plural'];
			}else{
				$plural = $name.'s';
			}

			if(isset($type['label'])&& $type['label']!=''){
				$label = $type['label'];
			}else{
				$label = ucfirst($name);
			}

			if(isset($type['label_plural'])&& $type['label_plural']!=''){
				$label_plural = $type['label_plural'];
			}else{
				$label_plural = ucfirst($plural);
			}

			$slug = strtoupper($name);

			if(!defined('RTMEDIA_'.$slug.'_SLUG'))
					define('RTMEDIA_'.$slug.'_SLUG',$name);
			if(!defined('RTMEDIA_'.$slug.'_PLURAL_SLUG'))
					define('RTMEDIA_'.$slug.'_PLURAL_SLUG',$plural);
			if(!defined('RTMEDIA_'.$slug.'_LABEL'))
					define('RTMEDIA_'.$slug.'_LABEL',$label);
			if(!defined('RTMEDIA_'.$slug.'_PLURAL_LABEL'))
					define('RTMEDIA_'.$slug.'_PLURAL_LABEL',$label_plural);

		}


	}

	/**
	 * Hooks the plugin into BuddyPress via 'bp_include' action.
	 * Initialises the plugin's functionalities, options,
	 * loads media for Profiles and Groups.
	 * Creates Admin panels
	 * Loads accessory functions
	 *
	 * @global BPMediaAdmin $bp_media_admin
	 */
	function init() {

		/**
		 *
		 * Buddypress Media Auto Upgradation
		 */
		$this->update_db();

		/**
		 * Add a settings link to the Plugin list screen
		 */
//            add_filter('plugin_action_links', array($this, 'settings_link'), 10, 2);

		/**
		 * BuddyPress - Media Navigation Tab Inject
		 *
		 */
		if(class_exists('BuddyPress')) {
			add_action('bp_init', array($this,'custom_media_nav_tab'), 10,1);
		}

		/**
		 * Load accessory functions
		 */
//			new BPMediaActivity();
		$class_construct = array(
			'deprecated' => true,
			'interaction' => true,
			//'template'	=> false,
			'upload_shortcode' => false,
			'gallery_shortcode' => false,
			'upload_endpoint' => false,
				//'query'		=> false
		);
		$class_construct = apply_filters('bpmedia_class_construct', $class_construct);

		foreach ($class_construct as $key => $global_scope) {
			$classname = '';
			$ck = explode('_', $key);

			foreach ($ck as $cn) {
				$classname .= ucfirst($cn);
			}

			$class = 'RTMedia' . $classname;

			if (class_exists($class)) {
				if ($global_scope == true) {
					global ${'rt_media_' . $key};
					${'rt_media_' . $key} = new $class();
				} else {
					new $class();
				}
			}
		}

		new RTMediaBuddyPressActivity();
		$media = new RTMediaMedia();
		$media->delete_hook();


				global $rt_media_ajax;
				$rt_media_ajax = new RTMediaAJAX();


	}

	/**
	 * Loads translations
	 */
	static function load_translation() {
		load_plugin_textdomain('rt-media', false, basename(RTMEDIA_PATH) . '/languages/');
	}

	function flush_rewrite() {
		error_log('flush');
		flush_rewrite_rules();
	}

	function check_global_album() {
		$album = new RTMediaAlbum();
		$global_album = $album->get_default();
                //** Hack for plupload default name
                    if(isset($_POST["action"]) && isset($_POST["mode"]) && $_POST["mode"] == "file_upload"){
                        unset($_POST["name"]);
                    }

                //**
		if(!$global_album) {
			$global_album = $album->add_global(__("rtMedia Global Album","rt-media"));
		}
	}

	function default_count() {
		$count = $this->posts_per_page;
		if (array_key_exists('default_count', $this->options)) {
			$count = $this->options['default_count'];
		}
		$count = (!is_int($count)) ? 0 : $count;
		return (!$count) ? 10 : $count;
	}

	static function plugin_get_version($path = NULL) {
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		$path = ($path) ? $path : RTMEDIA_PATH . 'index.php';
		$plugin_data = get_plugin_data($path);
		$plugin_version = $plugin_data['Version'];
		return $plugin_version;
	}

	function update_db() {
		$update = new RTDBUpdate();
		if ($update->check_upgrade()) {
			$update->do_upgrade();
		}
		new RTMediaMigration();
	}

	function enqueue_scripts_styles() {
                wp_enqueue_script('bp-media-mejs', RTMEDIA_URL . 'lib/media-element/mediaelement-and-player.min.js', '', RTMEDIA_VERSION);
                wp_enqueue_style('bp-media-mecss', RTMEDIA_URL . 'lib/media-element/mediaelementplayer.min.css', '', RTMEDIA_VERSION);
		wp_enqueue_style('rt-media-main', RTMEDIA_URL . 'app/assets/css/main.css', '', RTMEDIA_VERSION);
		wp_enqueue_script('rt-media-main', RTMEDIA_URL . 'app/assets/js/rtMedia.js', '', RTMEDIA_VERSION);
		wp_enqueue_style('rt-media-magnific', RTMEDIA_URL . 'lib/magnific/magnific.css', '', RTMEDIA_VERSION);
		wp_enqueue_script('rt-media-magnific', RTMEDIA_URL . 'lib/magnific/magnific.js', '', RTMEDIA_VERSION);

	}

}

function get_rt_media_permalink($id) {
        $mediaModel = new RTMediaModel();

    	$media = $mediaModel->get(array('id'=>$id));

	$parent_link = get_rt_media_user_link($media[0]->media_author);

	return trailingslashit($parent_link . 'media/' . $id);
}

function get_rt_media_user_link($id){
    	if(function_exists('bp_core_get_user_domain')) {
		$parent_link = bp_core_get_user_domain($id);
	} else {
		$parent_link = get_author_posts_url($id);
	}
        return $parent_link;
}

function rt_media_update_site_option($option_name,$option_value) {
        update_site_option($option_name, $option_value);
    }

function rt_media_get_site_option($option_name,$default=false){
	$return_val	 = get_site_option($option_name);
	if($return_val === false){
		if(function_exists("bp_get_option")){
			$return_val = bp_get_option($option_name,$default);
			rt_media_update_site_option($option_name, $return_val);
		}
	}
	if($default!== false && $return_val === false){
		$return_val = $default;
	}
	return $return_val;
}


/**
 * This wraps up the main rtMedia class. Three important notes:
 *
 * 1. All the constants can be overridden.
 *    So, you could use, 'portfolio' instead of 'media'
 * 2. The default thumbnail and display sizes can be filtered
 *    using 'bpmedia_media_sizes' hook
 * 3. The excerpts and string sizes can be filtered
 *    using 'bpmedia_excerpt_lengths' hook
 *
 */

