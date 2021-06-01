<?php
/*
Plugin Name: _Office Hero Alerts
Plugin URI: http://horaks.com/wp/ohero-alerts
Description: An alert bar at the top of the site
Version: 1.0.0
Author: Tom Horak
Author URI: http://horaks.com/wp
*/

/**
 * Notes: This was primarily built for use with BuddyPress, however it works also for a regular WP site.
 * It can be used for public alerts and private
 */

namespace OHEROAlerts;

// If this file is called directly, bail out!
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define the plugin constants
 * TODO: Switch the cache buster constant to production when not on dev
 */
$random_cache_int = random_int( 0, 100000 );
if( !defined( 'OHERO_DEBUG_ENABLE' ) ) define( 'OHERO_DEBUG_ENABLE', 'false' );
if( !defined( 'OHERO_ALERTS_URL' ) ) define( 'OHERO_ALERTS_URL', plugin_dir_url( __FILE__ ) );
if( !defined( 'OHERO_ALERTS_DIR' ) ) define( 'OHERO_ALERTS_DIR', plugin_dir_path( __FILE__ ) );
if( !defined( 'PLUGIN_NAME' ) ) define( 'PLUGIN_NAME', 'ohero-alerts' );
if( !defined( 'PLUGIN_VERSION' ) ) define( 'PLUGIN_VERSION', '1.0' );
if( !defined( 'PLUGIN_DIR' ) ) define( 'PLUGIN_DIR', plugin_dir_path( __FILE__ )  );
if( !defined( 'PLUGIN_TEXT_DOMAIN' ) ) define( 'PLUGIN_TEXT_DOMAIN', 'ohero_alerts' );
//if( !defined( 'PLUGIN_CACHE_BUSTER' ) ) define( 'PLUGIN_CACHE_BUSTER', $random_cache_int );//DEV
if( !defined( 'PLUGIN_CACHE_BUSTER' ) ) define( 'PLUGIN_CACHE_BUSTER', PLUGIN_VERSION ); //Production
if( !defined( 'DS' ) ) define( 'DS', DIRECTORY_SEPARATOR ); //Just in case

// Include the autoloader to load each class file as needed
$file = PLUGIN_DIR .  'autoload.php';
if ( is_readable( $file ) ) {
	require_once $file;
}

// Now we can use the class by namespace
use OHERO\Alerts\lib\admin\OheroAlertsAdmin as OHAdmin;
use OHERO\Alerts\lib\app\OheroAlertsPublic as OHPublic;


/**
 * Instantiate the plugin based on if in admin or front end view
 * The admin plugin runs during an ajax call, so both are active for a second
 * Different contexts, so no collision
 *
 * @since    1.0.0
 */
function run_ohero_alerts() {

	if(is_admin()) {
		$plugin = new OHAdmin(); //add metabox
	} else {
		$plugin = new OHPublic(); //display alert
	}
}
run_ohero_alerts();


/**
 * The code that runs during plugin activation.
 * Adds an option in wp_options to be used as a hook when running the plugin
 *
 * @since    1.0.0
 */
function ohero_alerts_on_activate() {
	//adding an option to create 3 initial alerts
	add_option( 'oh_activated_plugin',PLUGIN_NAME );
}

/**
 * The code that runs during plugin deactivation.
 *
 * @since    1.0.0
 */
function ohero_alerts_on_deactivate() {

	// Unregister the post type, so the rules are no longer in memory.
	unregister_post_type( 'alerts' );

	// Clear the permalinks to remove our post type's rules from the database.
	flush_rewrite_rules();

	// wp_delete_post()
	// TODO: Decide how best to implement.  Either a quick modal or prompt with a y/n
	// OR if eventually having an options page, add it there
}

//not doing anything special, could clean up the database if I add an options page etc..
//Added the automatic build out of 3 alerts to show how it works.
register_activation_hook( __FILE__, __NAMESPACE__.'\ohero_alerts_on_activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__.'\ohero_alerts_on_deactivate' );