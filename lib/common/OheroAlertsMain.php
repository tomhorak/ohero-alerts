<?php
/**
 * Abstract class providing common functionality needed
 * by the admin and front end classes that extend this one
 * It also registers the ajax hooks
 */

namespace OHERO\Alerts\lib\common;

/**
 * Class OheroAlertsMain
 *
 * @package OHERO\Alerts\lib\common
 */
abstract class OheroAlertsMain {
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The text domain of the plugin.
	 * Used for internationalization (not fully implemented)
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_text_domain
	 */
	protected $plugin_text_domain;

	/**
	 * @since    1.0.0
	 * @access   public
	 * @var string meta box prefix
	 */
	public string $oh_prefix;

	/**
	 * @since    1.0.0
	 * @access   public
	 * @var string 'true' or 'false', so there's no issues with bool/int/string
	 */
	public string $debug;

	/**
	 * Used for creating our NONCE field data
	 * @since    1.0.0
	 */
	const NONCE = 'ohero-alerts-ajax';

	/**
	 * The action we will pass via ajax when an alert is dismissed
	 * @since    1.0.0
	 */
	const ACTION = 'oh_mark_alert_as_read';

	/**
	 * OheroAlertsMain constructor.
	 *
	 * The constructor for our base class which handles common methods
	 *
	 * @since    1.0.0
	 *
	 * @access   public
	 */
	public function __construct() {

		$this->plugin_name = PLUGIN_NAME;
		$this->version = PLUGIN_VERSION;
		$this->plugin_text_domain = PLUGIN_TEXT_DOMAIN;
		$this->oh_prefix = 'oh_';
		$this->debug = $this->oh_maybe_debug();

		add_action( 'init', [ $this, 'oh_create_alerts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'oh_enqueue_scripts' ] );
		//Must wait until WP has been loaded before registering ajax hooks
		add_action( 'wp_loaded', [ $this, 'register_hooks' ]);
	}

	/**
	 * Enable or disable debug
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @param string $debug
	 *
	 * @return mixed|string
	 */
	private function oh_maybe_debug($debug = "false"){
		if( isset($_REQUEST['debug']) && $_REQUEST['debug'] === "true"){
			$debug = "true";
		}
		if( isset( $_REQUEST['debug'] ) && ( $_REQUEST['debug'] !== "true" || $_REQUEST['debug'] === "false") ) {
			$debug = "false";
		}
		if( OHERO_DEBUG_ENABLE === "true" ){
			$debug = "true";
		}
		if( OHERO_DEBUG_ENABLE !== "true" || OHERO_DEBUG_ENABLE === "false" ){
			$debug = "false";
		}

		return $debug;
	}

	/**
	 * Register the Ajax hooks so they fire during an ajax call
	 *
	 * @since    1.0.0
	 * @access   public | shared (static)
	 */
	 public static function register_hooks(){

		 add_action( 'wp_ajax_oh_mark_alert_as_read', [ 'OHERO\Alerts\lib\admin\OheroAlertsAdmin', 'oh_mark_alert_as_read' ] );
		 add_action( 'wp_ajax_nopriv_oh_mark_alert_as_read', [ 'OHERO\Alerts\lib\admin\OheroAlertsAdmin', 'oh_mark_alert_as_read' ] );
	 }

	/**
	 * @since    1.0.0
	 * @access   public
	 *
	 * @return mixed
	 */
	abstract public function run();

	/**
	 * This is our main css file for styles
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 */
	public function oh_enqueue_scripts(){
		wp_enqueue_style( 'oh-alerts-css', OHERO_ALERTS_URL . 'assets' . DS .'css' . DS . 'oh_alerts.css',  null, PLUGIN_CACHE_BUSTER);
		wp_enqueue_script( 'jquery-cookies', OHERO_ALERTS_URL . 'assets' . DS . 'js' . DS . 'jquery.cookie.js', array( 'jquery' ), PLUGIN_CACHE_BUSTER );
	}

	/**
	 * Method for loading our Custom Post Type - Alerts
	 * @since    1.0.0
	 * @access   public
	 */
	public static function oh_create_alerts() {

		$labels = array(
			'name' => _x( 'Alerts', 'post type general name' ), // Tip: _x('') is used for localization
			'singular_name' => _x( 'Alert', 'post type singular name' ),
			'add_new' => _x( 'Add New', 'Alert' ),
			'add_new_item' => __( 'Add New Alert' ),
			'edit_item' => __( 'Edit Alert' ),
			'new_item' => __( 'New Alert' ),
			'view_item' => __( 'View Alert' ),
			'search_items' => __( 'Search Alerts' ),
			'not_found' =>  __( 'No Alerts found' ),
			'not_found_in_trash' => __( 'No Alerts found in Trash' ),
			'parent_item_colon' => ''
		);

		$alert_args = array(
			'labels' => $labels,
			'singular_label' => __('Alert', 'ohero-alerts'),
			'public' => true,
			'show_ui' => true,
			'capability_type' => 'post',
			'hierarchical' => false,
			'rewrite' => false,
			'supports' => array('title', 'editor'),
			'query_var' => true,
			'exclude_from_search' => true,
		);
		register_post_type('alerts', $alert_args);
	}

}