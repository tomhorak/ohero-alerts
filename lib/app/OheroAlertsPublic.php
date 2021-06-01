<?php
/**
 * The public or front end class that handles the logic for the alert banner
 */

namespace OHERO\Alerts\lib\app;
// If this file is called directly, bail out!
if( ! defined( 'WPINC' ) ) {
	die;
}

use Kint; //debugging
use OHERO\Alerts\lib\common\OheroAlertsMain;

/**
 * Class OheroAlertsPublic
 *
 * This class handles the front end viewing side
 *
 * @extends OheroAlertsMain
 * @package OHERO\Alerts\lib\app
 */
class OheroAlertsPublic extends OheroAlertsMain {

	/**
	 * OheroAlertsPublic constructor.
     *
	 * @since    1.0.0
     *
	 * @access   public
	 */
	public function __construct() {

		parent::__construct();

	}

	/**
	 * Run the plugin
     * Registers the hooks
	 *
	 * @return mixed|void
	 * @since    1.0.0
     *
	 * @access   public
	 */
	public function run() {

		self::register_hooks();

	}

	/**
	 * Register the hooks needed for the front end
     *
	 * @since    1.0.0
	 * @access   public static
	 */
	public static function register_hooks() {

		$handler = new self();
		add_action( 'wp_enqueue_scripts', [ $handler, 'oh_register_script' ] );
		add_action( 'wp_enqueue_scripts', [ $handler, 'oh_enqueue_scripts' ] );
		add_action( 'wp_footer', [ $handler, 'oh_display_alert' ] );

	}

	/**
	 * Loads the alert js handler script if needed
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function oh_register_script() {

		$logged_in = 'yes';

		/**
		 * For not logged in users
		 */
        if( ! is_user_logged_in() ) {
			wp_enqueue_script( 'jquery-cookies', OHERO_ALERTS_URL . 'assets' . DS . 'js' . DS . 'jquery.cookie.js', array( 'jquery' ) );
			$logged_in = 'no';
		}

		/**
		 * First register, then 'localize' (create the js obj), then enqueue
		 */
		wp_register_script( 'oh_alerts', OHERO_ALERTS_URL . 'assets' . DS . 'js' . DS . 'oh_alerts.js', array( 'jquery' ), PLUGIN_CACHE_BUSTER, false );
		wp_localize_script( 'oh_alerts', 'alerts_ajax_script', array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'action'    => OheroAlertsMain::ACTION, //oh_mark_alert_as_read
				'logged_in' => $logged_in,
				'nonce'     => wp_create_nonce( OheroAlertsMain::NONCE )
			) );
		wp_enqueue_script( 'oh_alerts', );
	}

	/**
	 * This displays the alert in the div with id=notification-area if the user has not read it before
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function oh_display_alert() {

		$curr_user = wp_get_current_user();
		$curr_user_ID = $curr_user->ID ?? 0;

		//Getting the last 3 alerts.  I think that's plenty 'active' alerts
		$alert_args = array( 'post_type' => 'alerts', 'posts_per_page' => 3 );
		$all_alerts = get_posts( $alert_args );

		/**
		 *  Filter out any currently read alerts
		 */
		$alerts_filtered_read = $this->filter_alerts_already_read( $curr_user_ID, $all_alerts );

		if( $this->debug === 'true' ) {
			Kint::dump( $alerts_filtered_read );
		}

		/**
		 * Filter out any alerts that require the user to be logged in.
		 */
		if( ! is_user_logged_in() ) {
			// NOT authenticated
			$alerts_filtered = $this->filter_alerts_for_logged_in_only( $alerts_filtered_read );
		} else {
			// IS authenticated
			$alerts_filtered = $alerts_filtered_read;
		}
		if( $this->debug === 'true' ) {
			Kint::dump( $alerts_filtered );
		}
		//if there are multiple then take the most recent and display it
		if( count( $alerts_filtered ) > 0 ) {
			$alert_pop = array_shift( $alerts_filtered );
			$alert     = $alert_pop;
		} else {
			//no more alerts, bail out!
			return;
		}
		if( $this->debug === 'true' ) {
			Kint::dump( $alert );
		}
		//Should now have the appropriate alert depending on logged in/out and most recent (highest id value)
		$logged_in_only = get_post_meta( $alert->ID, $this->oh_prefix . 'alert_for_logged_in_only', true );
		if( $this->debug === 'true' ) {
			Kint::dump( $logged_in_only );
		}
		//Last check before display
		$has_been_read = $this->oh_check_alert_is_read( $alert->ID, $curr_user_ID );
		if( $this->debug === 'true' ) {
			Kint::dump( $has_been_read );
		}
		//TODO: Perhaps build out the id/class/name dynamically so the alerts can be stacked and still work... Not sure how useful that might be.
		if( $has_been_read === false ) { ?>

            <div class="container-flex sticky-top-alert">
                <div id="notification-area"
                     class="notification-area <?php echo strtolower( get_post_meta( $alert->ID, $this->oh_prefix . 'alert_color', true ) ); ?> hidden">
                    <a class="remove-alert" href="#" id="remove-alert" data="<?php echo $curr_user_ID; ?>"
                       rel="<?php echo $alert->ID; ?>"><?php _e( 'X', 'ohero-alerts' ); ?></a>
                    <h3><?php echo get_the_title( $alert->ID ); ?></h3>
					<?php echo do_shortcode( wpautop( __( $alert->post_content ) ) ); ?>
					<?php
					if( $this->debug === 'true' ) {
						echo '<p>user_id: ' . $curr_user_ID . '</p>';
						echo '<p>alert_id: ' . $alert->ID . '</p>';
					}
					?>
                </div>
            </div>

		<?php }

	}

	/**
	 * @param $user_id
	 * @param $all_alerts
	 *
	 * @return array
	 */
	public function filter_alerts_already_read( $user_id, $all_alerts ) {
		if( $this->debug === 'true' ) {
			echo 'filtering read all alerts<br>';
			Kint::dump( $all_alerts );
		}

		$read_alerts_arr = get_user_meta( $user_id, 'ohero_alert_ids', false );
		if( $this->debug === 'true' ) {
			Kint::dump( $read_alerts_arr );
		}
		if( isset( $read_alerts_arr[ 0 ] ) && ( count( $read_alerts_arr[ 0 ] ) > 0 ) ) {
			$read_alerts = $read_alerts_arr[ 0 ];
		} else {
			return $all_alerts;
		}
		if( $this->debug === 'true' ) {
			Kint::dump( $read_alerts );
		}
		$new_alerts = [];
		//check if for some reason it's already been read by this user
		if( count( $read_alerts ) > 0 ) {

			foreach( $all_alerts as $alert ) {

				//DEBUG
				if( $this->debug === 'true' ) {
					echo 'alert_id: ' . $alert->ID . '<br>';
				}
				if( ! in_array( (int) $alert->ID, $read_alerts, true ) ) {

					//build out a new array containing just the unread alerts
					$new_alerts[] = $alert;

				}

			}

		}

		return $new_alerts;
	}

	/**
	 * Filters out alerts for logged in users only
	 * Returns only those for logged out users
	 *
	 * @param $alerts
	 *
	 * @return array
	 */
	public function filter_alerts_for_logged_in_only( $alerts ) {

		//The new array container
		$new_alerts = [];
		if( count( $alerts ) > 0 ) {

			foreach( $alerts as $alert ) {

				$logged_in_only = get_post_meta( $alert->ID, $this->oh_prefix . 'alert_for_logged_in_only', true );
				if( $logged_in_only !== "on" ) {

					$new_alerts[] = $alert;
					//DEBUG
					if( $this->debug === 'true' ) {
						Kint::dump( $alert );
					}

				}

			}

		}
		//DEBUG
		if( $this->debug === 'true' ) {
			Kint::dump( $new_alerts );
		}

		return $new_alerts;

	}

	/**
	 * Lookup users meta data to see if they have already read an alert
	 *
	 * @param $post_id
	 * @param $user_id
	 *
	 * @return bool
	 * @since    1.0.0
	 * @access   public
	 *
	 */
	public function oh_check_alert_is_read( $post_id, $user_id ) {

		$read_alerts = get_user_meta( $user_id, 'ohero_alert_ids', false );
//		\Kint::dump($read_alerts);
		if( is_array( $read_alerts ) ) {
			foreach( $read_alerts as $alert ) {
				if( in_array( $post_id, $alert, true ) ) {
					return true;
				}
			}
		}

		// if not closed
		return false;
	}

}