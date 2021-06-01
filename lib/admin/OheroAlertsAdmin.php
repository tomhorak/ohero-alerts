<?php
/**
 * This class handles everything within the WP Admin interface
 * Like the CPT, menu, and meta-boxes
 * It also handles the callback for the ajax
 */

namespace OHERO\Alerts\lib\admin;

// If this file is called directly, bail out!
if( ! defined( 'WPINC' ) ) {
	die;
}

use OHERO\Alerts\lib\common\OheroAlertsMain;

/**
 * Class OheroAlertsAdmin
 *
 * This class handles the admin interface and ajax callback
 *
 * @extends OheroAlertsMain
 * @package OHERO\Alerts\lib\admin
 */
class OheroAlertsAdmin extends OheroAlertsMain {

	/**
	 * Container for the meta-boxes
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @var array array of meta boxes
	 */
	public array $oh_meta_box;

	/**
	 * OheroAlertsAdmin constructor.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function __construct() {

		parent::__construct();
		$this->oh_register_hooks();

	}

	/**
	 * Hook registration for admin plugin
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function oh_register_hooks() {
		add_action( 'admin_menu', [ $this, 'oh_add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'oh_save_meta_data' ] );
		add_action( 'admin_init', [ $this, 'preload_plugin' ] );

	}

	/**
	 * Ajax handler
	 *
	 * @since    1.0.0
	 * @access   public static
	 */
	public static function oh_mark_alert_as_read() {

		$curr_user = wp_get_current_user();
		if( $curr_user !== null ) {
			$user_id = $curr_user->ID;
		} else {
			$user_id = 0;
		}
		$handler = new self();

		//verify the nonce field
		check_ajax_referer( OheroAlertsMain::NONCE );

		if( isset( $_POST[ 'alert_read' ] ) ) {

			$alert_id  = (int) $_POST[ 'alert_read' ];
			$a_user_id = (int) $_POST[ 'a_user_id' ];

			if( $_POST[ 'a_user_id' ] === 0 ) {
				if( isset( $_POST[ 'alert_id' ] ) ) {
					$marked_as_read = '1';
				} else {
					$marked_as_read = '0';
				}
			} else {
				$marked_as_read = $handler->oh_alert_add_to_usermeta( $alert_id, $a_user_id );
			}

			if( $marked_as_read === '1' ) {
				$response = array(
					'alert_id'       => $alert_id,
					'$a_user_id'     => $a_user_id,
					'message'        => __( 'Saved', 'ohero-alerts' ),
					'marked_as_read' => $marked_as_read
				);
				wp_send_json_success( $response, 200 );
			} else {
				$response = array(
					'alert_id'       => $alert_id,
					'$a_user_id'     => $a_user_id,
					'message'        => __( 'Error', 'ohero-alerts' ),
					'marked_as_read' => $marked_as_read
				);
				wp_send_json_error( $response, 400 );
			}
		}
		//end of ajax so we must die now!
		die();
	}

	/**
	 * When a user dismisses an alert we can add it to their meta
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @param $post_id
	 * @param $a_user_id
	 *
	 * @return string
	 */
	public function oh_alert_add_to_usermeta( $post_id, $a_user_id ) {

		$read_alerts_arr = get_user_meta( $a_user_id, 'ohero_alert_ids', false );
		if( isset( $read_alerts_arr[ 0 ] ) ) {
			$read_alerts = $read_alerts_arr[ 0 ];
		} else {
			$read_alerts = [];
		}

		//check if for some reason it's already been read by this user
		if( ! empty( $read_alerts ) ) {
			foreach( $read_alerts as $alert ) {
				if( in_array( (int) $post_id, $read_alerts, true ) ) {
					return '1';
				}
			}
		}

		//This alert has not been read, so let's add it now that it has
		//WP stores this as a serialized array that will grow into an infinite number of dimensions
		//So let's just rebuild it every time so that it stores correctly
		foreach( $read_alerts as $key => $val ) {
			$new_alerts_arr[] = $val;
		}

		$new_alerts_arr[] = $post_id;
		//update or create a user_meta record
		$result = update_user_meta( $a_user_id, 'ohero_alert_ids', $new_alerts_arr );

		//already exists but didn't catch it above, or the update failed
		if( $result === false ) {
			return '0';
		}

		//success
		return '1';
	}

	/**
	 * hooks must be registered in the constructor
	 * so that the parent also registers the ajax
	 * with the proper timing of wp_loading in
	 *
	 * @return mixed|void
	 * @since    1.0.0
	 * @access   public
	 */
	public function run() {
		// TODO: Implement run() method.
	}

	/**
	 * Add the meta-boxes
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function oh_add_meta_boxes() {

		$this->oh_meta_box = $this->oh_define_meta_box();

		add_meta_box( $this->oh_meta_box[ 'id' ], $this->oh_meta_box[ 'title' ], [
			$this,
			'oh_render_meta_box'
		], 'alerts', $this->oh_meta_box[ 'context' ], $this->oh_meta_box[ 'priority' ] );

	}

	/**
	 * Defines the meta-boxes to be stored
	 * in the container array property
	 * $this->oh_meta_box[]
	 *
	 * Dull colors to start with, can be modified in the oh_alerts.css
	 *
	 * @since    1.0.0
	 *
	 * @access   public
	 *
	 * @return array
	 */
	public function oh_define_meta_box() {

		return array(
			'id'       => $this->oh_prefix . 'meta_box',
			'title'    => __( 'Alert Configuration', 'ohero-alerts' ),
			'context'  => 'side',
			'priority' => 'low',
			'fields'   => array(
				array(
					'name'    => __( 'Color', 'ohero-alerts' ),
					'id'      => $this->oh_prefix . 'alert_color',
					'type'    => 'select',
					'desc'    => __( 'Choose the alert color', 'ohero-alerts' ),
					'options' => array( 'Red', 'Yellow', 'Green' )
				),
				array(
					'name' => __( 'Logged In Users', 'ohero-alerts' ),
					'id'   => $this->oh_prefix . 'alert_for_logged_in_only',
					'type' => 'checkbox',
					'desc' => __( 'Logged-in users only', 'ohero-alerts' )
				)
			)
		);
	}

	/**
	 * Callback function to show fields in meta box
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function oh_render_meta_box() {
		global $post;
		// Use nonce for verification
		$html = '<input type="hidden" name="oh_meta_box" value="' . wp_create_nonce( basename( __FILE__ ) ) . '" />';
		$html .= '<table class="form-table">';
		foreach( $this->oh_meta_box[ 'fields' ] as $field ) {
			// get current post meta data
			$meta = get_post_meta( $post->ID, $field[ 'id' ], true );
			if( $this->debug === 'true' ) {
				\Kint::dump( $field );
				\Kint::dump( $meta );
			}
			$html .= '<tr>';
			$html .= "<td>" . $field[ 'desc' ] . "</td>";
			$html .= '<td>';
			switch ( $field[ 'type' ] ) {

				case 'select':
					$html .= '<select name="' . $field[ "id" ] . '" id="' . $field[ "id" ] . '">';
					foreach( $field[ 'options' ] as $option ) {
						$selected = ( $meta === $option ) ? ' selected' : '';
						$html     .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
					}
					$html .= '</select>';
					break;
				case 'checkbox':
					$checked = $meta ? ' checked' : '';
					$html    .= '<input type="checkbox" name="' . $field[ "id" ] . '" id="' . $field[ "id" ] . '"' . $checked . '>';
					break;

			}
			$html .= '</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		echo $html;
	}

	/**
	 * Save data callback from meta box
	 *
	 * @since    1.0.0
	 *
	 * @access   public
	 *
	 * @param $post_id
	 *
	 * @return bool
	 *
	 */
	public function oh_save_meta_data( $post_id ) {

		// verify nonce
		if( ! isset( $_POST[ 'oh_meta_box' ] ) || ! wp_verify_nonce( $_POST[ 'oh_meta_box' ], basename( __FILE__ ) ) ) {

			return $post_id;

		}
		// check autosave
		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {

			return $post_id;

		}
		// check permissions
		if( 'page' === $_POST[ 'post_type' ] ) {

			if( ! current_user_can( 'edit_page', $post_id ) ) {

				return $post_id;

			}

		} elseif( ! current_user_can( 'edit_post', $post_id ) ) {

			return $post_id;

		}
		//Iterate through the fields
		foreach( $this->oh_meta_box[ 'fields' ] as $field ) {

			if( isset( $_POST[ $field[ 'id' ] ] ) ) {

				//get the 'old' data
				$old = get_post_meta( $post_id, $field[ 'id' ], true );
				//get the 'new' data
				$data = $_POST[ $field[ 'id' ] ];
				//if it's dirty save it, if it's empty delete it
				if( ( $data || $data === 0 ) && $data !== $old ) {

					update_post_meta( $post_id, $field[ 'id' ], $data );

				} elseif( '' === $data && $old ) {

					//new and old data is empty
					delete_post_meta( $post_id, $field[ 'id' ], $old );

				}

			} else {

				delete_post_meta( $post_id, $field[ 'id' ] );
			}

		}

		return true;

	}

	/**
	 * Preloader run during admin initialization
	 *
	 * @since    1.0.0
	 *
	 * @access   private
	 */
	public function preload_plugin() {
		if ( is_admin() && get_option( 'oh_activated_plugin' ) === PLUGIN_NAME ) {

			//delete the option added when activating so that it won't run again
			delete_option( 'oh_activated_plugin' );

			//Create the 3 welcome posts
			self::add_welcome_posts();
		}
	}

	/**
	 * Adds 3 welcome posts to help educate end user
	 *
	 * @since    1.0.0
	 *
	 * @access   private
	 */
	public static function add_welcome_posts(){

		//Make sure post type is available
		OheroAlertsMain::oh_create_alerts();

		//Flush permalinks so our rules can be added
		flush_rewrite_rules();

		$alert_post_type = 'alerts';
		$alert_post_status = 'publish';
		$welcome_alerts['3']['title'] = 'This is an Announcement: Alert.';
		$welcome_alerts_content3 = 'This would normally be used to announce something nice. e.g.<br>';
		$welcome_alerts_content3 .= 'Bob won the box seat tickets to the football game!<br>';
		$welcome_alerts_content3 .= '<br>Click the "X" in the top right corner to dismiss this message.<br>';
		$welcome_alerts['3']['content'] = $welcome_alerts_content3;

		$welcome_alerts['2']['title'] = 'This is a Warning: Alert';
		$welcome_alerts_content2 = 'Warning: I25 will be closed from 58th Ave. to 88th Ave. for the next 3 days.<br>';
		$welcome_alerts_content2 .= '<br>Click the "X" in the top right corner to dismiss this message.<br>';
		$welcome_alerts['2']['content'] = $welcome_alerts_content2;

		$welcome_alerts['1']['title'] = 'This is a Danger: Alert';
		$welcome_alerts_content1 = 'Danger: The South parking lot is closed to repair the sinkhole. Please use the North and West lots.<br>';
		$welcome_alerts_content1 .= 'Do NOT attempt to park in the South lot!<br>';
		$welcome_alerts_content1 .= '<br>Click the "X" in the top right corner to dismiss this message.<br>';
		$welcome_alerts['1']['content'] = $welcome_alerts_content1;

		$meta_arr['3'] = [
			'oh_alert_color' => 'Green',
			'oh_alert_for_logged_in_only' => 'on'
		];
		$meta_arr['2'] = [
			'oh_alert_color' => 'Yellow',
			'oh_alert_for_logged_in_only' => 'on'
		];
		$meta_arr['1'] = [
			'oh_alert_color' => 'Red',
			'oh_alert_for_logged_in_only' => 'on'
		];

		//Fire off the inserts
		$x = 1;
		foreach($welcome_alerts as $alert){
			self::add_post_alert($welcome_alerts[$x]['title'], $welcome_alerts[$x]['content'], $alert_post_type, $alert_post_status, $meta_arr[$x]);
			$x++;
		}

	}

	/**
	 * Add an alert post
	 * Using this to create initial 3 posts during activation that explain how to use it.
	 *
	 * //Example Structure for meta data
	 * 			'meta_input'   => array(
	 * 				'your_custom_key1' => 'your_custom_value1',
	 * 				'your_custom_key2' => 'your_custom_value2'
	 * 				// and so on ;)
	 * 			)
	 *
	 * @since    1.0.0
	 *
	 * @access   private
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return $this|false
	 */
	private static function add_post_alert( $title = '', $content = '', $post_type = 'Alert', $post_status = 'publish', $meta_arr = [] ) {

		$post_id = wp_insert_post( array(
				'post_content' => $content,
				'post_title'   => $title,
				'post_type'    => $post_type,
				'post_status'  => $post_status,
				// Meta Data example structure above
				'meta_input'   => $meta_arr,
			)
		);

		if( $post_id ) {
			// it worked :)
			return $post_id;
		}
		return false;
	}

}